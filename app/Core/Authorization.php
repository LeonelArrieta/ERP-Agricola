<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Authorization
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Session $session,
    ) {
    }

    public function requireAuthenticated(): int
    {
        $userId = $this->session->userId();
        if ($userId === null) {
            throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
        }

        return $userId;
    }

    public function requirePermission(string $permission): int
    {
        $userId = $this->requireAuthenticated();
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             INNER JOIN rol_permiso rp ON rp.id_rol = r.id_rol
             INNER JOIN permisos p ON p.id_permiso = rp.id_permiso
             WHERE u.id_usuario = :user_id
               AND u.activo = 1
               AND r.activo = 1
               AND p.nombre = :permission
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'permission' => $permission]);

        if ($statement->fetchColumn() === false) {
            throw new HttpException('FORBIDDEN', 'No tiene permisos para realizar esta operacion.', 403);
        }

        return $userId;
    }

    public function permissions(): array
    {
        $userId = $this->session->userId();
        if ($userId === null) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT p.nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             INNER JOIN rol_permiso rp ON rp.id_rol = r.id_rol
             INNER JOIN permisos p ON p.id_permiso = rp.id_permiso
             WHERE u.id_usuario = :user_id
               AND u.activo = 1
               AND r.activo = 1'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map(static fn (array $row): string => (string) $row['nombre'], $statement->fetchAll());
    }

    public function user(): ?array
    {
        $userId = $this->session->userId();
        if ($userId === null) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email, r.nombre AS rol
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = :user_id AND u.activo = 1
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }
}
