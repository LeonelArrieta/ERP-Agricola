<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1>Unidades</h1>
        <p>Catálogo de unidades de medida usadas en productos, insumos, compras, consumos y cosechas.</p>
    </div>
    <?php if (in_array('unidades.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/unidades/nuevo')) ?>">Nueva unidad</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/unidades')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre o descripción">
    </div>
    <div class="field">
        <label for="activo">Estado</label>
        <select id="activo" name="activo">
            <option value="">Todas</option>
            <option value="1"<?= ($filters['activo'] ?? '') === '1' ? ' selected' : '' ?>>Activas</option>
            <option value="0"<?= ($filters['activo'] ?? '') === '0' ? ' selected' : '' ?>>Inactivas</option>
        </select>
    </div>
    <button class="button ghost">Filtrar</button>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron unidades con esos filtros.' : 'Todavía no hay unidades registradas.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Creá la primera unidad de medida.',
        $hasFilters
            ? ['url' => url('app/unidades'), 'label' => 'Limpiar filtros']
            : (in_array('unidades.crear', $permissions, true) ? ['url' => url('app/unidades/nuevo'), 'label' => 'Nueva unidad'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Descripción</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) ($item['id_unidad'] ?? 0); ?>
                <tr>
                    <td><?= e($item['nombre'] ?? '') ?></td>
                    <td><?= e($item['descripcion'] ?? '—') ?></td>
                    <td><?= estado_badge((int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo') ?></td>
                    <td>
                        <div class="actions">
                            <a class="button ghost small" href="<?= e(url('app/unidades/' . $id)) ?>">Ver</a>
                            <?php if (in_array('unidades.editar', $permissions, true)): ?>
                                <a class="button ghost small" href="<?= e(url('app/unidades/' . $id . '/editar')) ?>">Editar</a>
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
