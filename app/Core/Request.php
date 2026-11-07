<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly string $requestId,
        public readonly array $params = [],
    ) {
    }

    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, $this->requestId, $params);
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = self::applicationBasePath();
        if ($basePath !== '/' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody ?: '', true);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rtrim($path, '/') ?: '/',
            $_GET,
            is_array($body) ? $body : $_POST,
            self::requestId()
        );
    }

    private static function requestId(): string
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

        return is_string($requestId) && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $requestId)
            ? $requestId
            : bin2hex(random_bytes(16));
    }

    private static function applicationBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!is_string($scriptName) || $scriptName === '') {
            return '/';
        }

        $directory = str_replace('\\', '/', dirname($scriptName));
        return $directory === '.' ? '/' : rtrim($directory, '/');
    }
}
