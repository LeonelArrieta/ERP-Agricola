<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\RedirectResponse;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
    ) {
    }

    public function csrf(Request $request): Response
    {
        return new Response(['token' => $this->csrf->token()]);
    }

    public function login(Request $request): Response|RedirectResponse
    {
        $html = ($request->body['format'] ?? null) === 'html';
        try {
            $this->csrf->validate($this->tokenFrom($request));
            $email = $request->body['email'] ?? null;
            $password = $request->body['password'] ?? null;

            if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '') {
                throw new HttpException('VALIDATION_ERROR', 'Ingresá un email válido y tu contraseña.', 422);
            }

            $result = $this->auth->login($email, $password);
        } catch (HttpException $exception) {
            if (!$html) {
                throw $exception;
            }
            if (isset($email) && is_string($email) && trim($email) !== '') {
                $this->session->put('old_email', trim($email));
            }
            $this->session->flash('error', $exception->getMessage());

            return new RedirectResponse(url('login'));
        }

        return $html ? new RedirectResponse(url('dashboard')) : new Response($result);
    }

    public function logout(Request $request): Response|RedirectResponse
    {
        $this->authorization->requireAuthenticated();
        $this->csrf->validate($this->tokenFrom($request));
        $this->session->logout();

        return ($request->body['format'] ?? null) === 'html'
            ? new RedirectResponse(url('login'))
            : new Response(null, 204);
    }

    public function me(Request $request): Response
    {
        $this->authorization->requireAuthenticated();
        return new Response($this->auth->currentUser());
    }

    private function tokenFrom(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($token) ? $token : null;
    }
}
