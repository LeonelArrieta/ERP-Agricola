<?php

declare(strict_types=1);

namespace App\Domain\Facturacion;

use App\Core\HttpException;
use App\Infrastructure\Arca\ArcaCatalogs;

final class ValidadorFiscal
{
    /**
     * @return array{concepto:int,fch_serv_desde:?string,fch_serv_hasta:?string,fch_vto_pago:?string}
     */
    public function concepto(array $input): array
    {
        $concepto = $input['concepto'] ?? 1;
        if (!is_numeric($concepto) || !isset(ArcaCatalogs::CONCEPTOS[(int) $concepto])) {
            throw new HttpException('VALIDATION_ERROR', 'El concepto no es valido.', 422, ['field' => 'concepto']);
        }
        $concepto = (int) $concepto;
        if ($concepto === 1) {
            return ['concepto' => 1, 'fch_serv_desde' => null, 'fch_serv_hasta' => null, 'fch_vto_pago' => null];
        }

        $from = $this->fechaRequerida($input['fch_serv_desde'] ?? null, 'fch_serv_desde');
        $to = $this->fechaRequerida($input['fch_serv_hasta'] ?? null, 'fch_serv_hasta');
        $payment = $this->fechaRequerida($input['fch_vto_pago'] ?? null, 'fch_vto_pago');
        if ($from > $to) {
            throw new HttpException('VALIDATION_ERROR', 'El rango de fechas de servicio no es valido.', 422, ['field' => 'fch_serv_desde']);
        }

        return ['concepto' => $concepto, 'fch_serv_desde' => $from, 'fch_serv_hasta' => $to, 'fch_vto_pago' => $payment];
    }

    /**
     * @return array{doc_tipo:int,doc_nro:string,condicion_iva_receptor_id:int}
     */
    public function receptor(array $client, array $type, int $condicionEmisor): array
    {
        $docTipo = $client['doc_tipo'] !== null ? (int) $client['doc_tipo'] : null;
        $docNro = $client['doc_nro'] !== null ? trim((string) $client['doc_nro']) : '';
        $condicion = $client['condicion_iva_receptor_id'] !== null ? (int) $client['condicion_iva_receptor_id'] : null;

        if ($docTipo === null || !isset(ArcaCatalogs::DOC_TIPOS[$docTipo]) || $docNro === '') {
            throw new HttpException('VALIDATION_ERROR', 'El cliente no tiene tipo y numero de documento fiscal configurados.', 422, ['field' => 'doc_nro']);
        }
        if ($condicion === null || !isset(ArcaCatalogs::CONDICIONES_IVA[$condicion])) {
            throw new HttpException('VALIDATION_ERROR', 'El cliente no tiene condicion frente al IVA configurada.', 422, ['field' => 'condicion_iva_receptor_id']);
        }
        if (!ArcaCatalogs::condicionCompatible($condicion, (string) $type['clase'])) {
            throw new HttpException('VALIDATION_ERROR', 'La condicion IVA del receptor no es compatible con la clase del comprobante.', 422, ['field' => 'id_tipo_comprobante']);
        }
        if (!ArcaCatalogs::condicionEmisorPermite($condicionEmisor, (string) $type['clase'])) {
            throw new HttpException('VALIDATION_ERROR', 'La condicion del emisor no permite emitir esa clase de comprobante.', 422, ['field' => 'id_tipo_comprobante']);
        }

        return ['doc_tipo' => $docTipo, 'doc_nro' => $docNro, 'condicion_iva_receptor_id' => $condicion];
    }

    public function importeNota(mixed $value): float
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            throw new HttpException('VALIDATION_ERROR', 'El importe de la nota debe ser mayor a cero.', 422, ['field' => 'importe']);
        }

        return round((float) $value, 2);
    }

    public function fechaRequerida(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new HttpException('VALIDATION_ERROR', 'La fecha ' . $field . ' es obligatoria.', 422, ['field' => $field]);
        }
        $date = $this->fecha($value);
        if ($date === null) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha ' . $field . ' no es valida.', 422, ['field' => $field]);
        }

        return $date;
    }

    public function fecha(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return date('Y-m-d');
        }
        if (!is_string($value)) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422);
        }
        $date = date_create_from_format('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422);
        }

        return $value;
    }
}
