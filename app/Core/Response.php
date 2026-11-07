<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private readonly mixed $data,
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly bool $success = true,
        private readonly ?array $pagination = null,
    ) {
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        $payload = $this->success
            ? ['success' => true, 'data' => $this->data]
            : ['success' => false, 'error' => $this->data];

        if ($this->pagination !== null && $this->success) {
            $payload['pagination'] = $this->pagination;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function failure(string $code, string $message, int $status): self
    {
        return new self(['code' => $code, 'message' => $message], $status, [], false);
    }

    public static function error(string $code, string $message, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
