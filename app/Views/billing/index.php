<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Comprobantes fiscales</h1>
        <p>Comprobantes electrónicos emitidos y su estado ante ARCA.</p>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('app/comprobantes')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Cliente, CAE o número">
    </div>
    <div class="field">
        <label for="estado_fiscal">Estado fiscal</label>
        <select id="estado_fiscal" name="estado_fiscal">
            <option value="">Todos</option>
                <?php foreach (['Pendiente', 'Enviando', 'Autorizado', 'Rechazado', 'ErrorComunicacion', 'ResultadoIncierto', 'RequiereRevision'] as $state): ?>
                <option value="<?= e($state) ?>"<?= ($filters['estado_fiscal'] ?? '') === $state ? ' selected' : '' ?>><?= e(estado_label($state)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <?php foreach (($types ?? []) as $type): ?>
                <option value="<?= e((string) $type['id_tipo_comprobante']) ?>"<?= (string) ($filters['tipo'] ?? '') === (string) $type['id_tipo_comprobante'] ? ' selected' : '' ?>><?= e($type['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="punto_venta">Punto de venta</label>
        <select id="punto_venta" name="punto_venta">
            <option value="">Todos</option>
            <?php foreach (($points ?? []) as $point): ?>
                <option value="<?= e((string) $point['id_punto_venta']) ?>"<?= (string) ($filters['punto_venta'] ?? '') === (string) $point['id_punto_venta'] ? ' selected' : '' ?>>PV <?= e((string) $point['numero']) ?></option>
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
        <a class="button ghost" href="<?= e(url('app/comprobantes')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron comprobantes con esos filtros.' : 'Todavía no hay comprobantes fiscales emitidos.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Emití un comprobante desde el detalle de una venta.',
        $hasFilters ? ['url' => url('app/comprobantes'), 'label' => 'Limpiar filtros'] : null
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Tipo</th>
                <th scope="col">Punto / Número</th>
                <th scope="col">Cliente</th>
                <th scope="col" class="num">Total</th>
                <th scope="col">CAE</th>
                <th scope="col">Estado fiscal</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) $item['id_comprobante_fiscal']; ?>
                <tr>
                    <td><?= e(fecha($item['fecha'] ?? null)) ?></td>
                    <td><?= e($item['tipo_nombre'] ?? '—') ?></td>
                    <td>PV <?= e((string) ($item['punto_venta_numero'] ?? '')) ?> — <?= e((string) ($item['numero'] ?? '—')) ?></td>
                    <td><?= e($item['cliente'] ?? '—') ?></td>
                    <td class="num"><?= e(ars($item['imp_total'] ?? 0)) ?></td>
                    <td><?= e($item['cae'] ?? '—') ?></td>
                    <td><?= estado_badge($item['estado_fiscal'] ?? null) ?></td>
                    <td><a class="button ghost small" href="<?= e(url('app/comprobantes/' . $id)) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
