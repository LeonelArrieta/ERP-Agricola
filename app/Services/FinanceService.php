<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\FinanceRepository;
use PDO;

final class FinanceService
{
    public function __construct(private readonly PDO $pdo, private readonly FinanceRepository $finance)
    {
    }

    public function receive(int $saleId, array $input, int $actor): array
    {
        return $this->apply($saleId, $input, $actor, false);
    }

    public function pay(int $purchaseId, array $input, int $actor): array
    {
        return $this->apply($purchaseId, $input, $actor, true);
    }

    public function receivables(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->finance->receivables(($page - 1) * $perPage, $perPage, $filters);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function payables(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->finance->payables(($page - 1) * $perPage, $perPage, $filters);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function cashMovements(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->finance->cashMovements(($page - 1) * $perPage, $perPage, $filters);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function cashBalance(): float
    {
        return $this->finance->cashBalance();
    }

    private function apply(int $originId, array $input, int $actor, bool $payment): array
    {
        $amount = $input['importe'] ?? null;
        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new HttpException('VALIDATION_ERROR', 'El importe debe ser mayor que cero.', 422);
        }
        $date = $input['fecha'] ?? date('Y-m-d');
        $dateObject = date_create_from_format('Y-m-d', (string) $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422);
        }
        $this->pdo->beginTransaction();
        try {
            $account = $payment ? $this->finance->lockPayable($originId) : $this->finance->lockReceivable($originId);
            if ($account === null || $account['estado'] === 'Cancelada') {
                throw new HttpException('NOT_FOUND', 'La cuenta financiera no existe o esta cancelada.', 404);
            }
            $amount = (float) $amount;
            $balance = (float) $account['saldo_actual'];
            if ($amount > $balance) {
                throw new HttpException('CONFLICT', 'El importe supera el saldo pendiente.', 409);
            }
            $newBalance = $balance - $amount;
            $state = $newBalance == 0.0 ? ($payment ? 'Pagada' : 'Cobrada') : ($payment ? 'Parcialmente_Pagada' : 'Parcialmente_Cobrada');
            if ($payment) {
                $id = $this->finance->createPayment(['account' => $account['id_cuenta_por_pagar'], 'date' => $date, 'amount' => $amount, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
                $this->finance->updatePayable((int) $account['id_cuenta_por_pagar'], $newBalance, $state);
                $cash = $this->finance->lockCash();
                if ($cash < $amount) {
                    throw new HttpException('CONFLICT', 'El saldo de Caja es insuficiente.', 409);
                }
                $this->finance->updateCash($cash - $amount);
                $movement = $this->finance->cashMovement(['type' => 'Egreso', 'amount' => $amount, 'origin_type' => 'Pago', 'origin' => $id, 'user_id' => $actor, 'concept' => 'Pago de CxP', 'notes' => $input['observaciones'] ?? null]);
            } else {
                $id = $this->finance->createReceipt(['account' => $account['id_cuenta_por_cobrar'], 'date' => $date, 'amount' => $amount, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
                $this->finance->updateReceivable((int) $account['id_cuenta_por_cobrar'], $newBalance, $state);
                $cash = $this->finance->lockCash();
                $this->finance->updateCash($cash + $amount);
                $movement = $this->finance->cashMovement(['type' => 'Ingreso', 'amount' => $amount, 'origin_type' => 'Cobro', 'origin' => $id, 'user_id' => $actor, 'concept' => 'Cobro de CxC', 'notes' => $input['observaciones'] ?? null]);
            }
            $this->audit($actor, $payment ? 'RegistrarPago' : 'RegistrarCobro', $id, ['movimiento_caja' => $movement, 'saldo' => $newBalance]);
            $this->pdo->commit();
            return [$payment ? 'id_pago' : 'id_cobro' => $id, 'id_movimiento_caja' => $movement, 'saldo_actual' => $newBalance, 'estado' => $state];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function audit(int $actor, string $action, int $id, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => $action === 'RegistrarPago' ? 'pagos' : 'cobros', 'entity_id' => $id, 'values' => json_encode($values)]);
    }
}
