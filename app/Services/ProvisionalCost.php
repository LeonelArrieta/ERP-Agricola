<?php

declare(strict_types=1);

namespace App\Services;

final class ProvisionalCost
{
    /**
     * Costo unitario provisional de un lote.
     *
     * Usa la produccion estimada del ciclo si existe; si no, la cantidad
     * cosechada hasta el momento. Representa el costo acumulado hasta la
     * cosecha, no el costo definitivo del ciclo.
     */
    public static function estimate(float $totalCost, ?float $estimatedQuantity, float $harvestedQuantity): float
    {
        $quantity = ($estimatedQuantity !== null && $estimatedQuantity > 0) ? $estimatedQuantity : $harvestedQuantity;
        if ($quantity <= 0 || $totalCost <= 0) {
            return 0.0;
        }

        return round($totalCost / $quantity, 2);
    }
}
