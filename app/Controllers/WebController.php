<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HtmlResponse;
use App\Core\HttpException;
use App\Core\Navigation;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Services\DashboardService;
use Closure;

final class WebController
{
    public function __construct(
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
        private readonly Closure $authorizationFactory,
        private readonly Closure $dashboardFactory,
    ) {
    }

    public function login(Request $request): HtmlResponse|RedirectResponse
    {
        if ($this->session->userId() !== null) {
            return new RedirectResponse(url('dashboard'));
        }

        return new HtmlResponse($this->views->render('auth/login', [
            'title' => 'Acceso | ERP Agricola',
            'csrfToken' => $this->csrf->token(),
            'oldEmail' => $this->session->pull('old_email', ''),
            'flashes' => $this->session->pullFlashes(),
        ], 'auth'));
    }

    public function dashboard(Request $request): HtmlResponse
    {
        if ($this->session->userId() === null) {
            throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
        }

        $authorization = $this->authorization();
        $user = $authorization->user();
        if ($user === null) {
            $this->session->logout();
            throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
        }
        $permissions = $authorization->permissions();
        $summary = $this->dashboardSummary($request);

        return new HtmlResponse($this->views->render('dashboard/index', [
            'title' => 'Panel operativo | ERP Agricola',
            'active' => 'dashboard',
            'user' => $user,
            'permissions' => $permissions,
            'navigation' => Navigation::groups($permissions),
            'tiles' => Navigation::tiles($permissions),
            'summary' => $summary,
            'csrfToken' => $this->csrf->token(),
            'flashes' => $this->session->pullFlashes(),
        ]));
    }

    private function authorization(): Authorization
    {
        return ($this->authorizationFactory)();
    }

    private function dashboardSummary(Request $request): array
    {
        return ($this->dashboardFactory)()->summary(
            is_string($request->query['period'] ?? null) ? $request->query['period'] : 'month',
            is_string($request->query['from'] ?? null) ? $request->query['from'] : null,
            is_string($request->query['to'] ?? null) ? $request->query['to'] : null,
        );
    }
}
