<div class="page-head">
    <div>
        <p class="eyebrow">Finanzas</p>
        <h1>Cuentas por cobrar</h1>
        <p>Obligaciones pendientes de clientes. Los cobros se registran desde el detalle de la venta.</p>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('app/cxc')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Cliente">
    </div>
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['Pendiente', 'Parcialmente_Cobrada', 'Cobrada', 'Cancelada'] as $state): ?>
                <option value="<?= e($state) ?>"<?= ($filters['estado'] ?? '') === $state ? ' selected' : '' ?>><?= e(estado_label($state)) ?></option>
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
        <a class="button ghost" href="<?= e(url('app/cxc')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron cuentas por cobrar con esos filtros.' : 'No hay cuentas por cobrar registradas.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Cuando registres ventas en crédito, van a aparecer acá.',
        $hasFilters ? ['url' => url('app/cxc'), 'label' => 'Limpiar filtros'] : null
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Venta</th>
                <th scope="col">Cliente</th>
                <th scope="col">Emisión</th>
                <th scope="col" class="num">Importe original</th>
                <th scope="col" class="num">Saldo</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) $item['id_cuenta_por_cobrar']; ?>
                <tr>
                    <td><?= e((string) $id) ?></td>
                    <td>#<?= e((string) $item['id_venta']) ?> (<?= e(fecha($item['venta_fecha'] ?? null)) ?>)</td>
                    <td><?= e($item['cliente'] ?? '—') ?></td>
                    <td><?= e(fecha($item['fecha_emision'] ?? null)) ?></td>
                    <td class="num"><?= e(ars($item['importe_original'])) ?></td>
                    <td class="num"><?= e(ars($item['saldo_actual'])) ?></td>
                    <td><?= estado_badge($item['estado'] ?? null) ?></td>
                    <td>
                        <a class="button ghost small" href="<?= e(url('app/ventas/' . (int) $item['id_venta'])) ?>">Ver venta</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
