<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (bool) ($_SERVER['HTTPS'] ?? false) && $_SERVER['HTTPS'] !== 'off';
        if ($this->config->get('app_env') === 'production' && !$secure) {
            $secure = true;
        }

        session_name('ERP_AGRICOLA_SESSION');
        session_set_cookie_params([
            'lifetime' => (int) $this->config->get('session_lifetime'),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $this->expireIfInactive();
    }

    public function authenticate(array $user): void
    {
        $this->start();
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => (int) $user['id_usuario'],
            'authenticated_at' => time(),
            'last_activity' => time(),
        ];
    }

    public function userId(): ?int
    {
        $this->start();
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $_SESSION['last_activity'] = time();
        return (int) $_SESSION['user_id'];
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public function pullFlashes(): array
    {
        $this->start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION['_once'][$key] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = $_SESSION['_once'][$key] ?? $default;
        unset($_SESSION['_once'][$key]);

        return $value;
    }

    private function expireIfInactive(): void
    {
        $lastActivity = $_SESSION['last_activity'] ?? null;
        $lifetime = (int) $this->config->get('session_lifetime');

        if (is_int($lastActivity) && $lifetime > 0 && time() - $lastActivity > $lifetime) {
            $this->logout();
        }
    }
}
