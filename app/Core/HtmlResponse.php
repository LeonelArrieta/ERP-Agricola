<?php

declare(strict_types=1);

namespace App\Core;

final class HtmlResponse
{
    public function __construct(private readonly string $html, private readonly int $status = 200)
    {
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->html;
        exit;
    }
}
