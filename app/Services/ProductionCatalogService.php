<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DatabaseError;
use App\Core\HttpException;
use App\Repositories\ProductionCatalogRepository;
use PDO;
use PDOException;

final class ProductionCatalogService
{
    public function __construct(private readonly PDO $pdo, private readonly ProductionCatalogRepository $repository)
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

    public function save(string $type, array $input, int $actor, ?int $id = null): array
    {
        $before = $id === null ? null : $this->get($type, $id);
        $data = $this->validate($type, $input);
        try {
            $this->pdo->beginTransaction();
            $savedId = $this->repository->save($type, $data, $id);
            $this->audit($actor, $id === null ? 'Crear' : 'Modificar', $type, $savedId, $before, $data);
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
        return $this->get($type, $savedId);
    }

    public function deactivate(string $type, int $id, int $actor): array
    {
        $before = $this->get($type, $id);
        if ($type === 'parcela' && $this->repository->hasActiveCycle($id)) {
            throw new HttpException('CONFLICT', 'No se puede desactivar una parcela con un ciclo EnCurso.', 409);
        }
        if (!(int) $before['activo']) {
            if ($type === 'parcela') {
                return $before;
            }
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

    public function activate(string $type, int $id, int $actor): array
    {
        $before = $this->get($type, $id);
        if ((int) $before['activo'] === 1) {
            return $before;
        }
        $this->pdo->beginTransaction();
        try {
            $this->repository->activate($type, $id);
            $this->audit($actor, 'Activar', $type, $id, $before, ['activo' => 1]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($type, $id);
    }

    public function hasActiveCycle(int $plotId): bool
    {
        return $this->repository->hasActiveCycle($plotId);
    }

    private function validate(string $type, array $input): array
    {
        if (!isset($input['nombre']) || !is_string($input['nombre']) || trim($input['nombre']) === '') {
            throw new HttpException('VALIDATION_ERROR', 'El nombre es obligatorio.', 422, ['field' => 'nombre']);
        }
        $data = ['nombre' => trim($input['nombre'])];
        if (in_array($type, ['cultivo', 'parcela', 'tarea'], true)) {
            $data['descripcion'] = isset($input['descripcion']) ? trim((string) $input['descripcion']) : null;
        }
        if ($type === 'trabajador') {
            foreach (['apellido'] as $field) {
                if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
                    throw new HttpException('VALIDATION_ERROR', 'El apellido es obligatorio.', 422, ['field' => $field]);
                }
            }
            $cost = $input['costo_hora_actual'] ?? null;
            if (!is_numeric($cost) || (float) $cost < 0) {
                throw new HttpException('VALIDATION_ERROR', 'El costo por hora debe ser no negativo.', 422, ['field' => 'costo_hora_actual']);
            }
            $data += ['apellido' => trim((string) $input['apellido']), 'documento' => $input['documento'] ?? null, 'telefono' => $input['telefono'] ?? null, 'costo_hora_actual' => (float) $cost];
        }
        if ($type === 'parcela') {
            if (!is_numeric($input['superficie'] ?? null) || (float) $input['superficie'] <= 0 || trim((string) ($input['unidad_superficie'] ?? '')) === '') {
                throw new HttpException('VALIDATION_ERROR', 'La superficie y su unidad son obligatorias.', 422);
            }
            $data += ['superficie' => (float) $input['superficie'], 'unidad_superficie' => trim((string) $input['unidad_superficie'])];
        }
        return $data;
    }

    private function audit(int $actor, string $action, string $type, int $id, ?array $before, array $after): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :before, :after)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => $type, 'entity_id' => $id, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
