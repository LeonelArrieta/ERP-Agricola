<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class HarvestRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lockCycle(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM ciclos_cultivo WHERE id_ciclo_cultivo = :id FOR UPDATE');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function productUnit(int $id): ?string
    {
        $query = $this->pdo->prepare('SELECT unidad_medida FROM productos WHERE id_producto = :id AND activo = 1');
        $query->execute(['id' => $id]);
        $value = $query->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function costs(int $cycle): float
    {
        $query = $this->pdo->prepare('SELECT COALESCE((SELECT SUM(th.costo_total) FROM trabajos_humanos th INNER JOIN tareas_cultivo t ON t.id_tarea_cultivo = th.id_tarea_cultivo WHERE t.id_ciclo_cultivo = :cycle), 0) + COALESCE((SELECT SUM(ci.costo_total) FROM consumos_insumos ci INNER JOIN tareas_cultivo t2 ON t2.id_tarea_cultivo = ci.id_tarea_cultivo WHERE t2.id_ciclo_cultivo = :cycle2), 0)');
        $query->execute(['cycle' => $cycle, 'cycle2' => $cycle]);
        return (float) $query->fetchColumn();
    }

    public function harvests(int $cycle): array
    {
        $query = $this->pdo->prepare('SELECT * FROM cosechas WHERE id_ciclo_cultivo = :cycle ORDER BY id_cosecha');
        $query->execute(['cycle' => $cycle]);
        return $query->fetchAll();
    }

    public function createHarvest(int $cycle, array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO cosechas (id_ciclo_cultivo, fecha, cantidad, unidad_medida, observaciones) VALUES (:cycle, :date, :quantity, :unit, :notes)');
        $query->execute(['cycle' => $cycle] + $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function createLot(array $data): int
    {
        $query = $this->pdo->prepare('INSERT INTO lotes_produccion (id_producto, id_cosecha, fecha_produccion, cantidad_inicial, cantidad_disponible, costo_unitario, es_costo_provisional) VALUES (:product, :harvest, :date, :quantity_initial, :quantity_available, :cost, 1)');
        $query->execute([
            'product' => $data['product'],
            'harvest' => $data['harvest'],
            'date' => $data['date'],
            'quantity_initial' => $data['quantity'],
            'quantity_available' => $data['quantity'],
            'cost' => $data['cost'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function movement(array $data): int
    {
        $query = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_producto, id_lote_produccion, fecha_hora, tipo, cantidad, stock_anterior, stock_resultante, origen_tipo, origen_id, id_usuario, observaciones) VALUES (:product, :lot, NOW(), 'EntradaCosecha', :quantity, 0, :quantity_result, 'Cosecha', :harvest, :user_id, :notes)");
        $query->execute([
            'product' => $data['product'],
            'lot' => $data['lot'],
            'quantity' => $data['quantity'],
            'quantity_result' => $data['quantity'],
            'harvest' => $data['harvest'],
            'user_id' => $data['user_id'],
            'notes' => $data['notes'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function lots(int $cycle): array
    {
        $query = $this->pdo->prepare('SELECT l.* FROM lotes_produccion l INNER JOIN cosechas c ON c.id_cosecha = l.id_cosecha WHERE c.id_ciclo_cultivo = :cycle FOR UPDATE');
        $query->execute(['cycle' => $cycle]);
        return $query->fetchAll();
    }

    public function updateLotCost(int $id, float $cost): void
    {
        $query = $this->pdo->prepare('UPDATE lotes_produccion SET costo_unitario = :cost, es_costo_provisional = 0 WHERE id_lote_produccion = :id');
        $query->execute(['cost' => $cost, 'id' => $id]);
    }

    public function updateProvisionalLotsCost(int $cycleId, float $unitCost): void
    {
        $query = $this->pdo->prepare(
            'UPDATE lotes_produccion l
             INNER JOIN cosechas c ON c.id_cosecha = l.id_cosecha
             SET l.costo_unitario = :cost
             WHERE c.id_ciclo_cultivo = :cycle AND l.es_costo_provisional = 1'
        );
        $query->execute(['cost' => $unitCost, 'cycle' => $cycleId]);
    }

    public function closeCycle(int $id): void
    {
        $query = $this->pdo->prepare("UPDATE ciclos_cultivo SET estado = 'Cosechado', fecha_fin = GREATEST(CURRENT_DATE, fecha_inicio) WHERE id_ciclo_cultivo = :id");
        $query->execute(['id' => $id]);
    }
}
