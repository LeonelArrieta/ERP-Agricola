<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReversalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function receipt(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM cobros WHERE id_cobro = :id'); $q->execute(['id' => $id]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function payment(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM pagos WHERE id_pago = :id'); $q->execute(['id' => $id]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function accountReceivable(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM cuentas_por_cobrar WHERE id_cuenta_por_cobrar = :id FOR UPDATE'); $q->execute(['id' => $id]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function accountPayable(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM cuentas_por_pagar WHERE id_cuenta_por_pagar = :id FOR UPDATE'); $q->execute(['id' => $id]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function alreadyReceipt(int $id): bool
    {
        $q = $this->pdo->prepare('SELECT 1 FROM reversiones_cobros WHERE id_cobro = :id'); $q->execute(['id' => $id]); return $q->fetchColumn() !== false;
    }
    public function alreadyPayment(int $id): bool
    {
        $q = $this->pdo->prepare('SELECT 1 FROM reversiones_pagos WHERE id_pago = :id'); $q->execute(['id' => $id]); return $q->fetchColumn() !== false;
    }
    public function cash(): float { return (float) $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1 FOR UPDATE')->fetchColumn(); }
    public function updateCash(float $value): void { $q = $this->pdo->prepare('UPDATE caja SET saldo_actual = :value WHERE id_caja = 1'); $q->execute(['value' => $value]); }
    public function cashMovement(array $d): int
    {
        $q = $this->pdo->prepare("INSERT INTO movimientos_caja (fecha_hora, tipo, importe, origen_tipo, origen_id, id_usuario, concepto, observaciones) VALUES (NOW(), :type, :amount, :origin_type, :origin, :user_id, :concept, :notes)"); $q->execute($d); return (int) $this->pdo->lastInsertId();
    }
    public function createReceiptReversal(array $d): int
    {
        $q = $this->pdo->prepare('INSERT INTO reversiones_cobros (id_cobro, fecha_hora, id_usuario, motivo, id_movimiento_caja) VALUES (:original, NOW(), :user_id, :reason, :movement)'); $q->execute($d); return (int) $this->pdo->lastInsertId();
    }
    public function createPaymentReversal(array $d): int
    {
        $q = $this->pdo->prepare('INSERT INTO reversiones_pagos (id_pago, fecha_hora, id_usuario, motivo, id_movimiento_caja) VALUES (:original, NOW(), :user_id, :reason, :movement)'); $q->execute($d); return (int) $this->pdo->lastInsertId();
    }
    public function updateReceivable(int $id, float $balance, string $state): void { $q = $this->pdo->prepare('UPDATE cuentas_por_cobrar SET saldo_actual = :balance, estado = :state WHERE id_cuenta_por_cobrar = :id'); $q->execute(['balance' => $balance, 'state' => $state, 'id' => $id]); }
    public function updatePayable(int $id, float $balance, string $state): void { $q = $this->pdo->prepare('UPDATE cuentas_por_pagar SET saldo_actual = :balance, estado = :state WHERE id_cuenta_por_pagar = :id'); $q->execute(['balance' => $balance, 'state' => $state, 'id' => $id]); }
}
