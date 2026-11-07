<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\HarvestService;

final class HarvestController
{
    public function __construct(private readonly HarvestService $harvests, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function create(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->harvests->create($this->id($request), $request->body, $actor), 201);
    }

    public function consolidate(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->harvests->consolidate($this->id($request), $actor));
    }

    private function id(Request $request): int
    {
        $value = $request->params['id'] ?? null;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El identificador no es valido.', 422);
        }
        return (int) $value;
    }

    private function token(Request $request): ?string
    {
        $value = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($value) ? $value : null;
    }
}
