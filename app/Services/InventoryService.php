<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\InventoryRepository;
use PDO;

final class InventoryService
{
    public function __construct(private readonly PDO $pdo, private readonly InventoryRepository $inventory)
    {
    }

    public function movements(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->inventory->movements(($page - 1) * $perPage, $perPage, $filters);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function merma(array $input, int $actor): array
    {
        $id = $input['id_insumo'] ?? null;
        $quantity = $input['cantidad'] ?? null;
        $notes = $input['motivo'] ?? null;
        if (!is_numeric($id) || (int) $id < 1 || !is_numeric($quantity) || (float) $quantity <= 0 || !is_string($notes) || trim($notes) === '') {
            throw new HttpException('VALIDATION_ERROR', 'Insumo, cantidad y motivo son obligatorios.', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $insumo = $this->inventory->lockInsumo((int) $id);
            if ($insumo === null) {
                throw new HttpException('NOT_FOUND', 'El insumo no existe.', 404);
            }
            $before = (float) $insumo['stock_actual'];
            $quantity = (float) $quantity;
            if ($quantity > $before) {
                throw new HttpException('CONFLICT', 'El stock disponible es insuficiente.', 409);
            }
            $after = $before - $quantity;
            $movementId = $this->inventory->movement([
                'id_insumo' => (int) $id,
                'tipo' => 'SalidaPorMerma',
                'cantidad' => $quantity,
                'before' => $before,
                'after' => $after,
                'origin_type' => 'Merma',
                'origin_id' => null,
                'user_id' => $actor,
                'notes' => trim($notes),
            ]);
            $this->inventory->updateStock((int) $id, $after);
            $this->audit($actor, $movementId, ['id_insumo' => (int) $id, 'cantidad' => $quantity, 'motivo' => trim($notes)]);
            $this->pdo->commit();
            return ['id_movimiento_inventario' => $movementId, 'stock_anterior' => $before, 'stock_resultante' => $after];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function audit(int $actor, int $movementId, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'action' => 'RegistrarMerma', 'entity' => 'movimientos_inventario', 'entity_id' => $movementId, 'values' => json_encode($values, JSON_THROW_ON_ERROR)]);
    }
}
