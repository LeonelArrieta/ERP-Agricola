<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\HarvestRepository;
use PDO;
use PDOException;

final class HarvestService
{
    public function __construct(private readonly PDO $pdo, private readonly HarvestRepository $harvests)
    {
    }

    public function create(int $cycle, array $input, int $actor): array
    {
        if (!is_numeric($input['id_producto'] ?? null) || !is_numeric($input['cantidad'] ?? null) || (float) $input['cantidad'] <= 0) {
            throw new HttpException('VALIDATION_ERROR', 'Producto y cantidad son obligatorios.', 422);
        }
        $this->pdo->beginTransaction();
        try {
            $locked = $this->harvests->lockCycle($cycle);
            if ($locked === null) {
                throw new HttpException('NOT_FOUND', 'El ciclo no existe.', 404);
            }
            if ($locked['estado'] !== 'EnCurso') {
                throw new HttpException('CONFLICT', 'Solo un ciclo EnCurso admite cosechas.', 409);
            }
            $productId = (int) $input['id_producto'];
            $unit = $this->harvests->productUnit($productId);
            if ($unit === null) {
                throw new HttpException('NOT_FOUND', 'El producto no existe o esta inactivo.', 404);
            }
            if ($locked['unidad_estimada'] !== null && (string) $locked['unidad_estimada'] !== $unit) {
                throw new HttpException('VALIDATION_ERROR', 'La unidad del producto no coincide con la produccion estimada del ciclo.', 422, ['field' => 'id_producto']);
            }
            $registeredHarvests = $this->harvests->harvests($cycle);
            foreach ($registeredHarvests as $registered) {
                $registeredUnit = $this->harvests->productUnit((int) $registered['id_producto']);
                if ($registeredUnit !== null && $registeredUnit !== $unit) {
                    throw new HttpException('VALIDATION_ERROR', 'Las cosechas del ciclo deben usar la misma unidad de medida.', 422, ['field' => 'id_producto']);
                }
            }
            $quantity = (float) $input['cantidad'];
            $date = $input['fecha'] ?? date('Y-m-d');
            $harvest = $this->harvests->createHarvest($cycle, ['date' => $date, 'quantity' => $quantity, 'unit' => $unit, 'notes' => $input['observaciones'] ?? null]);
            $harvested = 0.0;
            foreach ($registeredHarvests as $registered) {
                $harvested += (float) $registered['cantidad'];
            }
            $harvested += $quantity;
            $estimated = $locked['cantidad_estimada'] !== null ? (float) $locked['cantidad_estimada'] : null;
            $provisionalCost = ProvisionalCost::estimate($this->harvests->costs($cycle), $estimated, $harvested);
            $lot = $this->harvests->createLot(['product' => $productId, 'harvest' => $harvest, 'date' => $date, 'quantity' => $quantity, 'cost' => $provisionalCost]);
            $this->harvests->updateProvisionalLotsCost($cycle, $provisionalCost);
            $movement = $this->harvests->movement(['product' => $productId, 'lot' => $lot, 'quantity' => $quantity, 'harvest' => $harvest, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
            $this->audit($actor, 'RegistrarCosecha', $harvest, ['lote' => $lot, 'movimiento' => $movement, 'costo_provisional' => $provisionalCost, 'unidad' => $unit]);
            $this->pdo->commit();
            return ['id_cosecha' => $harvest, 'id_lote_produccion' => $lot, 'id_movimiento_inventario' => $movement, 'costo_unitario' => $provisionalCost, 'unidad_medida' => $unit, 'es_costo_provisional' => true];
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw new HttpException('CONFLICT', 'El producto o ciclo indicado no existe.', 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function consolidate(int $cycle, int $actor): array
    {
        $this->pdo->beginTransaction();
        try {
            $locked = $this->harvests->lockCycle($cycle);
            if ($locked === null) {
                throw new HttpException('NOT_FOUND', 'El ciclo no existe.', 404);
            }
            if ($locked['estado'] !== 'EnCurso') {
                throw new HttpException('CONFLICT', 'Solo un ciclo EnCurso puede consolidarse.', 409);
            }
            $harvests = $this->harvests->harvests($cycle);
            if ($harvests === []) {
                throw new HttpException('CONFLICT', 'El ciclo no tiene cosechas.', 409);
            }
            $totalQuantity = array_sum(array_map(static fn (array $harvest): float => (float) $harvest['cantidad'], $harvests));
            if ($totalQuantity <= 0) {
                throw new HttpException('CONFLICT', 'La cantidad cosechada no es valida.', 409);
            }
            $unitCost = $this->harvests->costs($cycle) / $totalQuantity;
            $lots = $this->harvests->lots($cycle);
            foreach ($lots as $lot) {
                if ((int) $lot['es_costo_provisional'] === 1) {
                    $this->harvests->updateLotCost((int) $lot['id_lote_produccion'], $unitCost);
                }
            }
            $this->harvests->closeCycle($cycle);
            $this->audit($actor, 'ConsolidarCiclo', $cycle, ['costo_unitario' => $unitCost, 'cantidad' => $totalQuantity]);
            $this->pdo->commit();
            return ['id_ciclo_cultivo' => $cycle, 'costo_unitario' => $unitCost, 'cantidad_cosechada' => $totalQuantity, 'lotes_actualizados' => count($lots)];
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function audit(int $actor, string $action, int $id, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => 'ciclos_cultivo', 'entity_id' => $id, 'values' => json_encode($values)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
