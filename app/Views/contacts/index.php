<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= e($pluralLabel) ?></h1>
        <p>Gestión de <?= e(mb_strtolower($pluralLabel)) ?> registrados en el sistema.</p>
    </div>
    <?php if (in_array($permission . '.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/' . $type . '/nuevo')) ?>">Nuevo <?= e(mb_strtolower($singularLabel)) ?></a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/' . $type)) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre, documento o email">
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
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Creá el primer registro para empezar.',
        $hasFilters
            ? ['url' => url('app/' . $type), 'label' => 'Limpiar filtros']
            : (in_array($permission . '.crear', $permissions, true) ? ['url' => url('app/' . $type . '/nuevo'), 'label' => 'Nuevo ' . mb_strtolower($singularLabel)] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Nombre / Razón social</th>
                <th scope="col">Documento</th>
                <th scope="col">Teléfono</th>
                <th scope="col">Email</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $id = (int) ($item[$idColumn] ?? 0);
                $display = trim((string) ($item['razon_social'] ?? '')) !== ''
                    ? (string) $item['razon_social']
                    : trim((string) ($item['nombre'] ?? '') . ' ' . (string) ($item['apellido'] ?? ''));
                ?>
                <tr>
                    <td><?= e($display) ?></td>
                    <td><?= e($item['documento'] ?? '—') ?></td>
                    <td><?= e($item['telefono'] ?? '—') ?></td>
                    <td><?= e($item['email'] ?? '—') ?></td>
                    <td><?= estado_badge((int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo') ?></td>
                    <td>
                        <div class="actions">
                            <a class="button ghost small" href="<?= e(url('app/' . $type . '/' . $id)) ?>">Ver</a>
                            <?php if (in_array($permission . '.editar', $permissions, true)): ?>
                                <a class="button ghost small" href="<?= e(url('app/' . $type . '/' . $id . '/editar')) ?>">Editar</a>
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
