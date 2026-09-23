<?php

declare(strict_types=1);

namespace Forsa\Ai\Query;

use Forsa\Ai\Semantic\SemanticRegistry;

/**
 * PRD §12.4 — turns a QueryPlanner plan into a single parameterized SQL
 * string + bound params. The LLM never sees or produces this SQL (PRD
 * §11.2/§14): every identifier in the SELECT/GROUP BY/ORDER BY comes from
 * SemanticRegistry's own fixed strings, never from concatenating caller
 * input directly — only filter *values* are ever bound as parameters.
 */
final class QueryBuilder
{
    private const JOIN = 'FROM forsa_ftk_snapshot_rows r
        JOIN forsa_ftk_snapshots s ON s.id = r.snapshot_id
        JOIN forsa_shap_entities e ON e.id = s.shap_id';

    public function __construct(private readonly SemanticRegistry $registry = new SemanticRegistry())
    {
    }

    /** @return array{sql: string, params: array<string, mixed>} */
    public function build(array $plan): array
    {
        $selectParts = [];
        foreach ($plan['dimensions'] as $dim) {
            $selectParts[] = $this->registry->dimensionSql($dim) . ' AS ' . $this->quoteAlias($dim);
        }
        foreach ($plan['metrics'] as $metric) {
            $selectParts[] = $this->registry->metricSql($metric) . ' AS ' . $this->quoteAlias($metric);
        }

        $where = $plan['conditions'] === [] ? '1=1' : implode(' AND ', $plan['conditions']);

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' ' . self::JOIN . ' WHERE ' . $where;

        if ($plan['dimensions'] !== []) {
            $groupBy = array_map(fn ($d) => $this->registry->dimensionSql($d), $plan['dimensions']);
            $sql .= ' GROUP BY ' . implode(', ', $groupBy);
        }

        if ($plan['sort'] !== []) {
            $sql .= ' ORDER BY ' . $this->quoteAlias($plan['sort']['metric']) . ' ' . $plan['sort']['direction'];
        }

        // $plan['limit'] is always an already-capped int from QueryPlanner
        // (never raw user input), safe to inline — PDO can't bind LIMIT as
        // a typed parameter reliably across drivers.
        $sql .= ' LIMIT ' . (int) $plan['limit'];

        return ['sql' => $sql, 'params' => $plan['params']];
    }

    private function quoteAlias(string $name): string
    {
        return '"' . str_replace('"', '', $name) . '"';
    }
}
