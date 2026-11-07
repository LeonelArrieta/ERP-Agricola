<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ReversalRepository;
use PDO;

final class ReversalService
{
    public function __construct(private readonly PDO $pdo, private readonly ReversalRepository $reversals)
    {
    }
    public function receipt(int $id, string $reason, int $actor): array { return $this->reverse($id, $reason, $actor, false); }
    public function payment(int $id, string $reason, int $actor): array { return $this->reverse($id, $reason, $actor, true); }

    private function reverse(int $id, string $reason, int $actor, bool $payment): array
    {
        if ($reason === '') throw new HttpException('VALIDATION_ERROR', 'El motivo es obligatorio.', 422);
        $this->pdo->beginTransaction();
        try {
            $original = $payment ? $this->reversals->payment($id) : $this->reversals->receipt($id);
            if ($original === null) throw new HttpException('NOT_FOUND', 'La operación financiera no existe.', 404);
            if ($payment ? $this->reversals->alreadyPayment($id) : $this->reversals->alreadyReceipt($id)) throw new HttpException('CONFLICT', 'La operación ya fue revertida.', 409);
            $account = $payment ? $this->reversals->accountPayable((int) $original['id_cuenta_por_pagar']) : $this->reversals->accountReceivable((int) $original['id_cuenta_por_cobrar']);
            if ($account === null) throw new HttpException('CONFLICT', 'La cuenta financiera asociada no existe.', 409);
            $old = (float) $account['saldo_actual']; $new = $old + (float) $original['importe'];
            $state = $new >= (float) $account['importe_original'] ? 'Pendiente' : ($payment ? 'Parcialmente_Pagada' : 'Parcialmente_Cobrada');
            $cash = $this->reversals->cash();
            if (!$payment && $cash < (float) $original['importe']) throw new HttpException('CONFLICT', 'Caja no puede absorber la reversión.', 409);
            $cash += $payment ? (float) $original['importe'] : -(float) $original['importe'];
            $movement = $this->reversals->cashMovement(['type' => $payment ? 'ReversionEgreso' : 'ReversionIngreso', 'amount' => $original['importe'], 'origin_type' => $payment ? 'ReversionPago' : 'ReversionCobro', 'origin' => $id, 'user_id' => $actor, 'concept' => $payment ? 'Reversion de pago' : 'Reversion de cobro', 'notes' => $reason]);
            $data = ['original' => $id, 'user_id' => $actor, 'reason' => $reason, 'movement' => $movement];
            $reversal = $payment ? $this->reversals->createPaymentReversal($data) : $this->reversals->createReceiptReversal($data);
            if ($payment) $this->reversals->updatePayable((int) $account['id_cuenta_por_pagar'], $new, $state); else $this->reversals->updateReceivable((int) $account['id_cuenta_por_cobrar'], $new, $state);
            $this->reversals->updateCash($cash); $this->pdo->commit();
            return ['id_reversion' => $reversal, 'id_movimiento_caja' => $movement, 'saldo_actual' => $new, 'estado' => $state];
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
