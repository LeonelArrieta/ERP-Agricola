<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CropTaskRepository;
use PDO;
use PDOException;

final class CropTaskService
{
    public function __construct(private readonly PDO $pdo, private readonly CropTaskRepository $tasks)
    {
    }

    public function list(int $cycleId): array
    {
        return $this->tasks->list($cycleId);
    }

    public function listAll(int $page, int $perPage, ?string $state, string $search): array
    {
        $result = $this->tasks->listAll(($page - 1) * $perPage, $perPage, $state, $search);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function detail(int $id): array
    {
        $task = $this->tasks->detail($id);
        if ($task === null) {
            throw new HttpException('NOT_FOUND', 'La tarea de cultivo no existe.', 404);
        }

        return [
            'tarea' => $task,
            'consumos' => $this->tasks->consumptions($id),
            'trabajos' => $this->tasks->works($id),
            'costos' => $this->tasks->taskCosts($id),
        ];
    }

    public function works(int $id): array
    {
        $this->get($id);

        return $this->tasks->works($id);
    }

    public function costsByCycle(int $cycleId): array
    {
        return $this->tasks->costsByCycle($cycleId);
    }

    public function create(int $cycleId, array $input, int $actor): array
    {
        $date = $this->taskDate($input['fecha'] ?? null);
        $this->requireTaskType($input['id_tarea'] ?? null);
        $cycleState = $this->requireCycle($cycleId);

        try {
            $this->pdo->beginTransaction();
            $id = $this->tasks->create(['cycle' => $cycleId, 'task' => (int) $input['id_tarea'], 'date' => $date, 'notes' => $input['observaciones'] ?? null]);
            $this->audit($actor, 'Crear', $id, ['id_ciclo_cultivo' => $cycleId]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw new HttpException('CONFLICT', 'El ciclo o tarea indicado no existe.', 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get($id);
    }

    /**
     * Alta combinada: crea la tarea y, en una sola transaccion, la inicia,
     * registra insumos y mano de obra y opcionalmente la completa.
     */
    public function createWithResources(int $cycleId, array $input, int $actor): array
    {
        $date = $this->taskDate($input['fecha'] ?? null);
        $this->requireTaskType($input['id_tarea'] ?? null);
        $insumos = $this->insumoLines($input['detalles_insumos'] ?? []);
        $trabajos = $this->workLines($input['detalles_trabajos'] ?? []);
        $start = $this->flag($input['iniciar'] ?? null);
        $complete = $this->flag($input['completar'] ?? null);
        $hasResources = $insumos !== [] || $trabajos !== [];

        if (($hasResources || $complete) && !$start) {
            throw new HttpException('VALIDATION_ERROR', 'Para cargar recursos o completar la tarea debe iniciarla.', 422, ['field' => 'iniciar']);
        }
        if ($complete && !$start) {
            throw new HttpException('VALIDATION_ERROR', 'No se puede completar una tarea que no se inicia.', 422, ['field' => 'completar']);
        }

        $cycleState = $this->requireCycle($cycleId);
        if (($start || $hasResources || $complete) && $cycleState !== 'EnCurso') {
            throw new HttpException('CONFLICT', 'El ciclo debe estar EnCurso para cargar insumos, mano de obra o iniciar la tarea.', 409);
        }

        try {
            $this->pdo->beginTransaction();
            $id = $this->tasks->create(['cycle' => $cycleId, 'task' => (int) $input['id_tarea'], 'date' => $date, 'notes' => $input['observaciones'] ?? null]);
            $this->audit($actor, 'Crear', $id, ['id_ciclo_cultivo' => $cycleId, 'con_recursos' => $hasResources]);

            if ($start) {
                $this->tasks->state($id, 'EnCurso');
                $this->audit($actor, 'CambiarEstado', $id, ['estado' => 'EnCurso']);
            }

            $consumptions = [];
            foreach ($insumos as $line) {
                $consumptions[] = $this->applyConsumption($id, $line['id'], $line['quantity'], null, $actor, $input['observaciones'] ?? null, $date);
            }

            $works = [];
            foreach ($trabajos as $line) {
                $works[] = $this->applyWork($id, $line['id'], $line['hours'], $line['hour_cost'], $actor, $date);
            }

            if ($complete) {
                $this->tasks->state($id, 'Completada');
                $this->audit($actor, 'CambiarEstado', $id, ['estado' => 'Completada']);
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw new HttpException('CONFLICT', 'El ciclo, la tarea o un recurso indicado no existe.', 409);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get($id) + [
            'resumen' => [
                'consumos' => count($consumptions),
                'trabajos' => count($works),
                'costos' => $this->tasks->taskCosts($id),
            ],
        ];
    }

    public function transition(int $id, string $target, int $actor): array
    {
        $this->pdo->beginTransaction();
        try {
            $task = $this->tasks->findTask($id, true);
            if ($task === null) {
                throw new HttpException('NOT_FOUND', 'La tarea de cultivo no existe.', 404);
            }
            $valid = match ($target) {
                'EnCurso' => $task['estado'] === 'Planificada' && $task['ciclo_estado'] === 'EnCurso',
                'Completada' => $task['estado'] === 'EnCurso',
                'Cancelada' => in_array($task['estado'], ['Planificada', 'EnCurso'], true),
                default => false,
            };
            if (!$valid) {
                throw new HttpException('CONFLICT', 'La transicion de la tarea no es valida.', 409);
            }
            $this->tasks->state($id, $target);
            $this->audit($actor, 'CambiarEstado', $id, ['estado' => $target]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    public function consume(int $taskId, array $input, int $actor): array
    {
        $quantity = $input['cantidad'] ?? null;
        if (!is_numeric($input['id_insumo'] ?? null) || !is_numeric($quantity) || (float) $quantity <= 0 || !is_string($input['unidad_medida'] ?? null) || trim($input['unidad_medida']) === '') {
            throw new HttpException('VALIDATION_ERROR', 'Insumo, cantidad y unidad son obligatorios.', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $this->requireOpenTask($taskId, 'Solo una tarea EnCurso admite consumos.');
            $result = $this->applyConsumption($taskId, (int) $input['id_insumo'], (float) $quantity, trim($input['unidad_medida']), $actor, $input['observaciones'] ?? null, date('Y-m-d'));
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function get(int $id): array
    {
        $task = $this->tasks->findTask($id);
        if ($task === null) {
            throw new HttpException('NOT_FOUND', 'La tarea de cultivo no existe.', 404);
        }
        return $task;
    }

    public function registerWork(int $taskId, array $input, int $actor): array
    {
        $hours = $input['horas_aplicadas'] ?? null;
        if (!is_numeric($input['id_trabajador'] ?? null) || !is_numeric($hours) || (float) $hours <= 0 || !isset($input['fecha'])) {
            throw new HttpException('VALIDATION_ERROR', 'Trabajador, fecha y horas aplicadas son obligatorios.', 422);
        }
        $date = $this->taskDate($input['fecha']);
        if (isset($input['costo_hora']) && (!is_numeric($input['costo_hora']) || (float) $input['costo_hora'] < 0)) {
            throw new HttpException('VALIDATION_ERROR', 'El costo por hora debe ser no negativo.', 422, ['field' => 'costo_hora']);
        }

        $this->pdo->beginTransaction();
        try {
            $this->requireOpenTask($taskId, 'Solo una tarea EnCurso admite trabajo humano.');
            $result = $this->applyWork($taskId, (int) $input['id_trabajador'], (float) $hours, isset($input['costo_hora']) ? (float) $input['costo_hora'] : null, $actor, $date);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function applyConsumption(int $taskId, int $insumoId, float $quantity, ?string $unit, int $actor, ?string $notes, string $date): array
    {
        $insumo = $this->tasks->lockInsumo($insumoId);
        if ($insumo === null) {
            throw new HttpException('NOT_FOUND', 'El insumo no existe.', 404);
        }
        $before = (float) $insumo['stock_actual'];
        if ($quantity > $before) {
            throw new HttpException('CONFLICT', 'El stock disponible es insuficiente.', 409);
        }
        $unit = $unit ?? trim((string) $insumo['unidad_medida']);
        $unitCost = (float) $insumo['costo_unitario_actual'];
        $after = $before - $quantity;
        $consumptionId = $this->tasks->createConsumption(['task' => $taskId, 'input' => $insumoId, 'date' => $date, 'quantity' => $quantity, 'unit' => $unit, 'unit_cost' => $unitCost, 'total' => $quantity * $unitCost]);
        $movementId = $this->tasks->movement(['input' => $insumoId, 'quantity' => $quantity, 'before' => $before, 'after' => $after, 'origin' => $consumptionId, 'user_id' => $actor, 'notes' => $notes]);
        $this->tasks->updateStock($insumoId, $after);
        $this->audit($actor, 'RegistrarConsumo', $consumptionId, ['movimiento' => $movementId, 'costo_unitario' => $unitCost]);

        return ['id_consumo_insumo' => $consumptionId, 'id_movimiento_inventario' => $movementId, 'costo_unitario' => $unitCost, 'costo_total' => $quantity * $unitCost, 'stock_resultante' => $after];
    }

    private function applyWork(int $taskId, int $workerId, float $hours, ?float $hourCost, int $actor, string $date): array
    {
        $worker = $this->tasks->lockWorker($workerId);
        if ($worker === null) {
            throw new HttpException('NOT_FOUND', 'El trabajador no existe o esta inactivo.', 404);
        }
        $hourCost = $hourCost ?? (float) $worker['costo_hora_actual'];
        $total = $hours * $hourCost;
        $workId = $this->tasks->createWork(['task' => $taskId, 'worker' => $workerId, 'date' => $date, 'hours' => $hours, 'hour_cost' => $hourCost, 'total' => $total]);
        $this->audit($actor, 'RegistrarTrabajo', $taskId, ['id_trabajo_humano' => $workId, 'costo_hora' => $hourCost, 'horas_aplicadas' => $hours, 'costo_total' => $total]);

        return ['id_trabajo_humano' => $workId, 'costo_hora' => $hourCost, 'horas_aplicadas' => $hours, 'costo_total' => $total];
    }

    private function requireOpenTask(int $taskId, string $message): array
    {
        $task = $this->tasks->findTask($taskId, true);
        if ($task === null) {
            throw new HttpException('NOT_FOUND', 'La tarea de cultivo no existe.', 404);
        }
        if ($task['estado'] !== 'EnCurso' || $task['ciclo_estado'] === 'Cancelado') {
            throw new HttpException('CONFLICT', $message, 409);
        }

        return $task;
    }

    private function requireCycle(int $cycleId): string
    {
        $cycleState = $this->tasks->cycleState($cycleId);
        if ($cycleState === null) {
            throw new HttpException('NOT_FOUND', 'El ciclo de cultivo no existe.', 404);
        }
        if (!in_array($cycleState, ['Planificado', 'EnCurso'], true)) {
            throw new HttpException('CONFLICT', 'El ciclo no admite nuevas tareas.', 409);
        }

        return $cycleState;
    }

    private function requireTaskType(mixed $value): void
    {
        if (!is_numeric($value)) {
            throw new HttpException('VALIDATION_ERROR', 'Tarea y fecha son obligatorias.', 422, ['field' => 'id_tarea']);
        }
    }

    private function taskDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new HttpException('VALIDATION_ERROR', 'Tarea y fecha son obligatorias.', 422, ['field' => 'fecha']);
        }
        $date = date_create_from_format('Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha no es valida.', 422, ['field' => 'fecha']);
        }

        return (string) $value;
    }

    private function insumoLines(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $lines = [];
        foreach ($value as $line) {
            if (!is_array($line) || !isset($line['id_insumo']) || $line['id_insumo'] === '') {
                continue;
            }
            $quantity = $line['cantidad'] ?? null;
            if (!is_numeric($line['id_insumo']) || !is_numeric($quantity) || (float) $quantity <= 0) {
                throw new HttpException('VALIDATION_ERROR', 'Los insumos a consumir no son validos.', 422, ['field' => 'detalles_insumos']);
            }
            $lines[] = ['id' => (int) $line['id_insumo'], 'quantity' => (float) $quantity];
        }

        return $lines;
    }

    private function workLines(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $lines = [];
        foreach ($value as $line) {
            if (!is_array($line) || !isset($line['id_trabajador']) || $line['id_trabajador'] === '') {
                continue;
            }
            $hours = $line['horas_aplicadas'] ?? null;
            if (!is_numeric($line['id_trabajador']) || !is_numeric($hours) || (float) $hours <= 0) {
                throw new HttpException('VALIDATION_ERROR', 'La mano de obra cargada no es valida.', 422, ['field' => 'detalles_trabajos']);
            }
            $hourCost = $line['costo_hora'] ?? null;
            if ($hourCost !== null && $hourCost !== '' && (!is_numeric($hourCost) || (float) $hourCost < 0)) {
                throw new HttpException('VALIDATION_ERROR', 'El costo por hora debe ser no negativo.', 422, ['field' => 'detalles_trabajos']);
            }
            $lines[] = [
                'id' => (int) $line['id_trabajador'],
                'hours' => (float) $hours,
                'hour_cost' => ($hourCost === null || $hourCost === '') ? null : (float) $hourCost,
            ];
        }

        return $lines;
    }

    private function flag(mixed $value): bool
    {
        return $value === '1' || $value === 1 || $value === true || $value === 'on';
    }

    private function audit(int $actor, string $action, int $id, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, :action, \'tareas_cultivo\', :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity_id' => $id, 'values' => json_encode($values)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
