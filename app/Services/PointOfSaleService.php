<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DatabaseError;
use App\Core\HttpException;
use App\Repositories\FiscalRepository;
use PDO;
use PDOException;

final class PointOfSaleService
{
    public function __construct(private readonly PDO $pdo, private readonly FiscalRepository $repository)
    {
    }

    public function list(int $page, int $perPage, ?bool $active, string $search): array
    {
        $result = $this->repository->pointsOfSale(($page - 1) * $perPage, $perPage, $active, $search);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $point = $this->repository->pointOfSale($id);
        if ($point === null) {
            throw new HttpException('NOT_FOUND', 'El punto de venta no existe.', 404);
        }

        return $point;
    }

    public function save(array $input, int $actor, ?int $id = null): array
    {
        $before = $id === null ? null : $this->get($id);
        $data = $this->validate($input);
        try {
            $this->pdo->beginTransaction();
            $savedId = $this->repository->savePointOfSale($data, $id);
            $this->audit($actor, $id === null ? 'Crear' : 'Modificar', $savedId, $before, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'El numero de punto de venta ya existe.');
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
            throw new HttpException('CONFLICT', 'El punto de venta ya esta desactivado.', 409);
        }
        $this->pdo->beginTransaction();
        try {
            $this->repository->deactivatePointOfSale($id);
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
            $this->repository->activatePointOfSale($id);
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
        return $this->repository->pointOfSaleOptions();
    }

    private function validate(array $input): array
    {
        $number = $input['numero'] ?? null;
        if (!is_numeric($number) || (int) $number < 1 || (int) $number > 99999) {
            throw new HttpException('VALIDATION_ERROR', 'El numero de punto de venta debe estar entre 1 y 99999.', 422, ['field' => 'numero']);
        }

        return [
            'numero' => (int) $number,
            'descripcion' => isset($input['descripcion']) ? trim((string) $input['descripcion']) : null,
        ];
    }

    private function audit(int $actor, string $action, int $id, ?array $before, array $after): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :before, :after)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => 'puntos_venta', 'entity_id' => $id, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
