<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductionCatalogService;

final class ProductionCatalogController
{
    public function __construct(private readonly ProductionCatalogService $catalog, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function index(Request $request, string $type): Response
    {
        $this->authorization->requirePermission('produccion.ver');
        $page = $this->positive($request->query['page'] ?? 1, 'page');
        $perPage = $this->positive($request->query['per_page'] ?? 20, 'per_page');
        $active = null;
        if (isset($request->query['activo'])) {
            if (!in_array($request->query['activo'], ['true', 'false', '1', '0'], true)) {
                throw new HttpException('VALIDATION_ERROR', 'El filtro activo no es valido.', 422);
            }
            $active = in_array($request->query['activo'], ['true', '1'], true);
        }
        $result = $this->catalog->list($type, $page, $perPage, $active, (string) ($request->query['search'] ?? ''));
        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function show(Request $request, string $type): Response
    {
        $this->authorization->requirePermission('produccion.ver');
        return new Response($this->catalog->get($type, $this->id($request)));
    }

    public function store(Request $request, string $type): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->catalog->save($type, $request->body, $actor), 201);
    }

    public function update(Request $request, string $type): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->catalog->save($type, $request->body, $actor, $this->id($request)));
    }

    public function deactivate(Request $request, string $type): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->catalog->deactivate($type, $this->id($request), $actor));
    }

    public function activate(Request $request, string $type): Response
    {
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->catalog->activate($type, $this->id($request), $actor));
    }

    private function id(Request $request): int
    {
        $id = $request->params['id'] ?? null;
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El identificador no es valido.', 422);
        }
        return (int) $id;
    }

    private function positive(mixed $value, string $field): int
    {
        $number = is_numeric($value) ? (int) $value : 0;
        if ($number > 0 && $number <= 100) {
            return $number;
        }
        throw new HttpException('VALIDATION_ERROR', $field . ' no es valido.', 422);
    }

    private function token(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($token) ? $token : null;
    }
}
