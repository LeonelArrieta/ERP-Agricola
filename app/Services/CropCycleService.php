<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CropCycleRepository;
use App\Repositories\HarvestRepository;
use PDO;
use PDOException;

final class CropCycleService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly CropCycleRepository $cycles,
        private readonly HarvestRepository $harvests,
    ) {
    }

    public function list(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->cycles->list(($page - 1) * $perPage, $perPage, $filters);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $cycle = $this->cycles->find($id);
        if ($cycle === null) {
            throw new HttpException('NOT_FOUND', 'El ciclo de cultivo no existe.', 404);
        }
        return $cycle;
    }

    public function create(array $input, int $actor): array
    {
        $data = $this->validate($input);
        try {
            $this->pdo->beginTransaction();
            $id = $this->cycles->create($data);
            $this->audit($actor, 'Crear', $id, null, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw new HttpException('CONFLICT', 'El cultivo o parcela indicado no existe.', 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    public function updateEstimate(int $id, array $input, int $actor): array
    {
        $cycle = $this->get($id);
        if (!in_array((string) $cycle['estado'], ['Planificado', 'EnCurso'], true)) {
            throw new HttpException('CONFLICT', 'Solo un ciclo Planificado o EnCurso admite cambios de produccion estimada.', 409);
        }
        [$quantity, $unit] = $this->estimate($input);
        $this->pdo->beginTransaction();
        try {
            $this->cycles->updateEstimate($id, $quantity, $unit);
            $harvested = 0.0;
            foreach ($this->harvests->harvests($id) as $harvest) {
                $harvested += (float) $harvest['cantidad'];
            }
            if ($harvested > 0) {
                $unitCost = ProvisionalCost::estimate($this->harvests->costs($id), $quantity, $harvested);
                $this->harvests->updateProvisionalLotsCost($id, $unitCost);
            }
            $this->audit($actor, 'ActualizarEstimacion', $id, $cycle, ['cantidad_estimada' => $quantity, 'unidad_estimada' => $unit]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get($id);
    }

    public function transition(int $id, string $target, int $actor): array
    {
        $this->pdo->beginTransaction();
        try {
            $cycle = $this->cycles->lock($id);
            if ($cycle === null) {
                throw new HttpException('NOT_FOUND', 'El ciclo de cultivo no existe.', 404);
            }
            $allowed = match ($target) {
                'EnCurso' => $cycle['estado'] === 'Planificado',
                'Cancelado' => in_array($cycle['estado'], ['Planificado', 'EnCurso'], true),
                default => false,
            };
            if (!$allowed) {
                throw new HttpException('CONFLICT', 'La transicion del ciclo no es valida.', 409);
            }
            $this->cycles->state($id, $target, $target === 'Cancelado' ? date('Y-m-d') : null);
            $this->audit($actor, 'CambiarEstado', $id, $cycle, ['estado' => $target]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    private function validate(array $input): array
    {
        foreach (['id_cultivo', 'id_parcela', 'fecha_inicio'] as $field) {
            if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
                throw new HttpException('VALIDATION_ERROR', 'Falta un campo obligatorio.', 422, ['field' => $field]);
            }
        }
        $date = date_create_from_format('Y-m-d', (string) $input['fecha_inicio']);
        if (!is_numeric($input['id_cultivo']) || !is_numeric($input['id_parcela']) || !$date || $date->format('Y-m-d') !== $input['fecha_inicio']) {
            throw new HttpException('VALIDATION_ERROR', 'Los datos del ciclo no son validos.', 422);
        }
        [$quantity, $unit] = $this->estimate($input);

        return [
            'cultivo' => (int) $input['id_cultivo'],
            'parcela' => (int) $input['id_parcela'],
            'fecha' => $input['fecha_inicio'],
            'cantidad_estimada' => $quantity,
            'unidad_estimada' => $unit,
            'observaciones' => $input['observaciones'] ?? null,
        ];
    }

    private function estimate(array $input): array
    {
        $quantity = $input['cantidad_estimada'] ?? null;
        $unit = $input['unidad_estimada'] ?? null;
        $quantity = ($quantity === null || $quantity === '') ? null : $quantity;
        $unit = ($unit === null || $unit === '') ? null : trim((string) $unit);
        if ($quantity === null && $unit === null) {
            return [null, null];
        }
        if ($quantity === null || $unit === null || !is_numeric($quantity) || (float) $quantity <= 0) {
            throw new HttpException('VALIDATION_ERROR', 'La produccion estimada requiere cantidad y unidad validas.', 422, ['field' => 'cantidad_estimada']);
        }

        return [round((float) $quantity, 3), $unit];
    }

    private function audit(int $actor, string $action, int $id, ?array $before, array $after): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :before, :after)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => 'ciclos_cultivo', 'entity_id' => $id, 'before' => $before === null ? null : json_encode($before), 'after' => json_encode($after)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
