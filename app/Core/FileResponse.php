<?php

declare(strict_types=1);

namespace App\Core;

final class FileResponse
{
    public function __construct(
        private readonly string $content,
        private readonly string $contentType,
        private readonly string $filename,
        private readonly bool $attachment = false,
        private readonly int $status = 200,
    ) {
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: ' . $this->contentType);
        header('Content-Disposition: ' . ($this->attachment ? 'attachment' : 'inline') . '; filename="' . $this->filename . '"');
        header('Content-Length: ' . strlen($this->content));
        echo $this->content;
        exit;
    }
}
