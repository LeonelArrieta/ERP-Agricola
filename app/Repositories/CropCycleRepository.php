<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CropCycleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(cu.nombre LIKE :search_crop OR p.nombre LIKE :search_plot OR c.observaciones LIKE :search_notes)';
            $params['search_crop'] = '%' . $search . '%';
            $params['search_plot'] = '%' . $search . '%';
            $params['search_notes'] = '%' . $search . '%';
        }
        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'c.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'c.fecha_inicio >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'c.fecha_inicio <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM ciclos_cultivo c
             INNER JOIN cultivos cu ON cu.id_cultivo = c.id_cultivo
             INNER JOIN parcelas p ON p.id_parcela = c.id_parcela' . $condition
        );
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT c.*, cu.nombre AS cultivo, p.nombre AS parcela
             FROM ciclos_cultivo c
             INNER JOIN cultivos cu ON cu.id_cultivo = c.id_cultivo
             INNER JOIN parcelas p ON p.id_parcela = c.id_parcela' . $condition .
            ' ORDER BY c.id_ciclo_cultivo DESC LIMIT :limit OFFSET :offset'
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
            'SELECT c.*, cu.nombre AS cultivo, p.nombre AS parcela
             FROM ciclos_cultivo c
             INNER JOIN cultivos cu ON cu.id_cultivo = c.id_cultivo
             INNER JOIN parcelas p ON p.id_parcela = c.id_parcela
             WHERE c.id_ciclo_cultivo = :id'
        );
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function lock(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM ciclos_cultivo WHERE id_ciclo_cultivo = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function create(array $data): int
    {
        $query = $this->pdo->prepare(
            'INSERT INTO ciclos_cultivo (id_cultivo, id_parcela, fecha_inicio, cantidad_estimada, unidad_estimada, observaciones)
             VALUES (:cultivo, :parcela, :fecha, :cantidad_estimada, :unidad_estimada, :observaciones)'
        );
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEstimate(int $id, ?float $quantity, ?string $unit): void
    {
        $query = $this->pdo->prepare('UPDATE ciclos_cultivo SET cantidad_estimada = :quantity, unidad_estimada = :unit WHERE id_ciclo_cultivo = :id');
        $query->execute(['quantity' => $quantity, 'unit' => $unit, 'id' => $id]);
    }

    public function state(int $id, string $state, ?string $endDate = null): void
    {
        $query = $this->pdo->prepare('UPDATE ciclos_cultivo SET estado = :state, fecha_fin = :end_date WHERE id_ciclo_cultivo = :id');
        $query->execute(['state' => $state, 'end_date' => $endDate, 'id' => $id]);
    }
}
