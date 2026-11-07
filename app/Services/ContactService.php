<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DatabaseError;
use App\Core\HttpException;
use App\Infrastructure\Arca\ArcaCatalogs;
use App\Repositories\ContactRepository;
use PDO;
use PDOException;

final class ContactService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ContactRepository $contacts,
        private readonly string $entity,
    ) {
    }

    public function list(int $page, int $perPage, ?bool $active, string $search): array
    {
        $result = $this->contacts->list(($page - 1) * $perPage, $perPage, $active, $search);
        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $result = $this->contacts->find($id);
        if ($result === null) {
            throw new HttpException('NOT_FOUND', 'El registro no existe.', 404);
        }
        return $result;
    }

    public function create(array $input, int $actorId): array
    {
        $data = $this->validate($input);
        try {
            $this->pdo->beginTransaction();
            $id = $this->contacts->create($data);
            $this->audit($actorId, 'Crear', $id, null, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'Ya existe un contacto con esos datos.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    public function update(int $id, array $input, int $actorId): array
    {
        $before = $this->get($id);
        $data = $this->validate($input);
        try {
            $this->pdo->beginTransaction();
            $this->contacts->update($id, $data);
            $this->audit($actorId, 'Modificar', $id, $before, $data);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'Ya existe un contacto con esos datos.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    public function deactivate(int $id, int $actorId): array
    {
        $before = $this->get($id);
        if (!(int) $before['activo']) {
            throw new HttpException('CONFLICT', 'El registro ya esta desactivado.', 409);
        }
        try {
            $this->pdo->beginTransaction();
            $this->contacts->deactivate($id);
            $this->audit($actorId, 'Desactivar', $id, $before, ['activo' => 0]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    private function validate(array $input): array
    {
        $data = [];
        foreach (['nombre', 'apellido', 'razon_social', 'documento', 'doc_nro', 'telefono', 'email', 'direccion'] as $field) {
            $value = $input[$field] ?? null;
            if ($value !== null && !is_string($value)) {
                throw new HttpException('VALIDATION_ERROR', 'Los campos de contacto deben ser texto.', 422, ['field' => $field]);
            }
            $data[$field] = $value === null ? null : trim($value);
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }
        foreach (['doc_tipo', 'condicion_iva_receptor_id'] as $field) {
            $value = $input[$field] ?? null;
            if ($value === null || $value === '') {
                $data[$field] = null;
                continue;
            }
            if (!is_numeric($value)) {
                throw new HttpException('VALIDATION_ERROR', 'Los datos fiscales no son validos.', 422, ['field' => $field]);
            }
            $data[$field] = (int) $value;
        }
        if ($data['doc_tipo'] !== null && !isset(ArcaCatalogs::DOC_TIPOS[$data['doc_tipo']])) {
            throw new HttpException('VALIDATION_ERROR', 'El tipo de documento fiscal no es valido.', 422, ['field' => 'doc_tipo']);
        }
        if ($data['condicion_iva_receptor_id'] !== null && !isset(ArcaCatalogs::CONDICIONES_IVA[$data['condicion_iva_receptor_id']])) {
            throw new HttpException('VALIDATION_ERROR', 'La condicion frente al IVA no es valida.', 422, ['field' => 'condicion_iva_receptor_id']);
        }
        if ($data['email'] !== null && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException('VALIDATION_ERROR', 'El email no es valido.', 422, ['field' => 'email']);
        }
        if ($data['nombre'] === null && $data['razon_social'] === null) {
            throw new HttpException('VALIDATION_ERROR', 'Debe indicar nombre o razon social.', 422);
        }
        return $data;
    }

    private function audit(int $actorId, string $action, int $id, ?array $before, ?array $after): void
    {
        $query = $this->pdo->prepare(
            'INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos)
             VALUES (:user_id, :action, :entity, :entity_id, :before, :after)'
        );
        $query->execute([
            'user_id' => $actorId, 'action' => $action, 'entity' => $this->entity, 'entity_id' => $id,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        ]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
