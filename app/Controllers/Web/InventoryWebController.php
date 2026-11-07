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
use App\Services\InventoryCatalogService;
use App\Services\InventoryService;

final class InventoryWebController
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryCatalogService $catalog,
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
        $this->authorization->requirePermission('inventario.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'tipo' => QueryFilters::enum($request->query['tipo'] ?? null, ['EntradaCompra', 'EntradaCosecha', 'SalidaVenta', 'SalidaConsumo', 'SalidaPorMerma', 'ReversionEntrada', 'ReversionSalida'], 'tipo'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->inventory->movements($page, $perPage, $filters);

        return $this->render('inventory/index', [
            'title' => 'Inventario | ERP Agricola',
            'active' => 'inventario',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function mermaForm(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('inventario.merma');

        return $this->mermaResponse([], null);
    }

    public function mermaStore(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('inventario.merma');
        $this->csrf->validate($this->token($request));

        try {
            $this->inventory->merma($request->body, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->mermaResponse($request->body, $exception->getMessage(), $this->errors($exception));
        }

        $this->session->flash('success', 'Merma registrada correctamente. El stock fue actualizado y el movimiento quedó auditado.');

        return new RedirectResponse(url('app/inventario'));
    }

    private function mermaResponse(array $values, ?string $error, array $errors = []): HtmlResponse
    {
        return $this->render('inventory/merma', [
            'title' => 'Registrar merma | ERP Agricola',
            'active' => 'inventario',
            'insumos' => $this->catalog->list('insumo', 1, 100, true, '')['items'],
            'values' => $values,
            'error' => $error,
            'errors' => $errors,
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
