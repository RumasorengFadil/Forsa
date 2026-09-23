<?php

declare(strict_types=1);

namespace Forsa\Ai\Tools;

use Forsa\Ai\Cache\AiQueryCache;
use Forsa\Ai\Query\QueryBuilder;
use Forsa\Ai\Query\QueryExecutor;
use Forsa\Ai\Query\QueryPlanner;
use Forsa\Ai\Semantic\PolicyResolver;

/**
 * PRD §11.2/§13 — the one generic analytics tool. Its JSON schema (below)
 * is exactly what gets registered into ToolRegistry and sent to the LLM;
 * `execute()` is the only place that turns validated arguments into an
 * actual database read, via QueryPlanner -> QueryBuilder -> QueryExecutor.
 */
final class QueryForsaTool
{
    public const NAME = 'query_forsa';

    public function __construct(
        private readonly ToolValidator $validator = new ToolValidator(),
        private readonly QueryPlanner $planner = new QueryPlanner(),
        private readonly QueryBuilder $builder = new QueryBuilder(),
        private readonly QueryExecutor $executor = new QueryExecutor(),
        private readonly AiQueryCache $cache = new AiQueryCache(),
        private readonly PolicyResolver $policy = new PolicyResolver(),
    ) {
    }

    public static function schema(): array
    {
        return [
            'name' => self::NAME,
            'description' => 'Mengambil data FTK (Formasi Tenaga Kerja) dan realisasi FORSA yang sudah divalidasi & tersimpan (snapshot), diagregasi sesuai metric/dimension/filter yang diminta. Selalu mengembalikan data teragregasi (SUM/GROUP BY), tidak pernah baris mentah per individu.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'metrics' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['total_ftk', 'total_realisasi_organik', 'total_realisasi_tugas_karya', 'total_realisasi_pihak_ketiga', 'total_realisasi', 'gap', 'fulfillment_rate']],
                        'description' => 'Metric yang diminta. Wajib diisi minimal satu.',
                    ],
                    'dimensions' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['company', 'period', 'job_level', 'position_grade', 'organization']],
                        'description' => 'Dimensi untuk mengelompokkan hasil (GROUP BY). Kosongkan untuk satu angka total.',
                    ],
                    'filters' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'dimension' => ['type' => 'string', 'enum' => ['company', 'job_level', 'position_grade', 'organization', 'gap_status']],
                                'operator' => ['type' => 'string', 'enum' => ['=', '!=', 'in']],
                                'value' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'period' => ['type' => 'string', 'description' => 'Format "YYYY-MM", atau "latest"/kosongkan untuk periode terbaru yang tersedia.'],
                    'sort' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'metric' => ['type' => 'string'],
                                'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                            ],
                        ],
                    ],
                    'limit' => ['type' => 'integer', 'default' => 20],
                    'comparison' => ['type' => 'string', 'enum' => ['previous_period'], 'description' => 'Isi "previous_period" untuk pertanyaan lanjutan seperti "kalau bulan sebelumnya?".'],
                ],
                'required' => ['metrics'],
            ],
        ];
    }

    /** @return array{rows: array, previous_rows: ?array, resolved_period: string, previous_period: ?string, sql: string} */
    public function execute(array $args, array $user): array
    {
        $this->validator->validateQueryForsaArgs($args);

        // PRD §20 cache key = ai:{user_scope_hash}:{semantic_query_hash} —
        // scope comes from PolicyResolver (same source QueryPlanner itself
        // uses for RBAC), so two users with different data scopes can never
        // share a cache entry even if they ask the exact same question.
        $scope = $this->policy->scopeFor($user);
        $cacheKey = $this->cache->key($scope, $args);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $plan = $this->planner->plan($args, $user);
        $built = $this->builder->build($plan);
        $rows = $this->executor->run($built['sql'], $built['params']);

        $previousRows = null;
        if ($plan['previous_period'] !== null) {
            $prevPlan = $this->planner->plan(array_merge($args, ['period' => $plan['previous_period']]), $user);
            $prevBuilt = $this->builder->build($prevPlan);
            $previousRows = $this->executor->run($prevBuilt['sql'], $prevBuilt['params']);
        }

        if ($rows === [] && $previousRows === null) {
            throw new \Forsa\Ai\Semantic\SemanticException(
                'Tidak ditemukan data yang sesuai dengan filter tersebut.',
                \Forsa\Ai\Semantic\SemanticException::NO_RESULT
            );
        }

        $result = [
            'rows' => $rows,
            'previous_rows' => $previousRows,
            'resolved_period' => $plan['resolved_period'],
            'previous_period' => $plan['previous_period'],
            'sql' => $built['sql'],
        ];
        $this->cache->set($cacheKey, $result);

        return $result;
    }
}
