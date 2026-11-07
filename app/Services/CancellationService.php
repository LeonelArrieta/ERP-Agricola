<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CancellationRepository;
use PDO;

final class CancellationService
{
    public function __construct(private readonly PDO $pdo, private readonly CancellationRepository $cancellations)
    {
    }

    public function sale(int $saleId, int $actor): array
    {
        $this->pdo->beginTransaction();
        try {
            $sale = $this->cancellations->sale($saleId);
            if ($sale === null) {
                throw new HttpException('NOT_FOUND', 'La venta no existe.', 404);
            }
            if ($sale['estado'] === 'Cancelado') {
                throw new HttpException('CONFLICT', 'La venta ya esta cancelada.', 409);
            }
            foreach ($this->cancellations->details($saleId) as $detail) {
                $lot = $this->cancellations->lot((int) $detail['id_lote_produccion']);
                if ($lot === null) {
                    throw new HttpException('CONFLICT', 'No se puede revertir un lote inexistente.', 409);
                }
                $before = (float) $lot['cantidad_disponible'];
                $after = $before + (float) $detail['cantidad'];
                if ($after > (float) $lot['cantidad_inicial']) {
                    throw new HttpException('CONFLICT', 'La reversión produciria stock invalido.', 409);
                }
                $this->cancellations->updateLot((int) $detail['id_lote_produccion'], $after);
                $this->cancellations->inventoryReversal(['product' => $detail['id_producto'], 'lot' => $detail['id_lote_produccion'], 'quantity' => $detail['cantidad'], 'before' => $before, 'after' => $after, 'origin' => $saleId, 'user_id' => $actor, 'notes' => 'Cancelacion de venta']);
            }
            $cashReversed = 0.0;
            $account = $this->cancellations->receivable($saleId);
            if ($account !== null) {
                $cashReversed = $this->cancellations->collected((int) $account['id_cuenta_por_cobrar']);
                $this->cancellations->cancelReceivable((int) $account['id_cuenta_por_cobrar']);
            } elseif ($sale['tipo_pago'] === 'Contado') {
                $cashReversed = (float) $sale['total'];
            }
            if ($cashReversed > 0) {
                $cash = $this->cancellations->cash();
                if ($cash < $cashReversed) {
                    throw new HttpException('CONFLICT', 'El saldo de Caja es insuficiente para revertir la venta.', 409);
                }
                $this->cancellations->updateCash($cash - $cashReversed);
                $this->cancellations->cashReversal(['amount' => $cashReversed, 'origin' => $saleId, 'user_id' => $actor, 'notes' => 'Cancelacion de venta']);
            }
            $this->cancellations->cancelSale($saleId);
            $this->audit($actor, $saleId, ['caja_revertida' => $cashReversed]);
            $this->pdo->commit();
            return ['id_venta' => $saleId, 'estado' => 'Cancelado', 'caja_revertida' => $cashReversed];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function audit(int $actor, int $id, array $values): void
    {
        $query = $this->pdo->prepare("INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, 'Cancelar', 'ventas', :entity_id, :values)");
        $query->execute(['user_id' => $actor, 'entity_id' => $id, 'values' => json_encode($values)]);
    }
}
