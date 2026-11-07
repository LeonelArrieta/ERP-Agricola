<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FinanceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lockReceivable(int $saleId): ?array
    {
        $query = $this->pdo->prepare('SELECT cxc.* FROM cuentas_por_cobrar cxc WHERE cxc.id_venta = :id FOR UPDATE');
        $query->execute(['id' => $saleId]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function lockPayable(int $purchaseId): ?array
    {
        $query = $this->pdo->prepare('SELECT cxp.* FROM cuentas_por_pagar cxp WHERE cxp.id_compra = :id FOR UPDATE');
        $query->execute(['id' => $purchaseId]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function createReceipt(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO cobros (id_cuenta_por_cobrar, fecha, importe, id_usuario, observaciones) VALUES (:account, :date, :amount, :user_id, :notes)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function createPayment(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO pagos (id_cuenta_por_pagar, fecha, importe, id_usuario, observaciones) VALUES (:account, :date, :amount, :user_id, :notes)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateReceivable(int $id, float $balance, string $state): void
    {
        $query = $this->pdo->prepare('UPDATE cuentas_por_cobrar SET saldo_actual = :balance, estado = :state WHERE id_cuenta_por_cobrar = :id');
        $query->execute(['balance' => $balance, 'state' => $state, 'id' => $id]);
    }

    public function updatePayable(int $id, float $balance, string $state): void
    {
        $query = $this->pdo->prepare('UPDATE cuentas_por_pagar SET saldo_actual = :balance, estado = :state WHERE id_cuenta_por_pagar = :id');
        $query->execute(['balance' => $balance, 'state' => $state, 'id' => $id]);
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
        $query = $this->pdo->prepare('INSERT INTO movimientos_caja (fecha_hora, tipo, importe, origen_tipo, origen_id, id_usuario, concepto, observaciones) VALUES (NOW(), :type, :amount, :origin_type, :origin, :user_id, :concept, :notes)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function receivables(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(cl.razon_social LIKE :search_a OR cl.nombre LIKE :search_b OR cl.apellido LIKE :search_c)';
            foreach (['search_a', 'search_b', 'search_c'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }
        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'cxc.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'cxc.fecha_emision >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'cxc.fecha_emision <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM cuentas_por_cobrar cxc
             INNER JOIN ventas v ON v.id_venta = cxc.id_venta
             INNER JOIN clientes cl ON cl.id_cliente = v.id_cliente' . $condition
        );
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT cxc.*, v.fecha AS venta_fecha, cl.razon_social AS cliente
             FROM cuentas_por_cobrar cxc
             INNER JOIN ventas v ON v.id_venta = cxc.id_venta
             INNER JOIN clientes cl ON cl.id_cliente = v.id_cliente' . $condition .
            ' ORDER BY cxc.id_cuenta_por_cobrar DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function payables(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(pr.razon_social LIKE :search_a OR pr.nombre LIKE :search_b OR pr.apellido LIKE :search_c OR g.descripcion LIKE :search_d OR cg.nombre LIKE :search_e)';
            foreach (['search_a', 'search_b', 'search_c', 'search_d', 'search_e'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }
        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'cxp.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'cxp.fecha_emision >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'cxp.fecha_emision <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $joins = ' FROM cuentas_por_pagar cxp
             LEFT JOIN compras c ON c.id_compra = cxp.id_compra
             LEFT JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
             LEFT JOIN gastos g ON g.id_gasto = cxp.id_gasto
             LEFT JOIN categorias_gastos cg ON cg.id_categoria_gasto = g.id_categoria_gasto';
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $joins . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT cxp.*, pr.razon_social AS proveedor, g.descripcion AS gasto_descripcion, cg.nombre AS gasto_categoria' .
            $joins . $condition .
            ' ORDER BY cxp.id_cuenta_por_pagar DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function cashMovements(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        if (($filters['tipo'] ?? null) !== null) {
            $where[] = 'm.tipo = :type';
            $params['type'] = $filters['tipo'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'DATE(m.fecha_hora) >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'DATE(m.fecha_hora) <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM movimientos_caja m' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT m.*, u.nombre AS usuario
             FROM movimientos_caja m
             LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario' . $condition .
            ' ORDER BY m.id_movimiento_caja DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function cashBalance(): float
    {
        return (float) $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1')->fetchColumn();
    }
}
