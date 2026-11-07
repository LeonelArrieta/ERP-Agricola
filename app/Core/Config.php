<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private array $values;

    public function __construct(string $basePath)
    {
        $this->loadDotEnv($basePath . DIRECTORY_SEPARATOR . '.env');

        $this->values = [
            'app_env' => $this->env('APP_ENV') ?: 'production',
            'app_debug' => filter_var($this->env('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
            'app_url' => $this->env('APP_URL') ?: '',
            'timezone' => $this->env('APP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires',
            'session_lifetime' => (int) ($this->env('SESSION_LIFETIME') ?: 3600),
            'log_level' => $this->env('LOG_LEVEL') ?: 'ERROR',
            'db_host' => $this->env('DB_HOST') ?: '127.0.0.1',
            'db_port' => (int) ($this->env('DB_PORT') ?: 3306),
            'db_name' => $this->env('DB_DATABASE') ?: ($this->env('DB_NAME') ?: ''),
            'db_user' => $this->env('DB_USERNAME') ?: ($this->env('DB_USER') ?: ''),
            'db_password' => $this->env('DB_PASSWORD') ?: '',
            'arca_mode' => $this->env('ARCA_MODE') ?: 'mock',
            'arca_cuit' => $this->env('ARCA_CUIT') ?: '',
            'arca_cert_path' => $this->env('ARCA_CERT_PATH') ?: '',
            'arca_key_path' => $this->env('ARCA_KEY_PATH') ?: '',
            'arca_wsaa_url' => $this->env('ARCA_WSAA_URL') ?: '',
            'arca_wsfe_url' => $this->env('ARCA_WSFE_URL') ?: '',
            'arca_timeout' => (int) ($this->env('ARCA_TIMEOUT') ?: 30),
            'arca_allow_emission' => filter_var($this->env('ARCA_ALLOW_EMISSION') ?: true, FILTER_VALIDATE_BOOLEAN),
            'arca_wsdl_version' => $this->env('ARCA_WSDL_VERSION') ?: '',
            'arca_condicion_emisor' => (int) ($this->env('ARCA_CONDICION_EMISOR') ?: 1),
            'arca_punto_venta_default' => (int) ($this->env('ARCA_PUNTO_VENTA_DEFAULT') ?: 1),
        ];

        date_default_timezone_set((string) $this->values['timezone']);
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function isDebug(): bool
    {
        return (bool) $this->values['app_debug'];
    }

    private function env(string $name): mixed
    {
        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }

        return $_ENV[$name] ?? $_SERVER[$name] ?? null;
    }

    private function loadDotEnv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($name !== '' && getenv($name) === false && !isset($_ENV[$name])) {
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                if (function_exists('putenv')) {
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}
