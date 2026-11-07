<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\QueryFilters;
use App\Core\Request;
use App\Core\Response;
use App\Services\FinanceService;

final class FinanceController
{
    public function __construct(private readonly FinanceService $finance, private readonly Authorization $authorization, private readonly Csrf $csrf)
    {
    }

    public function receive(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('cobranzas.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->finance->receive($this->id($request), $request->body, $actor), 201);
    }

    public function pay(Request $request): Response
    {
        $actor = $this->authorization->requirePermission('pagos.crear');
        $this->csrf->validate($this->token($request));
        return new Response($this->finance->pay($this->id($request), $request->body, $actor), 201);
    }

    public function receivables(Request $request): Response
    {
        $this->authorization->requirePermission('cobranzas.ver');
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Pendiente', 'Parcialmente_Cobrada', 'Cobrada', 'Cancelada'], 'estado'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->finance->receivables($this->positive($request->query['page'] ?? 1, 'page'), $this->positive($request->query['per_page'] ?? 20, 'per_page'), $filters);

        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function payables(Request $request): Response
    {
        $this->authorization->requirePermission('pagos.ver');
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Pendiente', 'Parcialmente_Pagada', 'Pagada', 'Cancelada'], 'estado'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->finance->payables($this->positive($request->query['page'] ?? 1, 'page'), $this->positive($request->query['per_page'] ?? 20, 'per_page'), $filters);

        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    public function cash(Request $request): Response
    {
        $this->authorization->requirePermission('caja.ver');
        $filters = [
            'tipo' => QueryFilters::enum($request->query['tipo'] ?? null, ['Ingreso', 'Egreso', 'ReversionIngreso', 'ReversionEgreso'], 'tipo'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->finance->cashMovements($this->positive($request->query['page'] ?? 1, 'page'), $this->positive($request->query['per_page'] ?? 20, 'per_page'), $filters);

        return new Response($result['items'], 200, [], true, $result['pagination']);
    }

    private function positive(mixed $value, string $field): int
    {
        $number = is_numeric($value) ? (int) $value : 0;
        if ($number > 0 && $number <= 100) {
            return $number;
        }
        throw new HttpException('VALIDATION_ERROR', $field . ' no es valido.', 422);
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
