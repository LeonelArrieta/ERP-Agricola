<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SaleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO ventas (id_cliente, fecha, tipo_pago, estado, subtotal, importe_alicuota, total, observaciones) VALUES (:client, :date, :payment_type, :state, :subtotal, :tax, :total, :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function lockLot(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM lotes_produccion WHERE id_lote_produccion = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function product(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT porcentaje_alicuota, unidad_medida FROM productos WHERE id_producto = :id AND activo = 1');
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function detail(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO detalles_ventas (id_venta, id_producto, id_lote_produccion, cantidad, unidad_medida, precio_costo, porcentaje_rentabilidad, precio_bruto, porcentaje_alicuota, subtotal, importe_alicuota, precio_neto_final) VALUES (:sale, :product, :lot, :quantity, :unit, :cost, :margin, :gross, :rate, :subtotal, :tax, :net)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateLot(int $id, float $available): void
    {
        $query = $this->pdo->prepare('UPDATE lotes_produccion SET cantidad_disponible = :available WHERE id_lote_produccion = :id');
        $query->execute(['available' => $available, 'id' => $id]);
    }

    public function movement(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_producto, id_lote_produccion, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones) VALUES (:product, :lot, NOW(), 'SalidaVenta', :quantity, :before, :after, 'Venta', :origin, :user_id, :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function lockCash(): float
    {
        return (float) $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1 FOR UPDATE')->fetchColumn();
    }

    public function updateCash(float $balance): void
    {
        $query = $this->pdo->prepare('UPDATE caja SET saldo_actual = :balance WHERE id_caja = 1');
        $query->execute(['balance' => $balance]);
    }

    public function cashMovement(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_caja (fecha_hora, tipo, importe, origen_tipo, origen_id, id_usuario, concepto, observaciones) VALUES (NOW(), 'Ingreso', :amount, 'Venta', :origin, :user_id, 'Venta contado', :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function receivable(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO cuentas_por_cobrar (id_venta, fecha_emision, importe_original, saldo_actual, estado) VALUES (:sale, :date, :amount, :balance, 'Pendiente')");
        $query->execute($data + ['balance' => $data['amount']]);
        return (int) $this->pdo->lastInsertId();
    }

    public function list(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(c.razon_social LIKE :search_a OR c.nombre LIKE :search_b OR c.apellido LIKE :search_c OR v.observaciones LIKE :search_d)';
            foreach (['search_a', 'search_b', 'search_c', 'search_d'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }
        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'v.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['tipo_pago'] ?? null) !== null) {
            $where[] = 'v.tipo_pago = :payment_type';
            $params['payment_type'] = $filters['tipo_pago'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'v.fecha >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'v.fecha <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM ventas v INNER JOIN clientes c ON c.id_cliente = v.id_cliente' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT v.*, c.razon_social AS cliente
             FROM ventas v
             INNER JOIN clientes c ON c.id_cliente = v.id_cliente' . $condition .
            ' ORDER BY v.id_venta DESC LIMIT :limit OFFSET :offset'
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
            'SELECT v.*, c.razon_social AS cliente
             FROM ventas v
             INNER JOIN clientes c ON c.id_cliente = v.id_cliente
             WHERE v.id_venta = :id'
        );
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function details(int $sale): array
    {
        $query = $this->pdo->prepare(
            'SELECT d.*, p.nombre AS producto, COALESCE(d.unidad_medida, p.unidad_medida) AS unidad_medida, l.cantidad_disponible, l.es_costo_provisional
             FROM detalles_ventas d
             INNER JOIN productos p ON p.id_producto = d.id_producto
             INNER JOIN lotes_produccion l ON l.id_lote_produccion = d.id_lote_produccion
             WHERE d.id_venta = :sale
             ORDER BY d.id_detalle_venta'
        );
        $query->execute(['sale' => $sale]);

        return $query->fetchAll();
    }

    public function receivableBySale(int $sale): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM cuentas_por_cobrar WHERE id_venta = :sale');
        $query->execute(['sale' => $sale]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function availableLots(): array
    {
        $query = $this->pdo->query(
            'SELECT l.id_lote_produccion, l.id_producto, l.cantidad_disponible, l.costo_unitario, l.es_costo_provisional, p.nombre AS producto, p.porcentaje_alicuota, p.unidad_medida
             FROM lotes_produccion l
             INNER JOIN productos p ON p.id_producto = l.id_producto
             WHERE l.cantidad_disponible > 0 AND p.activo = 1
             ORDER BY l.id_lote_produccion DESC
             LIMIT 100'
        );

        return $query->fetchAll();
    }
}
