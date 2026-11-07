<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;

final class DatabaseError
{
    public static function conflict(
        PDOException $exception,
        string $duplicateMessage,
        string $foreignMessage = 'El registro relacionado no existe.',
    ): HttpException {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        $message = match ($driverCode) {
            1062 => $duplicateMessage,
            1451, 1452 => $foreignMessage,
            default => 'No se pudo guardar el registro por un conflicto de datos.',
        };

        return new HttpException('CONFLICT', $message, 409);
    }
}
