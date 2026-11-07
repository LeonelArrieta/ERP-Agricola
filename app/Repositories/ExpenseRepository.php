<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ExpenseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO gastos (id_categoria_gasto, fecha, descripcion, importe, tipo_pago, estado) VALUES (:category, :date, :description, :amount, :payment_type, :state)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
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

    public function cashMovement(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_caja (fecha_hora, tipo, importe, origen_tipo, origen_id, id_usuario, concepto, observaciones) VALUES (NOW(), 'Egreso', :amount, 'Gasto', :origin, :user_id, 'Gasto contado', :notes)");
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function payable(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO cuentas_por_pagar (id_gasto, fecha_emision, importe_original, saldo_actual, estado) VALUES (:expense, :date, :amount, :balance, 'Pendiente')");
        $query->execute($data + ['balance' => $data['amount']]);
        return (int) $this->pdo->lastInsertId();
    }

    public function list(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(g.descripcion LIKE :search_desc OR c.nombre LIKE :search_category)';
            $params['search_desc'] = '%' . $search . '%';
            $params['search_category'] = '%' . $search . '%';
        }
        if (($filters['categoria'] ?? null) !== null) {
            $where[] = 'g.id_categoria_gasto = :category';
            $params['category'] = $filters['categoria'];
        }
        if (($filters['tipo_pago'] ?? null) !== null) {
            $where[] = 'g.tipo_pago = :payment_type';
            $params['payment_type'] = $filters['tipo_pago'];
        }
        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'g.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'g.fecha >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'g.fecha <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM gastos g INNER JOIN categorias_gastos c ON c.id_categoria_gasto = g.id_categoria_gasto' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT g.*, c.nombre AS categoria
             FROM gastos g
             INNER JOIN categorias_gastos c ON c.id_categoria_gasto = g.id_categoria_gasto' . $condition .
            ' ORDER BY g.id_gasto DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function categories(): array
    {
        return $this->pdo->query('SELECT id_categoria_gasto, nombre FROM categorias_gastos WHERE activo = 1 ORDER BY nombre')->fetchAll();
    }
}
