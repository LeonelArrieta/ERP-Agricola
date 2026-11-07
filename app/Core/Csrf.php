<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $this->session->start();
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public function validate(?string $token): void
    {
        $expected = $this->token();
        if (!is_string($token) || $token === '' || !hash_equals($expected, $token)) {
            throw new HttpException('CSRF_INVALID', 'El token de seguridad no es valido.', 403);
        }
    }
}
