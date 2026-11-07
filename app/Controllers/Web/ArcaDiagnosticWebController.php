<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HtmlResponse;
use App\Core\Navigation;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Services\ArcaDiagnosticsService;

final class ArcaDiagnosticWebController
{
    public function __construct(
        private readonly ArcaDiagnosticsService $diagnostics,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
    ) {
    }

    public function index(Request $request): HtmlResponse|RedirectResponse
    {
        if ($this->session->userId() === null) {
            return new RedirectResponse(url('login'));
        }
        $this->authorization->requirePermission('facturacion.administrar');

        $report = $this->diagnostics->run();
        $permissions = $this->authorization->permissions();

        return new HtmlResponse($this->views->render('billing/diagnostics', [
            'title' => 'Diagnostico ARCA | ERP Agricola',
            'active' => 'facturacion-diagnostico',
            'report' => $report,
            'user' => $this->authorization->user(),
            'permissions' => $permissions,
            'navigation' => Navigation::groups($permissions),
            'csrfToken' => $this->csrf->token(),
            'flashes' => $this->session->pullFlashes(),
        ]));
    }
}
