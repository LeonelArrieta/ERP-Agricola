<?php

declare(strict_types=1);

namespace App\Core;

final class ViewRenderer
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function render(string $view, array $data = [], string $layout = 'main'): string
    {
        $content = $this->partial($view, $data);

        return $this->partial('layouts/' . $layout, $data + ['content' => $content]);
    }

    public function partial(string $view, array $data = []): string
    {
        $file = $this->basePath . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
