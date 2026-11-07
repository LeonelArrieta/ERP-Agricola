<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Logger
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function error(string $message, array $context = [], ?Throwable $exception = null): void
    {
        if ($exception instanceof Throwable) {
            $context['exception'] = $exception::class;
            $context['exception_message'] = $exception->getMessage();
        }

        $this->write('ERROR', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $directory = dirname($this->logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $record = [
            'timestamp' => date(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
        file_put_contents($this->logPath, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
