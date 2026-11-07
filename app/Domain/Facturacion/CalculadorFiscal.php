<?php

declare(strict_types=1);

namespace App\Domain\Facturacion;

use App\Core\HttpException;
use App\Infrastructure\Arca\ArcaCatalogs;

final class CalculadorFiscal
{
    /**
     * Calcula neto, IVA, total y el desglose AlicIva en centavos.
     *
     * @return array{0:float,1:float,2:float,3:array<int,array{id:int,base:float,importe:float}>}
     */
    public function forInvoice(array $sale, array $details, array $type): array
    {
        $totalCents = $this->cents((float) $sale['total']);
        if ((int) $type['discrimina_iva'] !== 1) {
            return [$this->amount($totalCents), 0.0, $this->amount($totalCents), []];
        }

        $netoCents = $this->cents((float) $sale['subtotal']);
        $ivaCents = $this->cents((float) $sale['importe_alicuota']);
        $groups = [];
        foreach ($details as $detail) {
            $rateId = ArcaCatalogs::alicuotaId((float) $detail['porcentaje_alicuota']);
            if ($rateId === null) {
                throw new HttpException('CONFLICT', 'La venta tiene una alicuota de IVA no soportada por ARCA.', 409);
            }
            $groups[$rateId]['base'] = ($groups[$rateId]['base'] ?? 0) + $this->cents((float) $detail['subtotal']);
            $groups[$rateId]['importe'] = ($groups[$rateId]['importe'] ?? 0) + $this->cents((float) $detail['importe_alicuota']);
        }

        $rows = [];
        foreach ($groups as $rateId => $values) {
            $rows[] = ['id' => (int) $rateId, 'base' => $values['base'], 'importe' => $values['importe']];
        }
        $rows = $this->balance($rows, $netoCents, $ivaCents);
        $this->assertTotals($netoCents, $ivaCents, $rows);

        return [$this->amount($netoCents), $this->amount($ivaCents), $this->amount($totalCents), $this->present($rows)];
    }

    /**
     * Calcula neto, IVA y desglose de una nota a partir del comprobante original.
     *
     * @return array{0:float,1:float,2:array<int,array{id:int,base:float,importe:float}>}
     */
    public function forNote(array $original, array $originalRows, float $total): array
    {
        $totalCents = $this->cents($total);
        if ((int) $original['discrimina_iva'] !== 1) {
            return [$this->amount($totalCents), 0.0, []];
        }

        $originalNetoCents = $this->cents((float) $original['imp_neto']);
        $originalIvaCents = $this->cents((float) $original['imp_iva']);
        $ratio = $originalNetoCents > 0 ? $originalIvaCents / $originalNetoCents : 0.0;
        $netoCents = (int) round($totalCents / (1 + $ratio));
        $ivaCents = $totalCents - $netoCents;

        $rows = [];
        foreach ($originalRows as $row) {
            $rowBase = $this->cents((float) $row['base_imp']);
            $rowImporte = $this->cents((float) $row['importe']);
            $base = $originalNetoCents > 0 ? (int) round($netoCents * ($rowBase / $originalNetoCents)) : 0;
            $importe = $rowBase > 0 ? (int) round($base * ($rowImporte / $rowBase)) : 0;
            $rows[] = ['id' => (int) $row['alic_iva_id'], 'base' => $base, 'importe' => $importe];
        }
        $rows = $this->balance($rows, $netoCents, $ivaCents);
        $this->assertTotals($netoCents, $ivaCents, $rows);

        return [$this->amount($netoCents), $this->amount($ivaCents), $this->present($rows)];
    }

    private function balance(array $rows, int $netoCents, int $ivaCents): array
    {
        if ($rows === []) {
            return [];
        }
        $last = count($rows) - 1;
        $rows[$last]['base'] += $netoCents - array_sum(array_column($rows, 'base'));
        $rows[$last]['importe'] += $ivaCents - array_sum(array_column($rows, 'importe'));

        return $rows;
    }

    private function assertTotals(int $netoCents, int $ivaCents, array $rows): void
    {
        $baseSum = array_sum(array_column($rows, 'base'));
        $ivaSum = array_sum(array_column($rows, 'importe'));
        if (abs($baseSum - $netoCents) > 1 || abs($ivaSum - $ivaCents) > 1) {
            throw new HttpException('CONFLICT', 'Los importes de IVA no cierran con los totales de la venta.', 409);
        }
    }

    /**
     * @param array<int,array{id:int,base:int,importe:int}> $rows
     * @return array<int,array{id:int,base:float,importe:float}>
     */
    private function present(array $rows): array
    {
        return array_map(
            fn (array $row): array => ['id' => $row['id'], 'base' => $this->amount($row['base']), 'importe' => $this->amount($row['importe'])],
            $rows
        );
    }

    private function cents(float $value): int
    {
        return (int) round($value * 100);
    }

    private function amount(int $cents): float
    {
        return $cents / 100;
    }
}
