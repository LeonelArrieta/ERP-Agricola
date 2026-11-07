<div class="page-head">
    <div>
        <p class="eyebrow">Comercial</p>
        <h1>Compras</h1>
        <p>Adquisiciones de insumos con entrada de inventario y PPP actualizado.</p>
    </div>
    <?php if (in_array('compras.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/compras/nuevo')) ?>">Nueva compra</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/compras')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Proveedor u observaciones">
    </div>
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['Presupuesto', 'Pendiente', 'Facturado', 'Pagado', 'Cancelado'] as $state): ?>
                <option value="<?= e($state) ?>"<?= ($filters['estado'] ?? '') === $state ? ' selected' : '' ?>><?= e(estado_label($state)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="tipo_pago">Tipo de pago</label>
        <select id="tipo_pago" name="tipo_pago">
            <option value="">Todos</option>
            <?php foreach (['Contado', 'Credito'] as $paymentType): ?>
                <option value="<?= e($paymentType) ?>"<?= ($filters['tipo_pago'] ?? '') === $paymentType ? ' selected' : '' ?>><?= e(estado_label($paymentType)) ?></option>
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
        <a class="button ghost" href="<?= e(url('app/compras')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron compras con esos filtros.' : 'Todavía no hay compras registradas.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Registrá la primera compra de insumos para actualizar stock y PPP.',
        $hasFilters
            ? ['url' => url('app/compras'), 'label' => 'Limpiar filtros']
            : (in_array('compras.crear', $permissions, true) ? ['url' => url('app/compras/nuevo'), 'label' => 'Nueva compra'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Fecha</th>
                <th scope="col">Proveedor</th>
                <th scope="col">Tipo de pago</th>
                <th scope="col" class="num">Total</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) $item['id_compra']; ?>
                <tr>
                    <td><?= e((string) $id) ?></td>
                    <td><?= e(fecha($item['fecha'] ?? null)) ?></td>
                    <td><?= e($item['proveedor'] ?? '—') ?></td>
                    <td><?= e($item['tipo_pago'] ?? '—') ?></td>
                    <td class="num"><?= e(ars($item['total'] ?? 0)) ?></td>
                    <td><?= estado_badge($item['estado'] ?? null) ?></td>
                    <td><a class="button ghost small" href="<?= e(url('app/compras/' . $id)) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
