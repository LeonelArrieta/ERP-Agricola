<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CropTaskService;

final class CropTaskController
{
    public function __construct(private readonly CropTaskService $tasks, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function list(Request $request): Response
    {
        $this->authorization->requirePermission('produccion.ver');
        return new Response($this->tasks->list($this->id($request, 'cycle')));
    }

    public function create(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->tasks->createWithResources($this->id($request, 'cycle'), $request->body, $actor), 201);
    }

    public function transition(Request $request, string $target): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->tasks->transition($this->id($request), $target, $actor));
    }

    public function consume(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->tasks->consume($this->id($request), $request->body, $actor), 201);
    }

    public function works(Request $request): Response
    {
        $this->authorization->requirePermission('produccion.ver');
        return new Response($this->tasks->works($this->id($request)));
    }

    public function storeWork(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->tasks->registerWork($this->id($request), $request->body, $actor), 201);
    }

    private function id(Request $request, string $name = 'id'): int
    {
        $value = $request->params[$name] ?? null;
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
