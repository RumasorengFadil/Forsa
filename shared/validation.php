<?php

declare(strict_types=1);

function normalize_blank(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trimmed = trim(preg_replace('/\s+/', ' ', $value));
    if ($trimmed === '' || $trimmed === '-') {
        return null;
    }
    return $trimmed;
}

function to_int_or_zero(mixed $value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_numeric($value)) {
        return (int) round((float) $value);
    }
    return 0;
}
