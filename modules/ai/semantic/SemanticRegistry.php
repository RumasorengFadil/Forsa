<?php

declare(strict_types=1);

namespace Forsa\Ai\Semantic;

/**
 * Loads the approved semantic model(s) (currently just `ftk_workforce`) and
 * is the single source of truth for which metric/dimension names exist —
 * PRD §14 "Tool hanya menerima identifier yang telah terdaftar pada semantic
 * registry." Every metric/dimension name that reaches SQL comes from this
 * class's own array, never from string concatenation of caller input.
 */
final class SemanticRegistry
{
    private array $model;

    public function __construct(?string $modelName = 'ftk_workforce')
    {
        $path = __DIR__ . '/models/' . $modelName . '.php';
        if (!is_file($path)) {
            throw new SemanticException("Model semantic \"{$modelName}\" tidak ditemukan.");
        }
        $this->model = require $path;
    }

    public function name(): string
    {
        return $this->model['name'];
    }

    /** @return array<string, array{sql: string}> */
    public function metrics(): array
    {
        return $this->model['metrics'];
    }

    /** @return array<string, array{sql: string}> */
    public function dimensions(): array
    {
        return $this->model['dimensions'];
    }

    public function metricSql(string $name): string
    {
        if (!isset($this->model['metrics'][$name])) {
            throw new SemanticException("Metric \"{$name}\" tidak dikenal.", SemanticException::METRIC_UNAVAILABLE);
        }
        return $this->model['metrics'][$name]['sql'];
    }

    public function dimensionSql(string $name): string
    {
        if (!isset($this->model['dimensions'][$name])) {
            throw new SemanticException("Dimension \"{$name}\" tidak dikenal.", SemanticException::METRIC_UNAVAILABLE);
        }
        return $this->model['dimensions'][$name]['sql'];
    }

    public function hasMetric(string $name): bool
    {
        return isset($this->model['metrics'][$name]);
    }

    public function hasDimension(string $name): bool
    {
        return isset($this->model['dimensions'][$name]);
    }
}
