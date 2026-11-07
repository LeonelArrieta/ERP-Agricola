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
use App\Services\ExpenseService;

final class ExpenseWebController
{
    public function __construct(
        private readonly ExpenseService $expenses,
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
        $this->authorization->requirePermission('gastos.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'categoria' => QueryFilters::int($request->query['categoria'] ?? null, 'categoria'),
            'tipo_pago' => QueryFilters::enum($request->query['tipo_pago'] ?? null, ['Contado', 'Diferido'], 'tipo_pago'),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Pendiente', 'Pagado', 'Cancelado'], 'estado'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->expenses->list($page, $perPage, $filters);

        return $this->render('expenses/index', [
            'title' => 'Gastos | ERP Agricola',
            'active' => 'gastos',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'categories' => $this->expenses->categories(),
        ]);
    }

    public function create(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('gastos.crear');

        return $this->form([], null);
    }

    public function store(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('gastos.crear');
        $this->csrf->validate($this->token($request));

        try {
            $result = $this->expenses->create($request->body, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->form($request->body, $exception->getMessage(), $this->errors($exception));
        }

        $message = $result['tipo_pago'] === 'Contado'
            ? 'Gasto de contado registrado. Se generó el egreso de Caja.'
            : 'Gasto diferido registrado. Se generó la CxP pendiente de pago.';
        $this->session->flash('success', $message);

        return new RedirectResponse(url('app/gastos'));
    }

    private function form(array $values, ?string $error, array $errors = []): HtmlResponse
    {
        return $this->render('expenses/form', [
            'title' => 'Nuevo gasto | ERP Agricola',
            'active' => 'gastos',
            'values' => $values,
            'error' => $error,
            'errors' => $errors,
            'categories' => $this->expenses->categories(),
        ]);
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

    private function token(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        return is_string($token) ? $token : null;
    }
}
