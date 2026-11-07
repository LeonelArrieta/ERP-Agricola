<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

final class ArcaRequestMapper
{
    /**
     * Traduce un comprobante interno al request normalizado de FECAESolicitar.
     *
     * @param array $voucher fila de comprobantes_fiscales con punto_venta_numero
     * @param array $iva filas de comprobante_iva
     */
    public function fromComprobante(array $voucher, array $iva, int $cuit): array
    {
        $associated = null;
        if (($voucher['id_comprobante_asociado'] ?? null) !== null) {
            $associated = [
                'tipo' => (int) $voucher['asoc_tipo'],
                'pto_vta' => (int) $voucher['asoc_pto_vta'],
                'nro' => (int) $voucher['asoc_nro'],
                'cuit' => (string) ($voucher['asoc_cuit'] ?? ''),
                'fecha' => $this->arcaDate((string) $voucher['asoc_fecha']),
            ];
        }

        $rates = [];
        foreach ($iva as $row) {
            $rates[] = [
                'id' => (int) $row['alic_iva_id'],
                'base' => (float) $row['base_imp'],
                'importe' => (float) $row['importe'],
            ];
        }

        return [
            'cuit' => $cuit,
            'pto_vta' => (int) $voucher['punto_venta_numero'],
            'cbte_tipo' => (int) $voucher['id_tipo_comprobante'],
            'concepto' => (int) $voucher['concepto'],
            'doc_tipo' => (int) $voucher['doc_tipo'],
            'doc_nro' => (string) $voucher['doc_nro'],
            'cbte_desde' => (int) $voucher['numero'],
            'cbte_hasta' => (int) $voucher['numero'],
            'cbte_fch' => $this->arcaDate((string) $voucher['fecha']),
            'imp_total' => (float) $voucher['imp_total'],
            'imp_tot_conc' => (float) $voucher['imp_tot_conc'],
            'imp_neto' => (float) $voucher['imp_neto'],
            'imp_op_ex' => (float) $voucher['imp_op_ex'],
            'imp_iva' => (float) $voucher['imp_iva'],
            'imp_trib' => (float) $voucher['imp_trib'],
            'mon_id' => (string) $voucher['moneda'],
            'mon_cotiz' => (float) $voucher['cotizacion'],
            'condicion_iva_receptor_id' => (int) $voucher['condicion_iva_receptor_id'],
            'fch_serv_desde' => $voucher['fch_serv_desde'] !== null ? $this->arcaDate((string) $voucher['fch_serv_desde']) : null,
            'fch_serv_hasta' => $voucher['fch_serv_hasta'] !== null ? $this->arcaDate((string) $voucher['fch_serv_hasta']) : null,
            'fch_vto_pago' => $voucher['fch_vto_pago'] !== null ? $this->arcaDate((string) $voucher['fch_vto_pago']) : null,
            'iva' => $rates,
            'asoc' => $associated,
        ];
    }

    private function arcaDate(string $date): string
    {
        return str_replace('-', '', $date);
    }
}
