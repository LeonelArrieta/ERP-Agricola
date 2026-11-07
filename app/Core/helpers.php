<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = str_replace('\\', '/', dirname(is_string($script) ? $script : ''));
        $base = ($base === '.' || $base === '/' || $base === '') ? '' : rtrim($base, '/');

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('ars')) {
    function ars(float|int|string|null $value): string
    {
        return '$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }
}

if (!function_exists('qty')) {
    function qty(float|int|string|null $value): string
    {
        $formatted = number_format((float) ($value ?? 0), 3, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }
}

if (!function_exists('fecha')) {
    function fecha(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        $date = date_create_from_format('Y-m-d', substr($value, 0, 10));

        return $date === false ? $value : $date->format('d/m/Y');
    }
}

if (!function_exists('fecha_hora')) {
    function fecha_hora(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        $date = date_create($value);

        return $date === false ? $value : $date->format('d/m/Y H:i');
    }
}

if (!function_exists('estado_label')) {
    function estado_label(?string $state): string
    {
        $state = trim((string) $state);
        if ($state === '') {
            return '—';
        }

        $labels = [
            'EnCurso' => 'En curso',
            'ErrorComunicacion' => 'Error de comunicación',
            'ResultadoIncierto' => 'Resultado incierto',
            'RequiereRevision' => 'Requiere revisión',
            'Parcialmente_Cobrada' => 'Parcialmente cobrada',
            'Parcialmente_Pagada' => 'Parcialmente pagada',
            'Planificado' => 'Planificado',
            'Planificada' => 'Planificada',
            'EntradaCompra' => 'Entrada por compra',
            'EntradaCosecha' => 'Entrada por cosecha',
            'SalidaVenta' => 'Salida por venta',
            'SalidaConsumo' => 'Salida por consumo',
            'SalidaPorMerma' => 'Salida por merma',
            'ReversionEntrada' => 'Reversión de entrada',
            'ReversionSalida' => 'Reversión de salida',
            'Credito' => 'Crédito',
        ];

        return $labels[$state] ?? ucfirst(str_replace('_', ' ', $state));
    }
}

if (!function_exists('estado_badge')) {
    function estado_badge(?string $state): string
    {
        $state = trim((string) $state);
        if ($state === '') {
            return '<span class="badge badge-off">—</span>';
        }

        $variant = match ($state) {
            'Autorizado', 'Cobrado', 'Cobrada', 'Pagado', 'Pagada', 'Completada', 'Cosechado', 'Activo' => 'ok',
            'Facturado', 'EnCurso' => 'info',
            'Pendiente', 'Parcialmente_Cobrada', 'Parcialmente_Pagada', 'Enviando', 'ResultadoIncierto', 'RequiereRevision', 'Planificado', 'Planificada' => 'warn',
            'Rechazado', 'Cancelado', 'Cancelada', 'ErrorComunicacion', 'Inactivo' => 'fail',
            default => 'off',
        };

        return '<span class="badge badge-' . $variant . '">' . e(estado_label($state)) . '</span>';
    }
}

if (!function_exists('has_active_filters')) {
    function has_active_filters(array $filters, array $ignore = ['page', 'per_page']): bool
    {
        foreach ($filters as $key => $value) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            if (is_array($value)) {
                if ($value !== []) {
                    return true;
                }
                continue;
            }
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('empty_state')) {
    /**
     * @param array{url:string,label:string}|null $action
     */
    function empty_state(string $title, string $hint = '', ?array $action = null): string
    {
        $html = '<div class="empty-state">'
            . '<p class="empty-state-title">' . e($title) . '</p>';
        if (trim($hint) !== '') {
            $html .= '<p class="empty-state-hint">' . e($hint) . '</p>';
        }
        if ($action !== null) {
            $html .= '<a class="button ghost small" href="' . e($action['url']) . '">' . e($action['label']) . '</a>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('error_modal')) {
    function error_modal(?string $message, string $title = 'No se pudo completar la operación'): string
    {
        if ($message === null || trim($message) === '') {
            return '';
        }

        static $counter = 0;
        $counter++;
        $titleId = 'error-modal-title-' . $counter;
        $messageId = 'error-modal-message-' . $counter;

        return '<dialog class="modal" data-modal role="alertdialog" aria-labelledby="' . e($titleId) . '" aria-describedby="' . e($messageId) . '">'
            . '<div class="modal-body">'
            . '<p class="modal-eyebrow">Error</p>'
            . '<h2 id="' . e($titleId) . '">' . e($title) . '</h2>'
            . '<p class="modal-message" id="' . e($messageId) . '">' . e($message) . '</p>'
            . '<div class="modal-actions"><button type="button" data-modal-close>Cerrar</button></div>'
            . '</div>'
            . '</dialog>';
    }
}
