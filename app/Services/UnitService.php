<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DatabaseError;
use App\Core\HttpException;
use App\Repositories\UnitRepository;
use PDO;
use PDOException;

final class UnitService
{
    public function __construct(private readonly PDO $pdo, private readonly UnitRepository $repository)
    {
    }

    public function list(int $page, int $perPage, ?bool $active, string $search): array
    {
        $result = $this->repository->list(($page - 1) * $perPage, $perPage, $active, $search);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $unit = $this->repository->find($id);
        if ($unit === null) {
            throw new HttpException('NOT_FOUND', 'La unidad no existe.', 404);
        }

        return $unit;
    }

    public function save(array $input, int $actor, ?int $id = null): array
    {
        $before = $id === null ? null : $this->get($id);
        $data = $this->validate($input);
        try {
            $this->pdo->beginTransaction();
            $savedId = $this->repository->save($data, $id);
            $this->audit($actor, $id === null ? 'Crear' : 'Modificar', $savedId, $before, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'La unidad ya existe.');
            }
            throw $exception;
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
            throw new HttpException('CONFLICT', 'La unidad ya esta desactivada.', 409);
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
        if (!isset($input['nombre']) || !is_string($input['nombre']) || trim($input['nombre']) === '') {
            throw new HttpException('VALIDATION_ERROR', 'El nombre es obligatorio.', 422, ['field' => 'nombre']);
        }

        return [
            'nombre' => trim($input['nombre']),
            'descripcion' => isset($input['descripcion']) ? trim((string) $input['descripcion']) : null,
        ];
    }

    private function audit(int $actor, string $action, int $id, ?array $before, array $after): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :before, :after)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => 'unidades', 'entity_id' => $id, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
