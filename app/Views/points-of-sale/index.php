<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Puntos de venta</h1>
        <p>Puntos de venta habilitados para emitir comprobantes electrónicos ante ARCA.</p>
    </div>
    <?php if (in_array('facturacion.emitir', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/puntos-venta/nuevo')) ?>">Nuevo punto de venta</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/puntos-venta')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Número o descripción">
    </div>
    <div class="field">
        <label for="activo">Estado</label>
        <select id="activo" name="activo">
            <option value="">Todos</option>
            <option value="1"<?= ($filters['activo'] ?? '') === '1' ? ' selected' : '' ?>>Activos</option>
            <option value="0"<?= ($filters['activo'] ?? '') === '0' ? ' selected' : '' ?>>Inactivos</option>
        </select>
    </div>
    <button class="button ghost">Filtrar</button>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron puntos de venta con esos filtros.' : 'Todavía no hay puntos de venta registrados.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Creá el punto de venta que vas a usar para emitir comprobantes electrónicos.',
        $hasFilters
            ? ['url' => url('app/puntos-venta'), 'label' => 'Limpiar filtros']
            : (in_array('facturacion.emitir', $permissions, true) ? ['url' => url('app/puntos-venta/nuevo'), 'label' => 'Nuevo punto de venta'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col" class="num">Número</th>
                <th scope="col">Descripción</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) ($item['id_punto_venta'] ?? 0); ?>
                <tr>
                    <td class="num"><?= e((string) $item['numero']) ?></td>
                    <td><?= e($item['descripcion'] ?? '—') ?></td>
                    <td><?= estado_badge((int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo') ?></td>
                    <td>
                        <div class="actions">
                            <a class="button ghost small" href="<?= e(url('app/puntos-venta/' . $id)) ?>">Ver</a>
                            <?php if (in_array('facturacion.emitir', $permissions, true)): ?>
                                <a class="button ghost small" href="<?= e(url('app/puntos-venta/' . $id . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
