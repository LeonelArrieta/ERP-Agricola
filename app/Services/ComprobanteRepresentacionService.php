<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\ViewRenderer;
use App\Domain\Facturacion\QrFiscal;
use App\Infrastructure\Arca\ArcaCatalogs;
use App\Infrastructure\Pdf\ComprobantePdfRenderer;
use App\Infrastructure\Qr\QrSvgRenderer;
use App\Repositories\ContactRepository;
use App\Repositories\FiscalRepository;
use App\Repositories\SaleRepository;

final class ComprobanteRepresentacionService
{
    public function __construct(
        private readonly FiscalRepository $fiscal,
        private readonly SaleRepository $sales,
        private readonly ContactRepository $clients,
        private readonly QrFiscal $qr,
        private readonly QrSvgRenderer $qrSvg,
        private readonly ComprobantePdfRenderer $pdf,
        private readonly ViewRenderer $views,
        private readonly string $cssPath,
    ) {
    }

    public function viewData(int $id): array
    {
        $voucher = $this->fiscal->voucher($id);
        if ($voucher === null) {
            throw new HttpException('NOT_FOUND', 'El comprobante fiscal no existe.', 404);
        }
        if ((string) $voucher['estado_fiscal'] !== 'Autorizado') {
            throw new HttpException('CONFLICT', 'Solo se representan comprobantes autorizados.', 409);
        }

        $sale = $this->sales->find((int) $voucher['id_venta']);
        $client = $sale !== null ? $this->clients->find((int) $sale['id_cliente']) : null;

        $incomplete = trim((string) ($voucher['emisor_razon_social'] ?? '')) === '';
        $emisor = $incomplete ? null : [
            'razon_social' => (string) $voucher['emisor_razon_social'],
            'cuit' => (string) ($voucher['emisor_cuit'] ?? ''),
            'domicilio' => (string) ($voucher['emisor_domicilio'] ?? ''),
            'condicion_iva' => (int) ($voucher['emisor_condicion_iva'] ?? 0),
            'ingresos_brutos' => $voucher['emisor_ingresos_brutos'] !== null ? (string) $voucher['emisor_ingresos_brutos'] : null,
            'inicio_actividades' => $voucher['emisor_inicio_actividades'] !== null ? (string) $voucher['emisor_inicio_actividades'] : null,
        ];

        $qrSvg = null;
        if (!$incomplete && $voucher['numero'] !== null && $voucher['cae'] !== null) {
            $qrSvg = $this->qrSvg->render($this->qr->payload($voucher));
        }

        return [
            'voucher' => $voucher,
            'iva' => $this->fiscal->iva($id),
            'details' => $this->sales->details((int) $voucher['id_venta']),
            'client' => $client,
            'emisor' => $emisor,
            'incomplete' => $incomplete,
            'qrSvg' => $qrSvg,
            'condiciones' => ArcaCatalogs::CONDICIONES_IVA,
            'docTipos' => ArcaCatalogs::DOC_TIPOS,
            'alicuotas' => ArcaCatalogs::ALICUOTA_POR_ID,
        ];
    }

    public function html(int $id): string
    {
        return $this->views->render('billing/print', $this->viewData($id) + ['qrAsImage' => false], 'print');
    }

    public function pdf(int $id): string
    {
        $data = $this->viewData($id);
        $body = $this->views->partial('billing/print', $data + ['qrAsImage' => true]);
        $css = is_file($this->cssPath) ? (string) file_get_contents($this->cssPath) : '';
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $body . '</body></html>';

        return $this->pdf->render($html);
    }

    public function filename(array $voucher): string
    {
        $clase = (string) ($voucher['clase'] ?? '');
        $pv = (int) $voucher['punto_venta_numero'];
        $numero = str_pad((string) ((int) ($voucher['numero'] ?? 0)), 8, '0', STR_PAD_LEFT);

        return 'Comprobante-' . $clase . '-PV' . $pv . '-' . $numero . '.pdf';
    }
}
