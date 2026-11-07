<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CropCycleService;

final class CropCycleController
{
    public function __construct(private readonly CropCycleService $cycles, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorization->requirePermission('produccion.ver');
        $page = $this->positive($request->query['page'] ?? 1);
        $perPage = min($this->positive($request->query['per_page'] ?? 20), 100);
        $result = $this->cycles->list($page, $perPage);
        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function show(Request $request): Response
    {
        $this->authorization->requirePermission('produccion.ver');
        return new Response($this->cycles->get($this->id($request)));
    }

    public function store(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->cycles->create($request->body, $actor), 201);
    }

    public function transition(Request $request, string $target, string $permission = 'produccion.crear'): Response
    {
        $actor = $this->authorization->requirePermission($permission);
        $this->csrf->validate($this->token($request));
        return new Response($this->cycles->transition($this->id($request), $target, $actor));
    }

    public function updateEstimate(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->cycles->updateEstimate($this->id($request), $request->body, $actor));
    }

    private function id(Request $request): int
    {
        $value = $request->params['id'] ?? null;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El identificador no es valido.', 422);
        }
        return (int) $value;
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
        $value = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($value) ? $value : null;
    }
}
