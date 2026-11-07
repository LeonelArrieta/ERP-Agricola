<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\PurchaseCancellationRepository;
use PDO;

final class PurchaseCancellationService
{
    public function __construct(private readonly PDO $pdo, private readonly PurchaseCancellationRepository $repository) {}

    public function cancel(int $id, int $actor): array
    {
        $this->pdo->beginTransaction();
        try {
            $purchase=$this->repository->purchase($id);
            if ($purchase===null) throw new HttpException('NOT_FOUND','La compra no existe.',404);
            if ($purchase['estado']==='Cancelado') throw new HttpException('CONFLICT','La compra ya esta cancelada.',409);
            foreach ($this->repository->details($id) as $detail) {
                $movement=$this->repository->movement($id,(int)$detail['id_insumo']);
                if ($movement===null || $this->repository->dependentMovements((int)$detail['id_insumo'],(int)$movement['id_movimiento_inventario'])>0) throw new HttpException('CONFLICT','La compra tiene movimientos posteriores dependientes.',409);
                $input=$this->repository->input((int)$detail['id_insumo']);
                $before=(float)$input['stock_actual']; $quantity=(float)$detail['cantidad']; $after=$before-$quantity;
                if ($after<0) throw new HttpException('CONFLICT','La reversión produciria stock negativo.',409);
                $costs=$this->repository->reconstructedCost((int)$detail['id_insumo'],$id); $remaining=(float)$costs[0]; $newCost=$remaining>0?(float)$costs[1]/$remaining:0;
                $this->repository->updateInput((int)$detail['id_insumo'],$after,$newCost);
                $this->repository->reverseMovement(['input'=>(int)$detail['id_insumo'],'quantity'=>$quantity,'before'=>$before,'after'=>$after,'origin'=>$id,'user_id'=>$actor,'notes'=>'Cancelacion de compra']);
            }
            $cashReversed=0.0; $account=$this->repository->payable($id);
            if($account!==null){$cashReversed=$this->repository->paid((int)$account['id_cuenta_por_pagar']);$this->repository->cancelPayable((int)$account['id_cuenta_por_pagar']);}
            elseif($purchase['tipo_pago']==='Contado')$cashReversed=(float)$purchase['total'];
            if($cashReversed>0){$cash=$this->repository->cash();if($cash<$cashReversed)throw new HttpException('CONFLICT','Caja insuficiente para revertir la compra.',409);$this->repository->updateCash($cash+$cashReversed);$this->repository->cashReversal(['amount'=>$cashReversed,'origin'=>$id,'user_id'=>$actor,'notes'=>'Cancelacion de compra']);}
            $this->repository->cancelPurchase($id); $this->audit($actor,$id,['caja_revertida'=>$cashReversed]); $this->pdo->commit();
            return ['id_compra'=>$id,'estado'=>'Cancelado','caja_revertida'=>$cashReversed];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function audit(int $actor,int $id,array $values):void{$q=$this->pdo->prepare("INSERT INTO registros_auditoria(id_usuario,accion,entidad,id_entidad,valores_nuevos) VALUES(:user_id,'Cancelar','compras',:entity_id,:values)");$q->execute(['user_id'=>$actor,'entity_id'=>$id,'values'=>json_encode($values)]);}
}
