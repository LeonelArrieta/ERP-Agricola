<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContactService;

final class ContactController
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly string $permissionPrefix,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorization->requirePermission($this->permissionPrefix . '.ver');
        $page = $this->positiveInt($request->query['page'] ?? 1, 'page');
        $perPage = $this->positiveInt($request->query['per_page'] ?? 20, 'per_page');
        if ($perPage > 100) {
            throw new HttpException('VALIDATION_ERROR', 'per_page no puede superar 100.', 422);
        }
        $active = null;
        if (isset($request->query['activo'])) {
            if (!in_array($request->query['activo'], ['true', 'false', '1', '0'], true)) {
                throw new HttpException('VALIDATION_ERROR', 'El filtro activo no es valido.', 422);
            }
            $active = in_array($request->query['activo'], ['true', '1'], true);
        }
        $search = is_string($request->query['search'] ?? null) ? trim($request->query['search']) : '';
        $result = $this->contacts->list($page, $perPage, $active, $search);
        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function show(Request $request): Response
    {
        $this->authorization->requirePermission($this->permissionPrefix . '.ver');
        return new Response($this->contacts->get($this->id($request)));
    }

    public function store(Request $request): Response
    {
        $actor = $this->authorization->requirePermission($this->permissionPrefix . '.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->contacts->create($request->body, $actor), 201);
    }

    public function update(Request $request): Response
    {
        $actor = $this->authorization->requirePermission($this->permissionPrefix . '.editar');
        $this->csrf->validate($this->token($request));
        return new Response($this->contacts->update($this->id($request), $request->body, $actor));
    }

    public function deactivate(Request $request): Response
    {
        $actor = $this->authorization->requirePermission($this->permissionPrefix . '.editar');
        $this->csrf->validate($this->token($request));
        return new Response($this->contacts->deactivate($this->id($request), $actor));
    }

    private function id(Request $request): int
    {
        $id = $request->params['id'] ?? null;
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El identificador no es valido.', 422);
        }
        return (int) $id;
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if ((is_string($value) && ctype_digit($value)) || is_int($value)) {
            $number = (int) $value;
            if ($number > 0) {
                return $number;
            }
        }
        throw new HttpException('VALIDATION_ERROR', $field . ' debe ser un entero positivo.', 422);
    }

    private function token(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($token) ? $token : null;
    }
}
