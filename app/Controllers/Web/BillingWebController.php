<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\FileResponse;
use App\Core\HtmlResponse;
use App\Core\HttpException;
use App\Core\Navigation;
use App\Core\QueryFilters;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Infrastructure\Arca\ArcaCatalogs;
use App\Services\ComprobanteRepresentacionService;
use App\Services\FacturacionService;
use App\Services\PointOfSaleService;
use App\Services\SaleService;

final class BillingWebController
{
    public function __construct(
        private readonly FacturacionService $billing,
        private readonly SaleService $sales,
        private readonly PointOfSaleService $points,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
        private readonly ComprobanteRepresentacionService $representation,
    ) {
    }

    public function index(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado_fiscal' => QueryFilters::enum($request->query['estado_fiscal'] ?? null, ['Pendiente', 'Enviando', 'Autorizado', 'Rechazado', 'ErrorComunicacion', 'ResultadoIncierto', 'RequiereRevision'], 'estado_fiscal'),
            'tipo' => QueryFilters::int($request->query['tipo'] ?? null, 'tipo'),
            'punto_venta' => QueryFilters::int($request->query['punto_venta'] ?? null, 'punto_venta'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->billing->list($page, $perPage, $filters);

        return $this->render('billing/index', [
            'title' => 'Comprobantes fiscales | ERP Agricola',
            'active' => 'comprobantes',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'types' => $this->billing->types(),
            'points' => $this->points->options(),
        ]);
    }

    public function show(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.ver');
        $id = $this->id($request);
        $data = $this->billing->get($id);

        return $this->render('billing/show', [
            'title' => 'Comprobante fiscal | ERP Agricola',
            'active' => 'comprobantes',
            'item' => $data['comprobante'],
            'iva' => $data['iva'],
            'asociado' => $data['asociado'],
            'attempts' => $this->billing->attempts($id),
            'types' => $this->billing->types(),
            'points' => $this->points->options(),
        ]);
    }

    public function print(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.ver');

        return new HtmlResponse($this->representation->html($this->id($request)));
    }

    public function pdf(Request $request): FileResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.ver');
        $id = $this->id($request);
        $voucher = $this->billing->get($id)['comprobante'];
        $download = ($request->query['descargar'] ?? null) === '1';

        return new FileResponse($this->representation->pdf($id), 'application/pdf', $this->representation->filename($voucher), $download);
    }

    public function invoiceForm(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.emitir');
        $saleId = $this->id($request);
        $data = $this->sales->get($saleId);
        if ($this->hasAuthorizedInvoice($saleId)) {
            $this->session->flash('error', 'La venta ya tiene un comprobante autorizado.');

            return new RedirectResponse(url('app/ventas/' . $saleId));
        }

        return $this->invoiceView($saleId, $data['venta'], [], [], null);
    }

    public function invoiceStore(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('facturacion.emitir');
        $this->csrf->validate($this->token($request));
        $saleId = $this->id($request);

        try {
            $result = $this->billing->emitInvoice($saleId, $request->body, $actor);
        } catch (HttpException $exception) {
            if ($exception->status === 422) {
                return $this->invoiceView($saleId, $this->sales->get($saleId)['venta'], $request->body, $this->errors($exception), $exception->getMessage());
            }
            $this->flashException($exception);

            return new RedirectResponse(url('app/ventas/' . $saleId));
        }

        $this->session->flash('success', 'Comprobante emitido. Estado fiscal: ' . $result['comprobante']['estado_fiscal'] . '.');

        return new RedirectResponse(url('app/comprobantes/' . (int) $result['comprobante']['id_comprobante_fiscal']));
    }

    public function noteForm(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.notas');
        $id = $this->id($request);
        $data = $this->billing->get($id);

        return $this->noteView($id, $data['comprobante'], [], [], null);
    }

    public function noteStore(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('facturacion.notas');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->billing->emitNote($id, $request->body, $actor);
        } catch (HttpException $exception) {
            if ($exception->status === 422) {
                return $this->noteView($id, $this->billing->get($id)['comprobante'], $request->body, $this->errors($exception), $exception->getMessage());
            }
            $this->flashException($exception);

            return new RedirectResponse(url('app/comprobantes/' . $id));
        }

        $this->session->flash('success', 'Nota emitida. Estado fiscal: ' . $result['comprobante']['estado_fiscal'] . '.');

        return new RedirectResponse(url('app/comprobantes/' . (int) $result['comprobante']['id_comprobante_fiscal']));
    }

    public function reconcile(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('facturacion.emitir');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->billing->reconcile($id, $actor);
            $this->session->flash('success', 'Reconciliación completada. Estado fiscal: ' . $result['comprobante']['estado_fiscal'] . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/comprobantes/' . $id));
    }

    public function retry(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('facturacion.emitir');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->billing->retry($id, $actor);
            $this->session->flash('success', 'Reintento completado. Estado fiscal: ' . $result['comprobante']['estado_fiscal'] . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/comprobantes/' . $id));
    }

    private function invoiceView(int $saleId, array $sale, array $values, array $errors, ?string $error): HtmlResponse
    {
        return $this->render('billing/invoice', [
            'title' => 'Emitir comprobante | ERP Agricola',
            'active' => 'ventas',
            'sale' => $sale,
            'saleId' => $saleId,
            'values' => $values,
            'errors' => $errors,
            'error' => $error,
            'defaults' => $this->billing->invoiceDefaults($saleId),
            'types' => array_values(array_filter($this->billing->types(), static fn (array $type): bool => in_array((int) $type['id_tipo_comprobante'], [1, 6, 11], true))),
            'points' => $this->points->options(),
        ]);
    }

    private function noteView(int $voucherId, array $original, array $values, array $errors, ?string $error): HtmlResponse
    {
        $class = (string) ($original['clase'] ?? '');
        $noteTypes = array_merge(ArcaCatalogs::NOTAS_CREDITO, ArcaCatalogs::NOTAS_DEBITO);

        return $this->render('billing/note', [
            'title' => 'Emitir nota | ERP Agricola',
            'active' => 'comprobantes',
            'original' => $original,
            'voucherId' => $voucherId,
            'values' => $values,
            'errors' => $errors,
            'error' => $error,
            'types' => array_values(array_filter($this->billing->types(), static fn (array $type): bool => in_array((int) $type['id_tipo_comprobante'], $noteTypes, true) && (string) $type['clase'] === $class)),
            'points' => $this->points->options(),
        ]);
    }

    private function hasAuthorizedInvoice(int $saleId): bool
    {
        foreach ($this->billing->vouchersBySale($saleId) as $voucher) {
            if ((string) $voucher['estado_fiscal'] === 'Autorizado' && in_array((int) $voucher['id_tipo_comprobante'], [1, 6, 11], true)) {
                return true;
            }
        }

        return false;
    }

    private function flashException(HttpException $exception): void
    {
        if (!in_array($exception->status, [404, 409, 422], true)) {
            throw $exception;
        }
        $this->session->flash('error', $exception->getMessage());
    }

    private function errors(HttpException $exception): array
    {
        $field = $exception->details['field'] ?? null;

        return is_string($field) ? [$field => $exception->getMessage()] : ['_form' => $exception->getMessage()];
    }

    private function render(string $view, array $data): HtmlResponse
    {
        $permissions = $this->authorization->permissions();

        return new HtmlResponse($this->views->render($view, $data + [
            'user' => $this->authorization->user(),
            'permissions' => $permissions,
            'navigation' => Navigation::groups($permissions),
            'csrfToken' => $this->csrf->token(),
            'flashes' => $this->session->pullFlashes(),
        ]));
    }

    private function redirectIfAnonymous(): ?RedirectResponse
    {
        return $this->session->userId() === null ? new RedirectResponse(url('login')) : null;
    }

    private function id(Request $request): int
    {
        $id = $request->params['id'] ?? null;
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El identificador no es valido.', 422);
        }

        return (int) $id;
    }

    private function token(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        return is_string($token) ? $token : null;
    }
}
