<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DatabaseError;
use App\Core\HttpException;
use App\Repositories\InventoryCatalogRepository;
use PDO;
use PDOException;

final class InventoryCatalogService
{
    public function __construct(private readonly PDO $pdo, private readonly InventoryCatalogRepository $repository)
    {
    }

    public function list(string $type, int $page, int $perPage, ?bool $active, string $search): array
    {
        $result = $this->repository->list($type, ($page - 1) * $perPage, $perPage, $active, $search);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(string $type, int $id): array
    {
        $value = $this->repository->find($type, $id);
        if ($value === null) {
            throw new HttpException('NOT_FOUND', 'El registro no existe.', 404);
        }
        return $value;
    }

    public function create(string $type, array $input, int $actor): array
    {
        $data = $this->validate($type, $input);
        try {
            $this->pdo->beginTransaction();
            $id = $this->repository->create($type, $data);
            $this->audit($actor, 'Crear', $type, $id, null, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'Ya existe un registro con ese nombre.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($type, $id);
    }

    public function update(string $type, int $id, array $input, int $actor): array
    {
        $before = $this->get($type, $id);
        $data = $this->validate($type, $input);
        try {
            $this->pdo->beginTransaction();
            $this->repository->update($type, $id, $data);
            $this->audit($actor, 'Modificar', $type, $id, $before, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'Ya existe un registro con ese nombre.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($type, $id);
    }

    public function deactivate(string $type, int $id, int $actor): array
    {
        $before = $this->get($type, $id);
        if (!(int) $before['activo']) {
            throw new HttpException('CONFLICT', 'El registro ya esta desactivado.', 409);
        }
        $this->pdo->beginTransaction();
        try {
            $this->repository->deactivate($type, $id);
            $this->audit($actor, 'Desactivar', $type, $id, $before, ['activo' => 0]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($type, $id);
    }

    private function validate(string $type, array $input): array
    {
        foreach (['nombre', 'unidad_medida'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
                throw new HttpException('VALIDATION_ERROR', 'Falta un campo obligatorio.', 422, ['field' => $field]);
            }
        }
        $data = ['nombre' => trim($input['nombre']), 'descripcion' => isset($input['descripcion']) ? trim((string) $input['descripcion']) : null, 'unidad_medida' => trim($input['unidad_medida'])];
        if ($type === 'producto') {
            $rate = $input['porcentaje_alicuota'] ?? null;
            if (!in_array((string) $rate, ['10.5', '10.50', '21', '21.00', '27', '27.00'], true)) {
                throw new HttpException('VALIDATION_ERROR', 'La alicuota debe ser 10.50, 21.00 o 27.00.', 422, ['field' => 'porcentaje_alicuota']);
            }
            $data['porcentaje_alicuota'] = (float) $rate;
        }
        return $data;
    }

    private function audit(int $actor, string $action, string $type, int $id, ?array $before, ?array $after): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :before, :after)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => $type, 'entity_id' => $id, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
