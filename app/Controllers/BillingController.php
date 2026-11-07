<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\QueryFilters;
use App\Core\Request;
use App\Core\Response;
use App\Services\FacturacionService;

final class BillingController
{
    public function __construct(private readonly FacturacionService $billing, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorization->requirePermission('facturacion.ver');
        $page = $this->positive($request->query['page'] ?? 1, 'page');
        $perPage = $this->positive($request->query['per_page'] ?? 20, 'per_page');
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado_fiscal' => QueryFilters::enum($request->query['estado_fiscal'] ?? null, ['Pendiente', 'Enviando', 'Autorizado', 'Rechazado', 'ErrorComunicacion'], 'estado_fiscal'),
            'tipo' => QueryFilters::int($request->query['tipo'] ?? null, 'tipo'),
            'punto_venta' => QueryFilters::int($request->query['punto_venta'] ?? null, 'punto_venta'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->billing->list($page, $perPage, $filters);

        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function show(Request $request): Response
    {
        $this->authorization->requirePermission('facturacion.ver');

        return new Response($this->billing->get($this->id($request)));
    }

    public function invoice(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('facturacion.emitir');
        $this->csrf->validate($this->token($request));

        return new Response($this->billing->emitInvoice($this->id($request), $request->body, $actor), 201);
    }

    public function note(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('facturacion.notas');
        $this->csrf->validate($this->token($request));

        return new Response($this->billing->emitNote($this->id($request), $request->body, $actor), 201);
    }

    public function reconcile(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('facturacion.emitir');
        $this->csrf->validate($this->token($request));

        return new Response($this->billing->reconcile($this->id($request), $actor));
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
