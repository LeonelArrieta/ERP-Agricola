<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PurchaseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO compras (id_proveedor, fecha, tipo_pago, estado, subtotal, importe_alicuota, total, observaciones) VALUES (:supplier, :date, :payment_type, :state, :subtotal, 0, :total, :notes)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function detail(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO detalles_compras (id_compra, id_insumo, cantidad, unidad_medida, costo_unitario, importe_linea) VALUES (:purchase, :input, :quantity, :unit, :unit_cost, :line_total)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function lockInput(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM insumos WHERE id_insumo = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function updateInput(int $id, float $stock, float $cost): void
    {
        $query = $this->pdo->prepare('UPDATE insumos SET stock_actual = :stock, costo_unitario_actual = :cost WHERE id_insumo = :id');
        $query->execute(['stock' => $stock, 'cost' => $cost, 'id' => $id]);
    }

    public function movement(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_insumo, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones) VALUES (:input, NOW(), 'EntradaCompra', :quantity, :before, :after, 'Compra', :origin, :user_id, :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function lockCash(): float
    {
        $query = $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1 FOR UPDATE');
        return (float) $query->fetchColumn();
    }

    public function updateCash(float $balance): void
    {
        $query = $this->pdo->prepare('UPDATE caja SET saldo_actual = :balance WHERE id_caja = 1');
        $query->execute(['balance' => $balance]);
    }

    public function cashMovement(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_caja (fecha_hora, tipo, importe, origen_tipo, origen_id, id_usuario, concepto, observaciones) VALUES (NOW(), 'Egreso', :amount, 'Compra', :origin, :user_id, 'Compra contado', :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function receivable(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO cuentas_por_pagar (id_compra, fecha_emision, importe_original, saldo_actual, estado) VALUES (:purchase, :date, :amount, :balance, 'Pendiente')");
        $query->execute($data + ['balance' => $data['amount']]);
        return (int) $this->pdo->lastInsertId();
    }

    public function list(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(p.razon_social LIKE :search_a OR p.nombre LIKE :search_b OR p.apellido LIKE :search_c OR c.observaciones LIKE :search_d)';
            foreach (['search_a', 'search_b', 'search_c', 'search_d'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }
        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'c.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['tipo_pago'] ?? null) !== null) {
            $where[] = 'c.tipo_pago = :payment_type';
            $params['payment_type'] = $filters['tipo_pago'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'c.fecha >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'c.fecha <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM compras c INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT c.*, p.razon_social AS proveedor
             FROM compras c
             INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor' . $condition .
            ' ORDER BY c.id_compra DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT c.*, p.razon_social AS proveedor
             FROM compras c
             INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
             WHERE c.id_compra = :id'
        );
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function details(int $purchase): array
    {
        $query = $this->pdo->prepare(
            'SELECT d.*, i.nombre AS insumo
             FROM detalles_compras d
             INNER JOIN insumos i ON i.id_insumo = d.id_insumo
             WHERE d.id_compra = :purchase
             ORDER BY d.id_detalle_compra'
        );
        $query->execute(['purchase' => $purchase]);

        return $query->fetchAll();
    }

    public function payable(int $purchase): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM cuentas_por_pagar WHERE id_compra = :purchase');
        $query->execute(['purchase' => $purchase]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }
}
