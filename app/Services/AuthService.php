<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Session;
use PDO;

final class AuthService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Session $session,
    ) {
    }

    public function login(string $email, string $password): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id_usuario, nombre, apellido, email, password_hash, activo
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!is_array($user) || (int) $user['activo'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException('INVALID_CREDENTIALS', 'El email o la contraseña no son correctos.', 401);
        }

        $this->session->authenticate($user);
        unset($user['password_hash'], $user['activo']);

        return $user;
    }

    public function currentUser(): array
    {
        $userId = $this->session->userId();
        if ($userId === null) {
            throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
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

        if (!is_array($user)) {
            $this->session->logout();
            throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
        }

        return $user;
    }
}
