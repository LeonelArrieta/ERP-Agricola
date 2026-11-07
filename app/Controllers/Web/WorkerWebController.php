<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HtmlResponse;
use App\Core\HttpException;
use App\Core\Navigation;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Services\WorkerService;

final class WorkerWebController
{
    public function __construct(
        private readonly WorkerService $workers,
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
        $this->authorization->requirePermission('produccion.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $active = null;
        if (isset($request->query['activo']) && in_array($request->query['activo'], ['0', '1'], true)) {
            $active = $request->query['activo'] === '1';
        }
        $search = is_string($request->query['search'] ?? null) ? trim($request->query['search']) : '';
        $result = $this->workers->list($page, $perPage, $active, $search);

        return $this->render('workers/index', [
            'title' => 'Trabajadores | ERP Agricola',
            'active' => 'trabajadores',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => ['search' => $search, 'activo' => (string) ($request->query['activo'] ?? '')],
        ]);
    }

    public function create(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.crear');

        return $this->form('create', [], [], null);
    }

    public function store(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));

        try {
            $this->workers->save($request->body, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->form('create', $request->body, $this->errors($exception), $exception->getMessage());
        }

        $this->session->flash('success', 'Trabajador creado correctamente.');

        return new RedirectResponse(url('app/trabajadores'));
    }

    public function show(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.ver');

        return $this->render('workers/show', [
            'title' => 'Trabajador | ERP Agricola',
            'active' => 'trabajadores',
            'item' => $this->workers->get($this->id($request)),
        ]);
    }

    public function edit(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.crear');

        return $this->form('edit', $this->workers->get($this->id($request)), [], null);
    }

    public function update(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->workers->save($request->body, $actor, $id);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->form('edit', array_merge($this->workers->get($id), $request->body), $this->errors($exception), $exception->getMessage());
        }

        $this->session->flash('success', 'Trabajador actualizado correctamente.');

        return new RedirectResponse(url('app/trabajadores/' . $id));
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->workers->deactivate($id, $actor);
            $this->session->flash('success', 'Trabajador desactivado correctamente.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/trabajadores/' . $id));
    }

    public function activate(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->workers->activate($id, $actor);
            $this->session->flash('success', 'Trabajador activado correctamente.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/trabajadores/' . $id));
    }

    private function form(string $mode, array $item, array $errors, ?string $error): HtmlResponse
    {
        return $this->render('workers/form', [
            'title' => ($mode === 'edit' ? 'Editar trabajador' : 'Nuevo trabajador') . ' | ERP Agricola',
            'active' => 'trabajadores',
            'mode' => $mode,
            'item' => $item,
            'errors' => $errors,
            'error' => $error,
        ]);
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

    private function errors(HttpException $exception): array
    {
        $field = $exception->details['field'] ?? null;

        return is_string($field) ? [$field => $exception->getMessage()] : ['_form' => $exception->getMessage()];
    }
}
