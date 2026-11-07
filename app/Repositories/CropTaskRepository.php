<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CropTaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findTask(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT t.*, c.estado AS ciclo_estado FROM tareas_cultivo t INNER JOIN ciclos_cultivo c ON c.id_ciclo_cultivo = t.id_ciclo_cultivo WHERE t.id_tarea_cultivo = :id';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $query = $this->pdo->prepare($sql);
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function list(int $cycleId): array
    {
        $query = $this->pdo->prepare('SELECT tc.*, t.nombre AS tarea FROM tareas_cultivo tc INNER JOIN tareas t ON t.id_tarea = tc.id_tarea WHERE tc.id_ciclo_cultivo = :cycle ORDER BY tc.fecha, tc.id_tarea_cultivo');
        $query->execute(['cycle' => $cycleId]);
        return $query->fetchAll();
    }

    public function cycleState(int $cycleId): ?string
    {
        $query = $this->pdo->prepare('SELECT estado FROM ciclos_cultivo WHERE id_ciclo_cultivo = :id');
        $query->execute(['id' => $cycleId]);
        $value = $query->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function listAll(int $offset, int $limit, ?string $state, string $search): array
    {
        $where = [];
        $params = [];
        if ($state !== null) {
            $where[] = 'tc.estado = :state';
            $params['state'] = $state;
        }
        if ($search !== '') {
            $where[] = '(t.nombre LIKE :search_task OR cu.nombre LIKE :search_crop OR p.nombre LIKE :search_plot)';
            $params['search_task'] = '%' . $search . '%';
            $params['search_crop'] = '%' . $search . '%';
            $params['search_plot'] = '%' . $search . '%';
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM tareas_cultivo tc
             INNER JOIN tareas t ON t.id_tarea = tc.id_tarea
             INNER JOIN ciclos_cultivo c ON c.id_ciclo_cultivo = tc.id_ciclo_cultivo
             INNER JOIN cultivos cu ON cu.id_cultivo = c.id_cultivo
             INNER JOIN parcelas p ON p.id_parcela = c.id_parcela' . $condition
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $query = $this->pdo->prepare(
            'SELECT tc.*, t.nombre AS tarea, cu.nombre AS cultivo, p.nombre AS parcela
             FROM tareas_cultivo tc
             INNER JOIN tareas t ON t.id_tarea = tc.id_tarea
             INNER JOIN ciclos_cultivo c ON c.id_ciclo_cultivo = tc.id_ciclo_cultivo
             INNER JOIN cultivos cu ON cu.id_cultivo = c.id_cultivo
             INNER JOIN parcelas p ON p.id_parcela = c.id_parcela' . $condition .
            ' ORDER BY tc.id_tarea_cultivo DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public function detail(int $id): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT tc.*, t.nombre AS tarea, c.id_ciclo_cultivo, c.estado AS ciclo_estado, cu.nombre AS cultivo, p.nombre AS parcela
             FROM tareas_cultivo tc
             INNER JOIN tareas t ON t.id_tarea = tc.id_tarea
             INNER JOIN ciclos_cultivo c ON c.id_ciclo_cultivo = tc.id_ciclo_cultivo
             INNER JOIN cultivos cu ON cu.id_cultivo = c.id_cultivo
             INNER JOIN parcelas p ON p.id_parcela = c.id_parcela
             WHERE tc.id_tarea_cultivo = :id'
        );
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function consumptions(int $taskId): array
    {
        $query = $this->pdo->prepare(
            'SELECT ci.*, i.nombre AS insumo
             FROM consumos_insumos ci
             INNER JOIN insumos i ON i.id_insumo = ci.id_insumo
             WHERE ci.id_tarea_cultivo = :task
             ORDER BY ci.id_consumo_insumo'
        );
        $query->execute(['task' => $taskId]);

        return $query->fetchAll();
    }

    public function works(int $taskId): array
    {
        $query = $this->pdo->prepare(
            'SELECT th.*, tr.nombre, tr.apellido
             FROM trabajos_humanos th
             INNER JOIN trabajadores tr ON tr.id_trabajador = th.id_trabajador
             WHERE th.id_tarea_cultivo = :task
             ORDER BY th.id_trabajo_humano'
        );
        $query->execute(['task' => $taskId]);

        return $query->fetchAll();
    }

    public function taskCosts(int $taskId): array
    {
        $query = $this->pdo->prepare(
            'SELECT
                COALESCE((SELECT SUM(ci.costo_total) FROM consumos_insumos ci WHERE ci.id_tarea_cultivo = :task), 0) AS costo_insumos,
                COALESCE((SELECT SUM(th.costo_total) FROM trabajos_humanos th WHERE th.id_tarea_cultivo = :task2), 0) AS costo_trabajos'
        );
        $query->execute(['task' => $taskId, 'task2' => $taskId]);
        $costs = $query->fetch() ?: ['costo_insumos' => 0, 'costo_trabajos' => 0];

        return [
            'costo_insumos' => (float) $costs['costo_insumos'],
            'costo_trabajos' => (float) $costs['costo_trabajos'],
            'costo_total' => (float) $costs['costo_insumos'] + (float) $costs['costo_trabajos'],
        ];
    }

    public function costsByCycle(int $cycleId): array
    {
        $query = $this->pdo->prepare(
            'SELECT t.id_tarea_cultivo,
                (SELECT COALESCE(SUM(ci.costo_total), 0) FROM consumos_insumos ci WHERE ci.id_tarea_cultivo = t.id_tarea_cultivo) AS costo_insumos,
                (SELECT COALESCE(SUM(th.costo_total), 0) FROM trabajos_humanos th WHERE th.id_tarea_cultivo = t.id_tarea_cultivo) AS costo_trabajos
             FROM tareas_cultivo t
             WHERE t.id_ciclo_cultivo = :cycle'
        );
        $query->execute(['cycle' => $cycleId]);
        $costs = [];
        foreach ($query->fetchAll() as $row) {
            $costs[(int) $row['id_tarea_cultivo']] = [
                'costo_insumos' => (float) $row['costo_insumos'],
                'costo_trabajos' => (float) $row['costo_trabajos'],
                'costo_total' => (float) $row['costo_insumos'] + (float) $row['costo_trabajos'],
            ];
        }

        return $costs;
    }

    public function create(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO tareas_cultivo (id_ciclo_cultivo, id_tarea, fecha, observaciones) VALUES (:cycle, :task, :date, :notes)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function state(int $id, string $state): void
    {
        $query = $this->pdo->prepare('UPDATE tareas_cultivo SET estado = :state WHERE id_tarea_cultivo = :id');
        $query->execute(['state' => $state, 'id' => $id]);
    }

    public function lockInsumo(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM insumos WHERE id_insumo = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function createConsumption(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO consumos_insumos (id_tarea_cultivo, id_insumo, fecha, cantidad, unidad_medida, costo_unitario, costo_total) VALUES (:task, :input, :date, :quantity, :unit, :unit_cost, :total)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function lockWorker(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM trabajadores WHERE id_trabajador = :id AND activo = 1 FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function createWork(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO trabajos_humanos (id_tarea_cultivo, id_trabajador, fecha, horas_aplicadas, costo_hora, costo_total) VALUES (:task, :worker, :date, :hours, :hour_cost, :total)');
        $query->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStock(int $id, float $stock): void
    {
        $query = $this->pdo->prepare('UPDATE insumos SET stock_actual = :stock WHERE id_insumo = :id');
        $query->execute(['stock' => $stock, 'id' => $id]);
    }

    public function movement(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO movimientos_inventario (id_insumo, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones) VALUES (:input, NOW(), \'SalidaConsumo\', :quantity, :before, :after, \'ConsumoInsumo\', :origin, :user_id, :notes)');
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }
}
