<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HtmlResponse;
use App\Core\HttpException;
use App\Core\Navigation;
use App\Core\QueryFilters;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Repositories\FiscalRepository;
use App\Services\CancellationService;
use App\Services\ContactService;
use App\Services\FinanceService;
use App\Services\SaleService;

final class SaleWebController
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly CancellationService $cancellations,
        private readonly FinanceService $finance,
        private readonly ContactService $clients,
        private readonly FiscalRepository $fiscal,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
    ) {
    }

    public function index(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('ventas.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Presupuesto', 'Pendiente', 'Facturado', 'Cobrado', 'Cancelado'], 'estado'),
            'tipo_pago' => QueryFilters::enum($request->query['tipo_pago'] ?? null, ['Contado', 'Credito'], 'tipo_pago'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->sales->list($page, $perPage, $filters);

        return $this->render('sales/index', [
            'title' => 'Ventas | ERP Agricola',
            'active' => 'ventas',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('ventas.crear');

        return $this->form([], null);
    }

    public function store(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('ventas.crear');
        $this->csrf->validate($this->token($request));
        $input = $this->normalize($request->body);

        try {
            $result = $this->sales->create($input, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->form($request->body, $exception->getMessage(), $this->errors($exception));
        }

        $this->session->flash('success', 'Venta registrada correctamente. Total: $' . number_format((float) $result['total'], 2, ',', '.') . '.');

        return new RedirectResponse(url('app/ventas/' . (int) $result['id_venta']));
    }

    public function show(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('ventas.ver');
        $id = $this->id($request);
        $data = $this->sales->get($id);

        return $this->render('sales/show', [
            'title' => 'Venta #' . $id . ' | ERP Agricola',
            'active' => 'ventas',
            'sale' => $data['venta'],
            'details' => $data['detalles'],
            'cxc' => $data['cxc'],
            'vouchers' => $this->fiscal->vouchersBySale($id),
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('ventas.cancelar');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->cancellations->sale($id, $actor);
            $this->session->flash('success', 'Venta cancelada. Lote, CxC y Caja fueron revertidos según los efectos reales.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ventas/' . $id));
    }

    public function receive(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('cobranzas.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->finance->receive($id, $request->body, $actor);
            $this->session->flash('success', 'Cobro registrado. Saldo pendiente: $' . number_format((float) $result['saldo_actual'], 2, ',', '.') . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ventas/' . $id));
    }

    private function normalize(array $input): array
    {
        if (!is_array($input['detalles'] ?? null)) {
            return $input;
        }

        foreach ($input['detalles'] as $index => $detail) {
            if (!is_array($detail)) {
                continue;
            }
            $reference = (string) ($detail['producto_lote'] ?? '');
            if (str_contains($reference, ':')) {
                [$product, $lot] = explode(':', $reference, 2);
                $input['detalles'][$index]['id_producto'] = $product;
                $input['detalles'][$index]['id_lote_produccion'] = $lot;
            }
            if (isset($detail['margen_porcentaje']) && is_numeric($detail['margen_porcentaje'])) {
                $input['detalles'][$index]['porcentaje_rentabilidad'] = (float) $detail['margen_porcentaje'] / 100;
            }
        }

        return $input;
    }

    private function form(array $values, ?string $error, array $errors = []): HtmlResponse
    {
        return $this->render('sales/form', [
            'title' => 'Nueva venta | ERP Agricola',
            'active' => 'ventas',
            'values' => $values,
            'error' => $error,
            'errors' => $errors,
            'clients' => $this->clients->list(1, 100, true, '')['items'],
            'lots' => $this->sales->lots(),
        ]);
    }

    private function errors(HttpException $exception): array
    {
        $field = $exception->details['field'] ?? null;

        return is_string($field) ? [$field => $exception->getMessage()] : ['_form' => $exception->getMessage()];
    }

    private function flashException(HttpException $exception): void
    {
        if (!in_array($exception->status, [404, 409, 422], true)) {
            throw $exception;
        }
        $this->session->flash('error', $exception->getMessage());
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
