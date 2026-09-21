<?php

declare(strict_types=1);

namespace Forsa;

final class JobLevelMapper
{
    private static ?array $rules = null;

    private static function rules(): array
    {
        if (self::$rules === null) {
            self::$rules = require dirname(__DIR__) . '/config/ftk_rules.php';
        }
        return self::$rules;
    }

    public static function map(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        if ($key === '') {
            return null;
        }
        $map = self::rules()['job_level_map'];
        return $map[$key] ?? null;
    }

    public static function orderedGroups(): array
    {
        return self::rules()['job_level_order'];
    }

    public static function label(string $group): string
    {
        return self::rules()['job_level_labels'][$group] ?? $group;
    }

    public static function thresholds(): array
    {
        return self::rules()['fulfillment_thresholds'];
    }

    public static function statusFor(float $percentage): array
    {
        $buckets = self::thresholds();
        foreach ($buckets as $bucket) {
            $min = $bucket['min'];
            $max = $bucket['max'];
            if (($min === null || $percentage >= $min) && ($max === null || $percentage <= $max)) {
                return $bucket;
            }
        }
        return end($buckets);
    }
}
