<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\WorkerRepository;
use PDO;

final class WorkerService
{
    public function __construct(private readonly PDO $pdo, private readonly WorkerRepository $repository)
    {
    }

    public function list(int $page, int $perPage, ?bool $active, string $search): array
    {
        $result = $this->repository->list(($page - 1) * $perPage, $perPage, $active, $search);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $worker = $this->repository->find($id);
        if ($worker === null) {
            throw new HttpException('NOT_FOUND', 'El trabajador no existe.', 404);
        }

        return $worker;
    }

    public function save(array $input, int $actor, ?int $id = null): array
    {
        $before = $id === null ? null : $this->get($id);
        $data = $this->validate($input);
        $this->pdo->beginTransaction();
        try {
            $savedId = $this->repository->save($data, $id);
            $this->audit($actor, $id === null ? 'Crear' : 'Modificar', $savedId, $before, $data);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get($savedId);
    }

    public function deactivate(int $id, int $actor): array
    {
        $before = $this->get($id);
        if (!(int) $before['activo']) {
            throw new HttpException('CONFLICT', 'El trabajador ya esta desactivado.', 409);
        }
        $this->pdo->beginTransaction();
        try {
            $this->repository->deactivate($id);
            $this->audit($actor, 'Desactivar', $id, $before, ['activo' => 0]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get($id);
    }

    public function activate(int $id, int $actor): array
    {
        $before = $this->get($id);
        if ((int) $before['activo'] === 1) {
            return $before;
        }
        $this->pdo->beginTransaction();
        try {
            $this->repository->activate($id);
            $this->audit($actor, 'Activar', $id, $before, ['activo' => 1]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get($id);
    }

    public function options(): array
    {
        return $this->repository->activeOptions();
    }

    private function validate(array $input): array
    {
        foreach (['nombre', 'apellido'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
                throw new HttpException('VALIDATION_ERROR', 'Nombre y apellido son obligatorios.', 422, ['field' => $field]);
            }
        }
        $cost = $input['costo_hora_actual'] ?? null;
        if (!is_numeric($cost) || (float) $cost < 0) {
            throw new HttpException('VALIDATION_ERROR', 'El costo por hora debe ser no negativo.', 422, ['field' => 'costo_hora_actual']);
        }

        return [
            'nombre' => trim((string) $input['nombre']),
            'apellido' => trim((string) $input['apellido']),
            'documento' => isset($input['documento']) && trim((string) $input['documento']) !== '' ? trim((string) $input['documento']) : null,
            'telefono' => isset($input['telefono']) && trim((string) $input['telefono']) !== '' ? trim((string) $input['telefono']) : null,
            'costo_hora' => (float) $cost,
        ];
    }

    private function audit(int $actor, string $action, int $id, ?array $before, array $after): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :before, :after)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => 'trabajadores', 'entity_id' => $id, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
