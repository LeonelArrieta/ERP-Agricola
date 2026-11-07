<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HtmlResponse;
use App\Core\Navigation;
use App\Core\QueryFilters;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Services\FinanceService;

final class FinanceWebController
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
    ) {
    }

    public function receivables(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('cobranzas.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Pendiente', 'Parcialmente_Cobrada', 'Cobrada', 'Cancelada'], 'estado'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->finance->receivables($page, $perPage, $filters);

        return $this->render('finance/receivables', [
            'title' => 'Cuentas por cobrar | ERP Agricola',
            'active' => 'cxc',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function payables(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('pagos.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Pendiente', 'Parcialmente_Pagada', 'Pagada', 'Cancelada'], 'estado'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->finance->payables($page, $perPage, $filters);

        return $this->render('finance/payables', [
            'title' => 'Cuentas por pagar | ERP Agricola',
            'active' => 'cxp',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function cash(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('caja.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'tipo' => QueryFilters::enum($request->query['tipo'] ?? null, ['Ingreso', 'Egreso', 'ReversionIngreso', 'ReversionEgreso'], 'tipo'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->finance->cashMovements($page, $perPage, $filters);

        return $this->render('finance/cash', [
            'title' => 'Caja | ERP Agricola',
            'active' => 'caja',
            'balance' => $this->finance->cashBalance(),
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
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
}
