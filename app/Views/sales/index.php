<?php
$stateLabels = [
    'Presupuesto' => 'Presupuesto',
    'Pendiente' => 'Pendiente',
    'Facturado' => 'Facturado',
    'Cobrado' => 'Cobrado',
    'Cancelado' => 'Cancelado',
];
$paymentLabels = ['Contado' => 'Contado', 'Credito' => 'Crédito'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Comercial</p>
        <h1>Ventas</h1>
        <p>Ventas de lotes con margen, impuesto y valores históricos congelados.</p>
    </div>
    <?php if (in_array('ventas.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/ventas/nuevo')) ?>">Nueva venta</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/ventas')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Cliente u observaciones">
    </div>
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($stateLabels as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= ($filters['estado'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="tipo_pago">Tipo de pago</label>
        <select id="tipo_pago" name="tipo_pago">
            <option value="">Todos</option>
            <?php foreach ($paymentLabels as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= ($filters['tipo_pago'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
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
        <a class="button ghost" href="<?= e(url('app/ventas')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron ventas con esos filtros.' : 'Todavía no hay ventas registradas.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Registrá la primera venta desde un lote con disponibilidad.',
        $hasFilters
            ? ['url' => url('app/ventas'), 'label' => 'Limpiar filtros']
            : (in_array('ventas.crear', $permissions, true) ? ['url' => url('app/ventas/nuevo'), 'label' => 'Nueva venta'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <caption class="visually-hidden">Listado de ventas</caption>
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Fecha</th>
                <th scope="col">Cliente</th>
                <th scope="col">Tipo de pago</th>
                <th scope="col" class="num">Total</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) $item['id_venta']; ?>
                <tr>
                    <td><?= e((string) $id) ?></td>
                    <td><?= e(fecha($item['fecha'] ?? null)) ?></td>
                    <td><?= e($item['cliente'] ?? '—') ?></td>
                    <td><?= e($paymentLabels[$item['tipo_pago'] ?? ''] ?? ($item['tipo_pago'] ?? '—')) ?></td>
                    <td class="num"><?= e(ars($item['total'] ?? 0)) ?></td>
                    <td><?= estado_badge($item['estado'] ?? null) ?></td>
                    <td>
                        <a class="button ghost small" href="<?= e(url('app/ventas/' . $id)) ?>" aria-label="Ver venta #<?= e((string) $id) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
