<?php

declare(strict_types=1);

namespace Forsa\Ai\Query;

use Forsa\Ai\Semantic\AliasResolver;
use Forsa\Ai\Semantic\PolicyResolver;
use Forsa\Ai\Semantic\SemanticException;
use Forsa\Ai\Semantic\SemanticRegistry;

/**
 * PRD §12.2/§12.3 "Tahap Tool" + "Tahap Semantic Layer" combined: takes the
 * LLM's raw tool arguments, resolves every alias, validates every identifier
 * against SemanticRegistry (whitelist — PRD §14), applies RBAC (PRD §16),
 * and resolves "latest"/"previous_period" the same way DashboardService
 * does. Produces a plan QueryBuilder can turn into SQL — this class never
 * builds or runs SQL itself.
 */
final class QueryPlanner
{
    private const ALLOWED_OPERATORS = ['=', '!=', 'in'];
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly SemanticRegistry $registry = new SemanticRegistry(),
        private readonly AliasResolver $alias = new AliasResolver(),
        private readonly PolicyResolver $policy = new PolicyResolver(),
    ) {
    }

    /**
     * @param array{metrics?: array, dimensions?: array, filters?: array, sort?: array, limit?: int, period?: ?string, comparison?: ?string} $args
     */
    public function plan(array $args, array $user): array
    {
        $metrics = $this->resolveMetrics($args['metrics'] ?? []);
        $dimensions = $this->resolveDimensions($args['dimensions'] ?? []);

        $scope = $this->policy->scopeFor($user);
        if ($scope['allowed_company_ids'] === []) {
            throw new SemanticException('Anda tidak memiliki akses ke data tersebut.', SemanticException::ACCESS_DENIED);
        }

        [$conditions, $params, $resolvedCompanyIds] = $this->resolveFilters($args['filters'] ?? [], $scope['allowed_company_ids']);

        $companyId = count($resolvedCompanyIds) === 1 ? $resolvedCompanyIds[0] : null;
        $period = $this->resolvePeriod($args['period'] ?? null, $companyId);
        if ($period === null) {
            throw new SemanticException('Tidak ditemukan data yang sesuai dengan filter tersebut.', SemanticException::NO_RESULT);
        }
        $conditions[] = 's.period_month = :period';
        $params['period'] = $period;

        $previousPeriod = null;
        if (($args['comparison'] ?? null) === 'previous_period') {
            $previousPeriod = $this->resolvePeriod(null, $companyId, before: $period);
        }

        $sort = $this->resolveSort($args['sort'] ?? [], $metrics);
        $limit = min(self::MAX_LIMIT, max(1, (int) ($args['limit'] ?? self::DEFAULT_LIMIT)));

        return [
            'model' => $this->registry->name(),
            'metrics' => $metrics,
            'dimensions' => $dimensions,
            'conditions' => $conditions,
            'params' => $params,
            'sort' => $sort,
            'limit' => $limit,
            'resolved_period' => $period,
            'previous_period' => $previousPeriod,
        ];
    }

    private function resolveMetrics(array $requested): array
    {
        if ($requested === []) {
            throw new SemanticException('Minimal satu metric harus diminta.', SemanticException::METRIC_UNAVAILABLE);
        }
        $resolved = [];
        foreach ($requested as $m) {
            $canonical = $this->registry->hasMetric($m) ? $m : $this->alias->metricAlias((string) $m);
            if ($canonical === null || !$this->registry->hasMetric($canonical)) {
                throw new SemanticException("Data \"{$m}\" belum tersedia pada sumber data AI FORSA.", SemanticException::METRIC_UNAVAILABLE);
            }
            $resolved[] = $canonical;
        }
        return array_values(array_unique($resolved));
    }

    private function resolveDimensions(array $requested): array
    {
        $resolved = [];
        foreach ($requested as $d) {
            $canonical = $this->registry->hasDimension($d) ? $d : $this->alias->dimensionAlias((string) $d);
            if ($canonical === null || !$this->registry->hasDimension($canonical)) {
                throw new SemanticException("Dimensi \"{$d}\" belum tersedia pada sumber data AI FORSA.", SemanticException::METRIC_UNAVAILABLE);
            }
            $resolved[] = $canonical;
        }
        return array_values(array_unique($resolved));
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>, 2: array<int, int>}
     */
    private function resolveFilters(array $filters, ?array $allowedCompanyIds): array
    {
        $conditions = [];
        $params = [];
        $companyIds = [];
        $paramSeq = 0;

        foreach ($filters as $f) {
            $dimension = $this->registry->hasDimension($f['dimension'] ?? '') ? $f['dimension'] : $this->alias->dimensionAlias((string) ($f['dimension'] ?? ''));
            $operator = $f['operator'] ?? '=';
            $value = $f['value'] ?? null;

            if ($dimension === null || !in_array($operator, self::ALLOWED_OPERATORS, true) || $value === null) {
                throw new SemanticException('Filter tidak valid.', SemanticException::METRIC_UNAVAILABLE);
            }

            if ($dimension === 'company') {
                $matches = $this->alias->resolveCompany((string) (is_array($value) ? ($value[0] ?? '') : $value));
                if ($matches === []) {
                    throw new SemanticException('Tidak ditemukan data yang sesuai dengan filter tersebut.', SemanticException::NO_RESULT);
                }
                $ids = array_column($matches, 'id');
                $companyIds = $ids;
                $paramSeq++;
                $conditions[] = "s.shap_id IN (" . implode(',', array_map(fn ($i) => ':cid' . $paramSeq . '_' . $i, array_keys($ids))) . ")";
                foreach ($ids as $i => $id) {
                    $params['cid' . $paramSeq . '_' . $i] = $id;
                }
                continue;
            }

            if ($dimension === 'gap_status') {
                $conditions[] = match ($value) {
                    'kurang' => 'r.sisa_delta > 0',
                    'terpenuhi' => 'r.sisa_delta = 0',
                    'lebih' => 'r.sisa_delta < 0',
                    default => throw new SemanticException('Status gap tidak dikenal.', SemanticException::METRIC_UNAVAILABLE),
                };
                continue;
            }

            if ($dimension === 'job_level') {
                $group = $this->registry->hasDimension('job_level') ? ($this->alias->jobLevelLabelToGroup((string) $value) ?? $value) : $value;
                $paramSeq++;
                $conditions[] = 'r.job_level_group = :jl' . $paramSeq;
                $params['jl' . $paramSeq] = $group;
                continue;
            }

            // Generic passthrough for position_grade/organization/period —
            // period is handled separately by resolvePeriod() below, so a
            // filter with dimension=period is intentionally ignored here and
            // read directly from $args['period'] by plan() instead.
            if ($dimension === 'period') {
                continue;
            }

            $sql = $this->registry->dimensionSql($dimension);
            $paramSeq++;
            $conditions[] = $sql . ' = :dim' . $paramSeq;
            $params['dim' . $paramSeq] = is_array($value) ? ($value[0] ?? '') : $value;
        }

        if ($allowedCompanyIds !== null && $companyIds !== []) {
            $companyIds = array_values(array_intersect($companyIds, $allowedCompanyIds));
            if ($companyIds === []) {
                throw new SemanticException('Anda tidak memiliki akses ke data tersebut.', SemanticException::ACCESS_DENIED);
            }
        } elseif ($allowedCompanyIds !== null) {
            $companyIds = $allowedCompanyIds;
        }

        return [$conditions, $params, $companyIds];
    }

    private function resolveSort(array $sort, array $resolvedMetrics): array
    {
        if ($sort === []) {
            return [];
        }
        $first = $sort[0];
        $metric = $first['metric'] ?? null;
        if ($metric === null || !in_array($metric, $resolvedMetrics, true)) {
            $metric = $resolvedMetrics[0];
        }
        $direction = strtolower((string) ($first['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return ['metric' => $metric, 'direction' => $direction];
    }

    /**
     * PRD §40 Snapshot Awareness: "latest" = MAX(period_month) among active
     * snapshots — scoped to one company when exactly one is in play (a more
     * useful "latest" than the dashboard's global-merge default when the
     * question is clearly about a specific SH/AP), otherwise the same
     * global-latest definition DashboardService::resolveSnapshots() uses.
     */
    private function resolvePeriod(?string $requested, ?int $companyId, ?string $before = null): ?string
    {
        if ($requested !== null && $requested !== 'latest' && $requested !== '') {
            // Accept "YYYY-MM" from the LLM, normalize to a full date.
            return preg_match('/^\d{4}-\d{2}$/', $requested) ? $requested . '-01' : $requested;
        }

        $sql = 'SELECT MAX(period_month) AS m FROM forsa_ftk_snapshots WHERE is_active = TRUE';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND shap_id = :shap_id';
            $params['shap_id'] = $companyId;
        }
        if ($before !== null) {
            $sql .= ' AND period_month < :before';
            $params['before'] = $before;
        }
        $stmt = AiReaderConnection::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row['m'] ?? null;
    }
}
