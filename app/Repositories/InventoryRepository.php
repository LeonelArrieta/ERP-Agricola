<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InventoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function movements(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(i.nombre LIKE :search_input OR p.nombre LIKE :search_product)';
            $params['search_input'] = '%' . $search . '%';
            $params['search_product'] = '%' . $search . '%';
        }
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
        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM movimientos_inventario m
             LEFT JOIN insumos i ON i.id_insumo = m.id_insumo
             LEFT JOIN productos p ON p.id_producto = m.id_producto' . $condition
        );
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT m.*, i.nombre AS insumo, p.nombre AS producto, u.nombre AS usuario
             FROM movimientos_inventario m
             LEFT JOIN insumos i ON i.id_insumo = m.id_insumo
             LEFT JOIN productos p ON p.id_producto = m.id_producto
             LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario' . $condition .
            ' ORDER BY m.id_movimiento_inventario DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function lockInsumo(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM insumos WHERE id_insumo = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function updateStock(int $id, float $stock): void
    {
        $query = $this->pdo->prepare('UPDATE insumos SET stock_actual = :stock WHERE id_insumo = :id');
        $query->execute(['stock' => $stock, 'id' => $id]);
    }

    public function movement(array $data): int
    {
        $query = $this->pdo->prepare(
            'INSERT INTO movimientos_inventario
             (id_insumo, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones)
             VALUES (:id_insumo, NOW(), :tipo, :cantidad, :before, :after, :origin_type, :origin_id, :user_id, :notes)'
        );
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }
}
