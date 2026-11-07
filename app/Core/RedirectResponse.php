<?php

declare(strict_types=1);

namespace App\Core;

final class RedirectResponse
{
    public function __construct(private readonly string $location, private readonly int $status = 302)
    {
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Location: ' . $this->location);
        exit;
    }
}
