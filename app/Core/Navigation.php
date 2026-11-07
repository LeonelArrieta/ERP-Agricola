<?php

declare(strict_types=1);

namespace App\Core;

final class Navigation
{
    public static function groups(array $permissions): array
    {
        $items = [
            'Administración' => [
                ['key' => 'clientes', 'label' => 'Clientes', 'url' => url('app/clientes'), 'permission' => 'clientes.ver'],
                ['key' => 'proveedores', 'label' => 'Proveedores', 'url' => url('app/proveedores'), 'permission' => 'proveedores.ver'],
                ['key' => 'trabajadores', 'label' => 'Trabajadores', 'url' => url('app/trabajadores'), 'permission' => 'produccion.ver'],
                ['key' => 'cultivos', 'label' => 'Cultivos', 'url' => url('app/cultivos'), 'permission' => 'produccion.ver'],
                ['key' => 'parcelas', 'label' => 'Parcelas', 'url' => url('app/parcelas'), 'permission' => 'produccion.ver'],
                ['key' => 'tareas', 'label' => 'Tareas', 'url' => url('app/tareas'), 'permission' => 'produccion.ver'],
                ['key' => 'unidades', 'label' => 'Unidades', 'url' => url('app/unidades'), 'permission' => 'unidades.ver'],
                ['key' => 'categorias-gastos', 'label' => 'Categorías de gasto', 'url' => url('app/categorias-gastos'), 'permission' => 'gastos.ver'],
            ],
            'Producción' => [
                ['key' => 'ciclos-cultivo', 'label' => 'Ciclos de cultivo', 'url' => url('app/ciclos-cultivo'), 'permission' => 'produccion.ver'],
                ['key' => 'tareas-cultivo', 'label' => 'Tareas de cultivo', 'url' => url('app/tareas-cultivo'), 'permission' => 'produccion.ver'],
            ],
            'Inventario' => [
                ['key' => 'productos', 'label' => 'Productos', 'url' => url('app/productos'), 'permission' => 'productos.ver'],
                ['key' => 'insumos', 'label' => 'Insumos', 'url' => url('app/insumos'), 'permission' => 'insumos.ver'],
                ['key' => 'inventario', 'label' => 'Movimientos', 'url' => url('app/inventario'), 'permission' => 'inventario.ver'],
            ],
            'Comercial' => [
                ['key' => 'compras', 'label' => 'Compras', 'url' => url('app/compras'), 'permission' => 'compras.ver'],
                ['key' => 'ventas', 'label' => 'Ventas', 'url' => url('app/ventas'), 'permission' => 'ventas.ver'],
            ],
            'Finanzas' => [
                ['key' => 'cxc', 'label' => 'Cuentas por cobrar', 'url' => url('app/cxc'), 'permission' => 'cobranzas.ver'],
                ['key' => 'cxp', 'label' => 'Cuentas por pagar', 'url' => url('app/cxp'), 'permission' => 'pagos.ver'],
                ['key' => 'caja', 'label' => 'Caja', 'url' => url('app/caja/movimientos'), 'permission' => 'caja.ver'],
                ['key' => 'gastos', 'label' => 'Gastos', 'url' => url('app/gastos'), 'permission' => 'gastos.ver'],
            ],
            'Facturación' => [
                ['key' => 'comprobantes', 'label' => 'Comprobantes', 'url' => url('app/comprobantes'), 'permission' => 'facturacion.ver'],
                ['key' => 'puntos-venta', 'label' => 'Puntos de venta', 'url' => url('app/puntos-venta'), 'permission' => 'facturacion.ver'],
                ['key' => 'emisor', 'label' => 'Datos del emisor', 'url' => url('app/emisor'), 'permission' => 'facturacion.administrar'],
                ['key' => 'facturacion-diagnostico', 'label' => 'Diagnóstico ARCA', 'url' => url('app/facturacion/diagnostico'), 'permission' => 'facturacion.administrar'],
            ],
        ];

        return self::filter($items, $permissions);
    }

    public static function tiles(array $permissions): array
    {
        $items = [
            'Resumen' => [
                ['key' => 'clientes', 'label' => 'Clientes', 'hint' => 'Cartera de clientes', 'url' => url('app/clientes'), 'permission' => 'clientes.ver'],
                ['key' => 'proveedores', 'label' => 'Proveedores', 'hint' => 'Proveedores registrados', 'url' => url('app/proveedores'), 'permission' => 'proveedores.ver'],
                ['key' => 'productos', 'label' => 'Productos', 'hint' => 'Catálogo comercializable', 'url' => url('app/productos'), 'permission' => 'productos.ver'],
                ['key' => 'insumos', 'label' => 'Insumos', 'hint' => 'Stock y valorización', 'url' => url('app/insumos'), 'permission' => 'insumos.ver'],
                ['key' => 'cultivos', 'label' => 'Cultivos', 'hint' => 'Catálogo de cultivos', 'url' => url('app/cultivos'), 'permission' => 'produccion.ver'],
                ['key' => 'parcelas', 'label' => 'Parcelas', 'hint' => 'Unidades productivas', 'url' => url('app/parcelas'), 'permission' => 'produccion.ver'],
                ['key' => 'tareas', 'label' => 'Tareas', 'hint' => 'Catálogo de tareas', 'url' => url('app/tareas'), 'permission' => 'produccion.ver'],
                ['key' => 'ciclos-cultivo', 'label' => 'Producción', 'hint' => 'Ciclos de cultivo', 'url' => url('app/ciclos-cultivo'), 'permission' => 'produccion.ver'],
                ['key' => 'inventario', 'label' => 'Inventario', 'hint' => 'Movimientos históricos', 'url' => url('app/inventario'), 'permission' => 'inventario.ver'],
                ['key' => 'cxc', 'label' => 'CxC', 'hint' => 'Cuentas por cobrar', 'url' => url('app/cxc'), 'permission' => 'cobranzas.ver'],
                ['key' => 'cxp', 'label' => 'CxP', 'hint' => 'Cuentas por pagar', 'url' => url('app/cxp'), 'permission' => 'pagos.ver'],
                ['key' => 'caja', 'label' => 'Caja', 'hint' => 'Movimientos de Caja', 'url' => url('app/caja/movimientos'), 'permission' => 'caja.ver'],
            ],
        ];

        $filtered = self::filter($items, $permissions);

        return $filtered['Resumen'] ?? [];
    }

    private static function filter(array $groups, array $permissions): array
    {
        $result = [];
        foreach ($groups as $group => $items) {
            $allowed = array_values(array_filter(
                $items,
                static fn (array $item): bool => in_array($item['permission'], $permissions, true)
            ));
            if ($allowed !== []) {
                $result[$group] = $allowed;
            }
        }

        return $result;
    }
}
