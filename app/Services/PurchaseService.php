<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\PurchaseRepository;
use PDO;
use PDOException;

final class PurchaseService
{
    public function __construct(private readonly PDO $pdo, private readonly PurchaseRepository $purchases)
    {
    }

    public function list(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->purchases->list(($page - 1) * $perPage, $perPage, $filters);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $purchase = $this->purchases->find($id);
        if ($purchase === null) {
            throw new HttpException('NOT_FOUND', 'La compra no existe.', 404);
        }

        return ['compra' => $purchase, 'detalles' => $this->purchases->details($id), 'cxp' => $this->purchases->payable($id)];
    }

    public function create(array $input, int $actor): array
    {
        if (!is_numeric($input['id_proveedor'] ?? null) || !in_array($input['tipo_pago'] ?? null, ['Contado', 'Credito'], true) || !is_array($input['detalles'] ?? null) || $input['detalles'] === []) {
            throw new HttpException('VALIDATION_ERROR', 'Proveedor, tipo de pago y detalles son obligatorios.', 422);
        }
        $date = $input['fecha'] ?? date('Y-m-d');
        $dateObject = date_create_from_format('Y-m-d', (string) $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $total = 0.0;
            $validated = [];
            foreach ($input['detalles'] as $detail) {
                if (!is_array($detail) || !is_numeric($detail['id_insumo'] ?? null) || !is_numeric($detail['cantidad'] ?? null) || (float) $detail['cantidad'] <= 0 || !is_numeric($detail['costo_unitario'] ?? null) || (float) $detail['costo_unitario'] < 0 || trim((string) ($detail['unidad_medida'] ?? '')) === '') {
                    throw new HttpException('VALIDATION_ERROR', 'Los detalles de compra no son validos.', 422);
                }
                $quantity = (float) $detail['cantidad'];
                $unitCost = (float) $detail['costo_unitario'];
                $lineTotal = $quantity * $unitCost;
                $total += $lineTotal;
                $validated[] = ['id' => (int) $detail['id_insumo'], 'quantity' => $quantity, 'unit' => trim($detail['unidad_medida']), 'unit_cost' => $unitCost, 'line_total' => $lineTotal];
            }
            $purchase = $this->purchases->create(['supplier' => (int) $input['id_proveedor'], 'date' => $date, 'payment_type' => $input['tipo_pago'], 'state' => $input['tipo_pago'] === 'Contado' ? 'Pagado' : 'Pendiente', 'subtotal' => $total, 'total' => $total, 'notes' => $input['observaciones'] ?? null]);
            foreach ($validated as $detail) {
                $this->purchases->detail(['purchase' => $purchase, 'input' => $detail['id'], 'quantity' => $detail['quantity'], 'unit' => $detail['unit'], 'unit_cost' => $detail['unit_cost'], 'line_total' => $detail['line_total']]);
                $stock = $this->purchases->lockInput($detail['id']);
                if ($stock === null) {
                    throw new HttpException('NOT_FOUND', 'El insumo no existe.', 404);
                }
                $oldStock = (float) $stock['stock_actual'];
                $oldCost = (float) $stock['costo_unitario_actual'];
                $newStock = $oldStock + $detail['quantity'];
                $newCost = $newStock == 0 ? $detail['unit_cost'] : (($oldStock * $oldCost) + ($detail['quantity'] * $detail['unit_cost'])) / $newStock;
                $this->purchases->updateInput($detail['id'], $newStock, $newCost);
                $this->purchases->movement(['input' => $detail['id'], 'quantity' => $detail['quantity'], 'before' => $oldStock, 'after' => $newStock, 'origin' => $purchase, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
            }
            if ($input['tipo_pago'] === 'Contado') {
                $balance = $this->purchases->lockCash();
                if ($balance < $total) {
                    throw new HttpException('CONFLICT', 'El saldo de Caja es insuficiente.', 409);
                }
                $this->purchases->updateCash($balance - $total);
                $this->purchases->cashMovement(['amount' => $total, 'origin' => $purchase, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
            } else {
                $this->purchases->receivable(['purchase' => $purchase, 'date' => $date, 'amount' => $total]);
            }
            $this->audit($actor, $purchase, ['total' => $total, 'tipo_pago' => $input['tipo_pago']]);
            $this->pdo->commit();
            return ['id_compra' => $purchase, 'total' => $total, 'tipo_pago' => $input['tipo_pago']];
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw new HttpException('CONFLICT', 'El proveedor o insumo indicado no existe.', 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function audit(int $actor, int $id, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, \'Registrar\', \'compras\', :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'entity_id' => $id, 'values' => json_encode($values)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
