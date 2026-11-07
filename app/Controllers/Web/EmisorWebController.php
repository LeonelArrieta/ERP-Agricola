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
use App\Infrastructure\Arca\ArcaCatalogs;
use App\Services\EmisorService;

final class EmisorWebController
{
    public function __construct(
        private readonly EmisorService $emisor,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
    ) {
    }

    public function edit(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('facturacion.administrar');

        return $this->form($this->emisor->get(), [], null);
    }

    public function update(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('facturacion.administrar');
        $this->csrf->validate($this->token($request));

        try {
            $this->emisor->update($request->body, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [404, 409, 422], true)) {
                throw $exception;
            }

            return $this->form(array_merge($this->emisor->get(), $request->body), $this->errors($exception), $exception->getMessage());
        }

        $this->session->flash('success', 'Datos del emisor actualizados correctamente.');

        return new RedirectResponse(url('app/emisor'));
    }

    private function form(array $item, array $errors, ?string $error): HtmlResponse
    {
        return $this->render('emisor/form', [
            'title' => 'Datos del emisor | ERP Agricola',
            'active' => 'emisor',
            'item' => $item,
            'errors' => $errors,
            'error' => $error,
            'condiciones' => ArcaCatalogs::CONDICIONES_IVA,
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
