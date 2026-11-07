<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DatabaseError;
use App\Core\HttpException;
use App\Repositories\UserRepository;
use PDO;
use PDOException;

final class UserService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
    ) {
    }

    public function list(int $page, int $perPage, ?bool $active, string $search): array
    {
        $result = $this->users->list(($page - 1) * $perPage, $perPage, $active, $search);
        return [
            'items' => $result['items'],
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ];
    }

    public function get(int $id): array
    {
        $user = $this->users->find($id);
        if ($user === null) {
            throw new HttpException('NOT_FOUND', 'El usuario no existe.', 404);
        }
        return $user;
    }

    public function create(array $input, int $actorId): array
    {
        $data = $this->validated($input, true);
        try {
            $this->pdo->beginTransaction();
            $id = $this->users->create([
                'role' => $data['id_rol'],
                'first_name' => $data['nombre'],
                'last_name' => $data['apellido'],
                'email' => strtolower($data['email']),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
            $this->audit($actorId, 'Crear', 'usuarios', $id, null, ['email' => $data['email'], 'id_rol' => $data['id_rol']]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'Ya existe un usuario con ese email.', 'El rol indicado no existe.');
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
        $data = $this->validated($input, false);
        try {
            $this->pdo->beginTransaction();
            $this->users->update($id, [
                'role' => $data['id_rol'],
                'first_name' => $data['nombre'],
                'last_name' => $data['apellido'],
                'email' => strtolower($data['email']),
            ]);
            $this->audit($actorId, 'Modificar', 'usuarios', $id, $before, [
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
                'id_rol' => $data['id_rol'],
            ]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if ($exception->getCode() === '23000') {
                throw DatabaseError::conflict($exception, 'Ya existe un usuario con ese email.', 'El rol indicado no existe.');
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
        if (!$before['activo']) {
            throw new HttpException('CONFLICT', 'El usuario ya esta desactivado.', 409);
        }
        $this->pdo->beginTransaction();
        try {
            $this->users->deactivate($id);
            $this->audit($actorId, 'Desactivar', 'usuarios', $id, $before, ['activo' => 0]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        return $this->get($id);
    }

    private function validated(array $input, bool $passwordRequired): array
    {
        $required = ['nombre', 'apellido', 'email', 'id_rol'];
        if ($passwordRequired) {
            $required[] = 'password';
        }
        foreach ($required as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === '') {
                throw new HttpException('VALIDATION_ERROR', 'Falta un campo obligatorio.', 422, ['field' => $field]);
            }
        }
        if (!is_string($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new HttpException('VALIDATION_ERROR', 'El email no es valido.', 422, ['field' => 'email']);
        }
        if (!is_int($input['id_rol']) && !(is_string($input['id_rol']) && ctype_digit($input['id_rol']))) {
            throw new HttpException('VALIDATION_ERROR', 'El rol no es valido.', 422, ['field' => 'id_rol']);
        }
        if ($passwordRequired && (!is_string($input['password']) || strlen($input['password']) < 8)) {
            throw new HttpException('VALIDATION_ERROR', 'La contraseña debe tener al menos 8 caracteres.', 422, ['field' => 'password']);
        }
        return [
            'nombre' => trim((string) $input['nombre']),
            'apellido' => trim((string) $input['apellido']),
            'email' => trim((string) $input['email']),
            'id_rol' => (int) $input['id_rol'],
            'password' => (string) ($input['password'] ?? ''),
        ];
    }

    private function audit(int $actorId, string $action, string $entity, int $entityId, ?array $before, ?array $after): void
    {
        $query = $this->pdo->prepare(
            'INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos)
             VALUES (:user_id, :action, :entity, :entity_id, :before, :after)'
        );
        $query->execute([
            'user_id' => $actorId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
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
