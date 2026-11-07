<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ExpenseRepository;
use PDO;

final class ExpenseService
{
    public function __construct(private readonly PDO $pdo, private readonly ExpenseRepository $expenses)
    {
    }

    public function list(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->expenses->list(($page - 1) * $perPage, $perPage, $filters);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function categories(): array
    {
        return $this->expenses->categories();
    }

    public function create(array $input, int $actor): array
    {
        if (!is_numeric($input['id_categoria_gasto'] ?? null) || trim((string) ($input['descripcion'] ?? '')) === '' || !is_numeric($input['importe'] ?? null) || (float) $input['importe'] <= 0 || !in_array($input['tipo_pago'] ?? null, ['Contado', 'Diferido'], true)) {
            throw new HttpException('VALIDATION_ERROR', 'Categoria, descripcion, importe y tipo de pago son obligatorios.', 422);
        }
        $date = $input['fecha'] ?? date('Y-m-d');
        $dateObject = date_create_from_format('Y-m-d', (string) $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422);
        }
        $amount = (float) $input['importe'];
        $this->pdo->beginTransaction();
        try {
            $id = $this->expenses->create(['category' => (int) $input['id_categoria_gasto'], 'date' => $date, 'description' => trim($input['descripcion']), 'amount' => $amount, 'payment_type' => $input['tipo_pago'], 'state' => $input['tipo_pago'] === 'Contado' ? 'Pagado' : 'Pendiente']);
            if ($input['tipo_pago'] === 'Contado') {
                $cash = $this->expenses->cash();
                if ($cash < $amount) {
                    throw new HttpException('CONFLICT', 'El saldo de Caja es insuficiente.', 409);
                }
                $this->expenses->updateCash($cash - $amount);
                $movement = $this->expenses->cashMovement(['amount' => $amount, 'origin' => $id, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
            } else {
                $movement = null;
                $this->expenses->payable(['expense' => $id, 'date' => $date, 'amount' => $amount]);
            }
            $this->audit($actor, $id, ['tipo_pago' => $input['tipo_pago'], 'movimiento_caja' => $movement]);
            $this->pdo->commit();
            return ['id_gasto' => $id, 'importe' => $amount, 'tipo_pago' => $input['tipo_pago']];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function audit(int $actor, int $id, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, \'Registrar\', \'gastos\', :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'entity_id' => $id, 'values' => json_encode($values)]);
    }
}
