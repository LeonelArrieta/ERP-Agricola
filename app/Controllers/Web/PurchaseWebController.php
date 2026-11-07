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
use App\Repositories\UnitRepository;
use App\Services\ContactService;
use App\Services\FinanceService;
use App\Services\InventoryCatalogService;
use App\Services\PurchaseCancellationService;
use App\Services\PurchaseService;

final class PurchaseWebController
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly PurchaseCancellationService $cancellations,
        private readonly FinanceService $finance,
        private readonly ContactService $suppliers,
        private readonly InventoryCatalogService $catalog,
        private readonly UnitRepository $units,
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
        $this->authorization->requirePermission('compras.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Presupuesto', 'Pendiente', 'Facturado', 'Pagado', 'Cancelado'], 'estado'),
            'tipo_pago' => QueryFilters::enum($request->query['tipo_pago'] ?? null, ['Contado', 'Credito'], 'tipo_pago'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->purchases->list($page, $perPage, $filters);

        return $this->render('purchases/index', [
            'title' => 'Compras | ERP Agricola',
            'active' => 'compras',
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
        $this->authorization->requirePermission('compras.crear');

        return $this->form([], null);
    }

    public function store(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('compras.crear');
        $this->csrf->validate($this->token($request));

        try {
            $result = $this->purchases->create($request->body, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->form($request->body, $exception->getMessage(), $this->errors($exception));
        }

        $this->session->flash('success', 'Compra registrada correctamente. Total: $' . number_format((float) $result['total'], 2, ',', '.') . '.');

        return new RedirectResponse(url('app/compras/' . (int) $result['id_compra']));
    }

    public function show(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('compras.ver');
        $id = $this->id($request);
        $data = $this->purchases->get($id);

        return $this->render('purchases/show', [
            'title' => 'Compra #' . $id . ' | ERP Agricola',
            'active' => 'compras',
            'purchase' => $data['compra'],
            'details' => $data['detalles'],
            'cxp' => $data['cxp'],
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('compras.cancelar');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->cancellations->cancel($id, $actor);
            $this->session->flash('success', 'Compra cancelada. Inventario, CxP y Caja fueron revertidos según los efectos reales.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/compras/' . $id));
    }

    public function pay(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('pagos.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->finance->pay($id, $request->body, $actor);
            $this->session->flash('success', 'Pago registrado. Saldo pendiente: $' . number_format((float) $result['saldo_actual'], 2, ',', '.') . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/compras/' . $id));
    }

    private function form(array $values, ?string $error, array $errors = []): HtmlResponse
    {
        return $this->render('purchases/form', [
            'title' => 'Nueva compra | ERP Agricola',
            'active' => 'compras',
            'values' => $values,
            'error' => $error,
            'errors' => $errors,
            'suppliers' => $this->suppliers->list(1, 100, true, '')['items'],
            'insumos' => $this->catalog->list('insumo', 1, 100, true, '')['items'],
            'units' => $this->units->activeOptions(),
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
