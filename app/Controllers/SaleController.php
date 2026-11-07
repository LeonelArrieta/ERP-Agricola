<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\SaleService;

final class SaleController
{
    public function __construct(private readonly SaleService $sales, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function store(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('ventas.crear');
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        $this->csrf->validate(is_string($token) ? $token : null);
        return new Response($this->sales->create($request->body, $actor), 201);
    }
}
