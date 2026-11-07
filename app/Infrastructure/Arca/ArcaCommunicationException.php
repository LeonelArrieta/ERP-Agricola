<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

use RuntimeException;

final class ArcaCommunicationException extends RuntimeException
{
    public const CATEGORY_TRANSPORT = 'transporte';
    public const CATEGORY_AUTH = 'autenticacion';
    public const CATEGORY_RESPONSE = 'respuesta';
    public const CATEGORY_CONFIG = 'configuracion';

    public function __construct(string $message, private readonly string $category = self::CATEGORY_TRANSPORT)
    {
        parent::__construct($message);
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function isPreSend(): bool
    {
        return in_array($this->category, [self::CATEGORY_AUTH, self::CATEGORY_CONFIG], true);
    }
}
