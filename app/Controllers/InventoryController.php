<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\InventoryService;

final class InventoryController
{
    public function __construct(private readonly InventoryService $inventory, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function movements(Request $request): Response
    {
        $this->authorization->requirePermission('inventario.ver');
        $page = $this->positive($request->query['page'] ?? 1);
        $perPage = $this->positive($request->query['per_page'] ?? 20);
        $result = $this->inventory->movements($page, min($perPage, 100));
        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function merma(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('inventario.merma');
        $this->csrf->validate($this->token($request));
        return new Response($this->inventory->merma($request->body, $actor), 201);
    }

    private function positive(mixed $value): int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }
        throw new HttpException('VALIDATION_ERROR', 'La paginacion no es valida.', 422);
    }

    private function token(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($token) ? $token : null;
    }
}
