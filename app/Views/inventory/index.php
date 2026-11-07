<div class="page-head">
    <div>
        <p class="eyebrow">Inventario</p>
        <h1>Movimientos</h1>
        <p>Historial de cambios físicos del inventario. Los movimientos no se editan ni se eliminan.</p>
    </div>
    <?php if (in_array('inventario.merma', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/inventario/mermas/nueva')) ?>">Registrar merma</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/inventario')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Insumo o producto">
    </div>
    <div class="field">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <?php foreach (['EntradaCompra', 'EntradaCosecha', 'SalidaVenta', 'SalidaConsumo', 'SalidaPorMerma', 'ReversionEntrada', 'ReversionSalida'] as $type): ?>
                <option value="<?= e($type) ?>"<?= ($filters['tipo'] ?? '') === $type ? ' selected' : '' ?>><?= e(estado_label($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="from">Desde</label>
        <input id="from" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="to">Hasta</label>
        <input id="to" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
    </div>
    <button class="button ghost">Filtrar</button>
    <?php if (has_active_filters($filters ?? [])): ?>
        <a class="button ghost" href="<?= e(url('app/inventario')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron movimientos con esos filtros.' : 'Todavía no hay movimientos de inventario.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Las compras, cosechas, ventas, consumos y mermas generan movimientos históricos.',
        $hasFilters
            ? ['url' => url('app/inventario'), 'label' => 'Limpiar filtros']
            : (in_array('inventario.merma', $permissions, true) ? ['url' => url('app/inventario/mermas/nueva'), 'label' => 'Registrar merma'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Tipo</th>
                <th scope="col">Recurso</th>
                <th scope="col" class="num">Cantidad</th>
                <th scope="col" class="num">Stock anterior</th>
                <th scope="col" class="num">Stock resultante</th>
                <th scope="col">Origen</th>
                <th scope="col">Usuario</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e(fecha_hora($item['fecha_hora'] ?? null)) ?></td>
                    <td><span class="badge"><?= e($item['tipo'] ?? '') ?></span></td>
                    <td>
                        <?php if (($item['insumo'] ?? null) !== null): ?>
                            <?= e($item['insumo']) ?> <span class="hint">(insumo)</span>
                        <?php elseif (($item['producto'] ?? null) !== null): ?>
                            <?= e($item['producto']) ?> <span class="hint">(producto)</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= e(qty($item['cantidad'] ?? 0)) ?></td>
                    <td class="num"><?= e(qty($item['stock_anterior'] ?? 0)) ?></td>
                    <td class="num"><?= e(qty($item['stock_resultante'] ?? 0)) ?></td>
                    <td><?= e(($item['origen_tipo'] ?? '—') . (($item['origen_id'] ?? null) !== null ? ' #' . $item['origen_id'] : '')) ?></td>
                    <td><?= e($item['usuario'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
