<div class="page-head">
    <div>
        <p class="eyebrow">Inventario</p>
        <h1><?= e($pluralLabel) ?></h1>
        <p>Catálogo de <?= e(mb_strtolower($pluralLabel)) ?> registrados en el sistema.</p>
    </div>
    <?php if (in_array($permission . '.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/' . $type . 's/nuevo')) ?>">Nuevo <?= e(mb_strtolower($singularLabel)) ?></a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/' . $type . 's')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre o descripción">
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
        $hasFilters ? 'No se encontraron ' . mb_strtolower($pluralLabel) . ' con esos filtros.' : 'Todavía no hay ' . mb_strtolower($pluralLabel) . ' registrados.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Creá el primer registro del catálogo.',
        $hasFilters
            ? ['url' => url('app/' . $type . 's'), 'label' => 'Limpiar filtros']
            : (in_array($permission . '.crear', $permissions, true) ? ['url' => url('app/' . $type . 's/nuevo'), 'label' => 'Nuevo ' . mb_strtolower($singularLabel)] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Unidad</th>
                <?php if ($type === 'producto'): ?>
                    <th scope="col" class="num">Alícuota</th>
                <?php else: ?>
                    <th scope="col" class="num">Stock</th>
                    <th scope="col" class="num">PPP</th>
                <?php endif; ?>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) ($item[$idColumn] ?? 0); ?>
                <tr>
                    <td><?= e($item['nombre'] ?? '') ?></td>
                    <td><?= e($item['unidad_medida'] ?? '—') ?></td>
                    <?php if ($type === 'producto'): ?>
                        <td class="num"><?= e(number_format((float) ($item['porcentaje_alicuota'] ?? 0), 2, ',', '.')) ?> %</td>
                    <?php else: ?>
                        <td class="num"><?= e(qty($item['stock_actual'] ?? 0)) ?></td>
                        <td class="num"><?= e(ars($item['costo_unitario_actual'] ?? 0)) ?></td>
                    <?php endif; ?>
                    <td><?= estado_badge((int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo') ?></td>
                    <td>
                        <div class="actions">
                            <a class="button ghost small" href="<?= e(url('app/' . $type . 's/' . $id)) ?>">Ver</a>
                            <?php if (in_array($permission . '.editar', $permissions, true)): ?>
                                <a class="button ghost small" href="<?= e(url('app/' . $type . 's/' . $id . '/editar')) ?>">Editar</a>
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
