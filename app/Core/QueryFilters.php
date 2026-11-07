<?php

declare(strict_types=1);

namespace App\Core;

final class QueryFilters
{
    public static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    public static function enum(mixed $value, array $allowed, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new HttpException('VALIDATION_ERROR', 'El filtro ' . $field . ' no es valido.', 422);
        }

        return $value;
    }

    public static function date(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha ' . $field . ' no es valida.', 422);
        }
        $date = date_create_from_format('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha ' . $field . ' no es valida.', 422);
        }

        return $value;
    }

    public static function dateRange(array $query, string $fromField = 'from', string $toField = 'to'): array
    {
        $from = self::date($query[$fromField] ?? null, $fromField);
        $to = self::date($query[$toField] ?? null, $toField);
        if ($from !== null && $to !== null && $from > $to) {
            throw new HttpException('VALIDATION_ERROR', 'El rango de fechas no es valido.', 422);
        }

        return ['from' => $from, 'to' => $to];
    }

    public static function int(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (int) $value < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El filtro ' . $field . ' no es valido.', 422);
        }

        return (int) $value;
    }
}
