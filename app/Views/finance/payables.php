<div class="page-head">
    <div>
        <p class="eyebrow">Finanzas</p>
        <h1>Cuentas por pagar</h1>
        <p>Obligaciones con proveedores y gastos diferidos. Los pagos se registran desde el detalle de la compra.</p>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('app/cxp')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Proveedor, categoría o descripción">
    </div>
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['Pendiente', 'Parcialmente_Pagada', 'Pagada', 'Cancelada'] as $state): ?>
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
        <a class="button ghost" href="<?= e(url('app/cxp')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron cuentas por pagar con esos filtros.' : 'No hay cuentas por pagar registradas.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Las compras y gastos diferidos generan CxP automáticamente.',
        $hasFilters ? ['url' => url('app/cxp'), 'label' => 'Limpiar filtros'] : null
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Origen</th>
                <th scope="col">Emisión</th>
                <th scope="col" class="num">Importe original</th>
                <th scope="col" class="num">Saldo</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) $item['id_cuenta_por_pagar']; ?>
                <tr>
                    <td><?= e((string) $id) ?></td>
                    <td>
                        <?php if (($item['id_compra'] ?? null) !== null): ?>
                            Compra #<?= e((string) $item['id_compra']) ?> — <?= e($item['proveedor'] ?? '') ?>
                        <?php else: ?>
                            Gasto #<?= e((string) $item['id_gasto']) ?> — <?= e($item['gasto_categoria'] ?? '') ?> (<?= e($item['gasto_descripcion'] ?? '') ?>)
                        <?php endif; ?>
                    </td>
                    <td><?= e(fecha($item['fecha_emision'] ?? null)) ?></td>
                    <td class="num"><?= e(ars($item['importe_original'])) ?></td>
                    <td class="num"><?= e(ars($item['saldo_actual'])) ?></td>
                    <td><?= estado_badge($item['estado'] ?? null) ?></td>
                    <td>
                        <?php if (($item['id_compra'] ?? null) !== null): ?>
                            <a class="button ghost small" href="<?= e(url('app/compras/' . (int) $item['id_compra'])) ?>">Ver compra</a>
                        <?php else: ?>
                            <?php $gastoSearch = trim((string) ($item['gasto_descripcion'] ?? '')); ?>
                            <a class="button ghost small" href="<?= e(url('app/gastos' . ($gastoSearch !== '' ? '?search=' . rawurlencode($gastoSearch) : ''))) ?>">Ver gasto</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
