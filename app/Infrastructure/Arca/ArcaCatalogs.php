<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

final class ArcaCatalogs
{
    public const FACTURAS = [1, 6, 11];
    public const NOTAS_CREDITO = [2, 7, 12];
    public const NOTAS_DEBITO = [3, 8, 13];

    public const DOC_TIPOS = [
        80 => 'CUIT',
        86 => 'CUIL',
        87 => 'CDI',
        89 => 'LE',
        90 => 'LC',
        91 => 'CI extranjera',
        92 => 'En tramite',
        93 => 'Acta de nacimiento',
        94 => 'Pasaporte',
        95 => 'CI Bs. As. RNP',
        96 => 'DNI',
        97 => 'PEP',
        99 => 'Sin identificar / Consumidor Final',
    ];

    public const CONDICIONES_IVA = [
        1 => ['label' => 'IVA Responsable Inscripto', 'clases' => ['A', 'B', 'C']],
        4 => ['label' => 'IVA Sujeto Exento', 'clases' => ['B', 'C']],
        5 => ['label' => 'Consumidor Final', 'clases' => ['B', 'C']],
        6 => ['label' => 'Responsable Monotributo', 'clases' => ['A', 'B', 'C']],
        7 => ['label' => 'Sujeto No Categorizado', 'clases' => ['B', 'C']],
        8 => ['label' => 'Proveedor del Exterior', 'clases' => ['B', 'C']],
        9 => ['label' => 'Cliente del Exterior', 'clases' => ['B', 'C']],
        10 => ['label' => 'IVA Liberado Ley 19.640', 'clases' => ['B', 'C']],
        13 => ['label' => 'Monotributista Social', 'clases' => ['A', 'B', 'C']],
        15 => ['label' => 'IVA No Alcanzado', 'clases' => ['B', 'C']],
        16 => ['label' => 'Monotributo Trabajador Independiente Promovido', 'clases' => ['A', 'B', 'C']],
    ];

    public const CONCEPTOS = [
        1 => 'Productos',
        2 => 'Servicios',
        3 => 'Productos y servicios',
    ];

    public const ALICUOTA_A_ID = [
        '10.50' => 4,
        '21.00' => 5,
        '27.00' => 6,
    ];

    public const ALICUOTA_POR_ID = [
        4 => '10,50',
        5 => '21,00',
        6 => '27,00',
    ];

    public static function alicuotaId(float $rate): ?int
    {
        return self::ALICUOTA_A_ID[number_format($rate, 2, '.', '')] ?? null;
    }

    public static function condicionCompatible(int $condicionId, string $clase): bool
    {
        $condition = self::CONDICIONES_IVA[$condicionId] ?? null;
        if ($condition === null) {
            return false;
        }

        return in_array($clase, $condition['clases'], true);
    }

    public static function condicionEmisorPermite(int $condicionEmisor, string $clase): bool
    {
        return match ($condicionEmisor) {
            1 => in_array($clase, ['A', 'B', 'C'], true),
            6, 4, 7, 13, 16 => $clase === 'C',
            default => $clase === 'C',
        };
    }

    public static function esFactura(int $tipo): bool
    {
        return in_array($tipo, self::FACTURAS, true);
    }

    public static function esNota(int $tipo): bool
    {
        return in_array($tipo, self::NOTAS_CREDITO, true) || in_array($tipo, self::NOTAS_DEBITO, true);
    }
}
