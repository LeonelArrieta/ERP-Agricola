<?php

declare(strict_types=1);

namespace App\Domain\Facturacion;

use App\Core\HttpException;

/**
 * Construye el contenido del codigo QR exigido por ARCA (RG 4892/2020).
 * No conoce ninguna biblioteca de representacion grafica: solo el texto a codificar.
 */
final class QrFiscal
{
    private const URL = 'https://www.afip.gob.ar/fe/qr/';

    public function payload(array $comprobante): string
    {
        if (($comprobante['numero'] ?? null) === null || ($comprobante['cae'] ?? null) === null) {
            throw new HttpException('CONFLICT', 'El comprobante no tiene numero y CAE para generar el QR.', 409);
        }

        $data = [
            'ver' => 1,
            'fecha' => (string) $comprobante['fecha'],
            'cuit' => (int) $comprobante['emisor_cuit'],
            'ptoVta' => (int) $comprobante['punto_venta_numero'],
            'tipoCmp' => (int) $comprobante['id_tipo_comprobante'],
            'nroCmp' => (int) $comprobante['numero'],
            'importe' => round((float) $comprobante['imp_total'], 2),
            'moneda' => (string) ($comprobante['moneda'] ?? 'PES'),
            'ctz' => round((float) ($comprobante['cotizacion'] ?? 1), 6),
            'tipoDocRec' => (int) $comprobante['doc_tipo'],
            'nroDocRec' => (int) $comprobante['doc_nro'],
            'tipoCodAut' => 'E',
            'codAut' => (int) $comprobante['cae'],
        ];

        // serialize_precision puede venir en 17 (cPanel): forzar la representacion
        // decimal corta para que "importe" y "ctz" cumplan la especificacion de ARCA.
        $previous = ini_set('serialize_precision', '-1');
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', (string) $previous);
            }
        }

        return self::URL . '?p=' . base64_encode($json);
    }
}
