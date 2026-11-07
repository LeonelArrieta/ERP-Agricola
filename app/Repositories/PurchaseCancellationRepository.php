<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PurchaseCancellationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function purchase(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM compras WHERE id_compra = :id FOR UPDATE');
        $q->execute(['id' => $id]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function details(int $id): array
    {
        $q = $this->pdo->prepare('SELECT * FROM detalles_compras WHERE id_compra = :id');
        $q->execute(['id' => $id]); return $q->fetchAll();
    }
    public function movement(int $purchase, int $input): ?array
    {
        $q = $this->pdo->prepare("SELECT * FROM movimientos_inventario WHERE origen_tipo = 'Compra' AND origen_id = :purchase AND id_insumo = :input ORDER BY id_movimiento_inventario LIMIT 1");
        $q->execute(['purchase' => $purchase, 'input' => $input]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function dependentMovements(int $input, int $movement): int
    {
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM movimientos_inventario WHERE id_insumo = :input AND id_movimiento_inventario > :movement');
        $q->execute(['input' => $input, 'movement' => $movement]); return (int) $q->fetchColumn();
    }
    public function input(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM insumos WHERE id_insumo = :id FOR UPDATE');
        $q->execute(['id' => $id]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function updateInput(int $id, float $stock, float $cost): void
    {
        $q = $this->pdo->prepare('UPDATE insumos SET stock_actual = :stock, costo_unitario_actual = :cost WHERE id_insumo = :id');
        $q->execute(['stock' => $stock, 'cost' => $cost, 'id' => $id]);
    }
    public function reverseMovement(array $d): int
    {
        $q = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_insumo, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones) VALUES (:input, NOW(), 'ReversionEntrada', :quantity, :before, :after, 'CancelacionCompra', :origin, :user_id, :notes)");
        $q->execute($d); return (int) $this->pdo->lastInsertId();
    }
    public function reconstructedCost(int $input, int $purchase): array
    {
        $q = $this->pdo->prepare("SELECT COALESCE(SUM(d.cantidad), 0), COALESCE(SUM(d.cantidad * d.costo_unitario), 0) FROM detalles_compras d INNER JOIN compras c ON c.id_compra = d.id_compra WHERE d.id_insumo = :input AND c.id_compra <> :purchase AND c.estado <> 'Cancelado'");
        $q->execute(['input' => $input, 'purchase' => $purchase]); return $q->fetch(PDO::FETCH_NUM);
    }
    public function payable(int $purchase): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM cuentas_por_pagar WHERE id_compra = :purchase FOR UPDATE');
        $q->execute(['purchase' => $purchase]); $v = $q->fetch(); return is_array($v) ? $v : null;
    }
    public function paid(int $account): float
    {
        $q = $this->pdo->prepare('SELECT COALESCE(SUM(importe), 0) FROM pagos WHERE id_cuenta_por_pagar = :account');
        $q->execute(['account' => $account]); return (float) $q->fetchColumn();
    }
    public function cash(): float { return (float) $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1 FOR UPDATE')->fetchColumn(); }
    public function updateCash(float $value): void { $q=$this->pdo->prepare('UPDATE caja SET saldo_actual=:value WHERE id_caja=1'); $q->execute(['value'=>$value]); }
    public function cashReversal(array $d): int
    {
        $q=$this->pdo->prepare("INSERT INTO movimientos_caja (fecha_hora,tipo,importe,origen_tipo,origen_id,id_usuario,concepto,observaciones) VALUES (NOW(),'ReversionEgreso',:amount,'CancelacionCompra',:origin,:user_id,'Cancelacion de compra',:notes)"); $q->execute($d); return (int)$this->pdo->lastInsertId();
    }
    public function cancelPayable(int $id): void { $q=$this->pdo->prepare("UPDATE cuentas_por_pagar SET saldo_actual=0,estado='Cancelada' WHERE id_cuenta_por_pagar=:id"); $q->execute(['id'=>$id]); }
    public function cancelPurchase(int $id): void { $q=$this->pdo->prepare("UPDATE compras SET estado='Cancelado' WHERE id_compra=:id"); $q->execute(['id'=>$id]); }
}
