<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CancellationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function sale(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function details(int $sale): array
    {
        $query = $this->pdo->prepare('SELECT * FROM detalles_ventas WHERE id_venta = :sale');
        $query->execute(['sale' => $sale]);
        return $query->fetchAll();
    }

    public function lot(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM lotes_produccion WHERE id_lote_produccion = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function updateLot(int $id, float $available): void
    {
        $query = $this->pdo->prepare('UPDATE lotes_produccion SET cantidad_disponible = :available WHERE id_lote_produccion = :id');
        $query->execute(['available' => $available, 'id' => $id]);
    }

    public function inventoryReversal(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_producto, id_lote_produccion, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones) VALUES (:product, :lot, NOW(), 'ReversionSalida', :quantity, :before, :after, 'CancelacionVenta', :origin, :user_id, :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function receivable(int $sale): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM cuentas_por_cobrar WHERE id_venta = :sale FOR UPDATE');
        $query->execute(['sale' => $sale]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function collected(int $account): float
    {
        $query = $this->pdo->prepare('SELECT COALESCE(SUM(importe), 0) FROM cobros WHERE id_cuenta_por_cobrar = :account');
        $query->execute(['account' => $account]);
        return (float) $query->fetchColumn();
    }

    public function cancelReceivable(int $id): void
    {
        $query = $this->pdo->prepare("UPDATE cuentas_por_cobrar SET saldo_actual = 0, estado = 'Cancelada' WHERE id_cuenta_por_cobrar = :id");
        $query->execute(['id' => $id]);
    }

    public function cash(): float
    {
        return (float) $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1 FOR UPDATE')->fetchColumn();
    }

    public function updateCash(float $balance): void
    {
        $query = $this->pdo->prepare('UPDATE caja SET saldo_actual = :balance WHERE id_caja = 1');
        $query->execute(['balance' => $balance]);
    }

    public function cashReversal(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_caja (fecha_hora, tipo, importe, origen_tipo, origen_id, id_usuario, concepto, observaciones) VALUES (NOW(), 'ReversionIngreso', :amount, 'CancelacionVenta', :origin, :user_id, 'Cancelacion de venta', :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function cancelSale(int $id): void
    {
        $query = $this->pdo->prepare("UPDATE ventas SET estado = 'Cancelado' WHERE id_venta = :id");
        $query->execute(['id' => $id]);
    }
}
