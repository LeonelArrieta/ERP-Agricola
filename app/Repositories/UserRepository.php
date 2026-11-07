<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(int $offset, int $limit, ?bool $active, string $search): array
    {
        $conditions = [];
        $parameters = [];
        if ($active !== null) {
            $conditions[] = 'u.activo = :active';
            $parameters['active'] = $active ? 1 : 0;
        }
        if ($search !== '') {
            $conditions[] = '(u.nombre LIKE :search_nombre OR u.apellido LIKE :search_apellido OR u.email LIKE :search_email)';
            $parameters['search_nombre'] = '%' . $search . '%';
            $parameters['search_apellido'] = '%' . $search . '%';
            $parameters['search_email'] = '%' . $search . '%';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios u' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $query = $this->pdo->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.activo, u.created_at, u.updated_at, r.id_rol, r.nombre AS rol
             FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol' . $where .
            ' ORDER BY u.id_usuario DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $query->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.activo, u.created_at, u.updated_at, r.id_rol, r.nombre AS rol
             FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $user = $query->fetch();
        return is_array($user) ? $user : null;
    }

    public function create(array $data): int
    {
        $query = $this->pdo->prepare(
            'INSERT INTO usuarios (id_rol, nombre, apellido, email, password_hash)
             VALUES (:role, :first_name, :last_name, :email, :password_hash)'
        );
        $query->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $query = $this->pdo->prepare(
            'UPDATE usuarios SET id_rol = :role, nombre = :first_name, apellido = :last_name, email = :email
             WHERE id_usuario = :id'
        );
        $query->execute($data + ['id' => $id]);
    }

    public function deactivate(int $id): void
    {
        $query = $this->pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id_usuario = :id');
        $query->execute(['id' => $id]);
    }
}
