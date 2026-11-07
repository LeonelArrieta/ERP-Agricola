<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\ExpenseService;

final class ExpenseController
{
    public function __construct(private readonly ExpenseService $expenses, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function store(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('gastos.crear');
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        $this->csrf->validate(is_string($token) ? $token : null);
        return new Response($this->expenses->create($request->body, $actor), 201);
    }
}
