<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\SaleRepository;
use PDO;
use PDOException;

final class SaleService
{
    public function __construct(private readonly PDO $pdo, private readonly SaleRepository $sales)
    {
    }

    public function list(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->sales->list(($page - 1) * $perPage, $perPage, $filters);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $sale = $this->sales->find($id);
        if ($sale === null) {
            throw new HttpException('NOT_FOUND', 'La venta no existe.', 404);
        }

        return ['venta' => $sale, 'detalles' => $this->sales->details($id), 'cxc' => $this->sales->receivableBySale($id)];
    }

    public function lots(): array
    {
        return $this->sales->availableLots();
    }

    public function create(array $input, int $actor): array
    {
        if (!is_numeric($input['id_cliente'] ?? null) || !in_array($input['tipo_pago'] ?? null, ['Contado', 'Credito'], true) || !is_array($input['detalles'] ?? null) || $input['detalles'] === []) {
            throw new HttpException('VALIDATION_ERROR', 'Cliente, tipo de pago y detalles son obligatorios.', 422);
        }
        $date = $input['fecha'] ?? date('Y-m-d');
        $dateObject = date_create_from_format('Y-m-d', (string) $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422);
        }
        $this->pdo->beginTransaction();
        try {
            $details = [];
            $subtotal = 0.0;
            $tax = 0.0;
            $hasProvisional = false;
            foreach ($input['detalles'] as $detail) {
                if (!is_array($detail) || !is_numeric($detail['id_lote_produccion'] ?? null) || !is_numeric($detail['cantidad'] ?? null) || (float) $detail['cantidad'] <= 0) {
                    throw new HttpException('VALIDATION_ERROR', 'Los detalles de venta no son validos.', 422);
                }
                $lot = $this->sales->lockLot((int) $detail['id_lote_produccion']);
                if ($lot === null) {
                    throw new HttpException('NOT_FOUND', 'El lote no existe.', 404);
                }
                $quantity = (float) $detail['cantidad'];
                if ($quantity > (float) $lot['cantidad_disponible']) {
                    throw new HttpException('CONFLICT', 'El lote no tiene disponibilidad suficiente.', 409);
                }
                $product = $this->sales->product((int) $detail['id_producto']);
                if ($product === null || (int) $detail['id_producto'] !== (int) ($lot['id_producto'])) {
                    throw new HttpException('CONFLICT', 'El producto no coincide con el lote.', 409);
                }
                $rate = (float) $product['porcentaje_alicuota'];
                $unit = (string) $product['unidad_medida'];
                $cost = (float) $lot['costo_unitario'];
                $hasProvisional = $hasProvisional || (int) $lot['es_costo_provisional'] === 1;
                $priceInput = $detail['precio_bruto'] ?? null;
                if ($priceInput !== null && $priceInput !== '') {
                    if (!is_numeric($priceInput) || (float) $priceInput <= 0) {
                        throw new HttpException('VALIDATION_ERROR', 'El precio no es valido.', 422, ['field' => 'precio_bruto']);
                    }
                    $gross = round((float) $priceInput, 2);
                    if ($gross < $cost) {
                        throw new HttpException('VALIDATION_ERROR', 'El precio no puede ser menor al costo del lote.', 422, ['field' => 'precio_bruto']);
                    }
                    $margin = round(1 - ($cost / $gross), 6);
                    if ($margin < 0 || $margin >= 1) {
                        throw new HttpException('VALIDATION_ERROR', 'El margen resultante no es valido.', 422, ['field' => 'precio_bruto']);
                    }
                } else {
                    if (!is_numeric($detail['porcentaje_rentabilidad'] ?? null)) {
                        throw new HttpException('VALIDATION_ERROR', 'Los detalles de venta no son validos.', 422);
                    }
                    $margin = (float) $detail['porcentaje_rentabilidad'];
                    if ($margin < 0 || $margin >= 1) {
                        throw new HttpException('VALIDATION_ERROR', 'El margen debe estar entre 0 y 99.99%.', 422);
                    }
                    $gross = $cost / (1 - $margin);
                }
                $lineSubtotal = $gross * $quantity;
                $lineTax = $lineSubtotal * ($rate / 100);
                $lineTotal = $lineSubtotal + $lineTax;
                $subtotal += $lineSubtotal;
                $tax += $lineTax;
                $details[] = ['product' => (int) $detail['id_producto'], 'lot' => (int) $detail['id_lote_produccion'], 'quantity' => $quantity, 'unit' => $unit, 'cost' => $cost, 'margin' => $margin, 'gross' => $gross, 'rate' => $rate, 'subtotal' => $lineSubtotal, 'tax' => $lineTax, 'net' => $gross * (1 + $rate / 100), 'before' => (float) $lot['cantidad_disponible'], 'after' => (float) $lot['cantidad_disponible'] - $quantity];
            }
            if ($hasProvisional && !$this->flag($input['acepta_costo_provisional'] ?? null)) {
                throw new HttpException('VALIDATION_ERROR', 'Debe aceptar la venta con costo provisional.', 422, ['field' => 'acepta_costo_provisional']);
            }
            $total = $subtotal + $tax;
            $sale = $this->sales->create(['client' => (int) $input['id_cliente'], 'date' => $date, 'payment_type' => $input['tipo_pago'], 'state' => $input['tipo_pago'] === 'Contado' ? 'Cobrado' : 'Pendiente', 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total, 'notes' => $input['observaciones'] ?? null]);
            foreach ($details as $detail) {
                $this->sales->detail(['sale' => $sale, 'product' => $detail['product'], 'lot' => $detail['lot'], 'quantity' => $detail['quantity'], 'unit' => $detail['unit'], 'cost' => $detail['cost'], 'margin' => $detail['margin'], 'gross' => $detail['gross'], 'rate' => $detail['rate'], 'subtotal' => $detail['subtotal'], 'tax' => $detail['tax'], 'net' => $detail['net']]);
                $this->sales->updateLot($detail['lot'], $detail['after']);
                $this->sales->movement(['product' => $detail['product'], 'lot' => $detail['lot'], 'quantity' => $detail['quantity'], 'before' => $detail['before'], 'after' => $detail['after'], 'origin' => $sale, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
            }
            if ($input['tipo_pago'] === 'Contado') {
                $balance = $this->sales->lockCash();
                $this->sales->updateCash($balance + $total);
                $this->sales->cashMovement(['amount' => $total, 'origin' => $sale, 'user_id' => $actor, 'notes' => $input['observaciones'] ?? null]);
            } else {
                $this->sales->receivable(['sale' => $sale, 'date' => $date, 'amount' => $total]);
            }
            $this->audit($actor, $sale, ['total' => $total, 'tipo_pago' => $input['tipo_pago']]);
            $this->pdo->commit();
            return ['id_venta' => $sale, 'subtotal' => $subtotal, 'importe_alicuota' => $tax, 'total' => $total, 'tipo_pago' => $input['tipo_pago']];
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw new HttpException('CONFLICT', 'El cliente, producto o lote indicado no existe.', 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function flag(mixed $value): bool
    {
        return $value === '1' || $value === 1 || $value === true || $value === 'on';
    }

    private function audit(int $actor, int $id, array $values): void
    {
        $query = $this->pdo->prepare("INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, 'Registrar', 'ventas', :entity_id, :values)");
        $query->execute(['user_id' => $actor, 'entity_id' => $id, 'values' => json_encode($values)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
