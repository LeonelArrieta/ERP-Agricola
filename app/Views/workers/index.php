<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1>Trabajadores</h1>
        <p>Personas que realizan tareas productivas y cuya actividad genera costo de mano de obra.</p>
    </div>
    <?php if (in_array('produccion.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/trabajadores/nuevo')) ?>">Nuevo trabajador</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/trabajadores')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre, apellido o documento">
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
        $hasFilters ? 'No se encontraron trabajadores con esos filtros.' : 'Todavía no hay trabajadores registrados.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Registrá a las personas que realizan tareas productivas.',
        $hasFilters
            ? ['url' => url('app/trabajadores'), 'label' => 'Limpiar filtros']
            : (in_array('produccion.crear', $permissions, true) ? ['url' => url('app/trabajadores/nuevo'), 'label' => 'Nuevo trabajador'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Apellido y nombre</th>
                <th scope="col">Documento</th>
                <th scope="col">Teléfono</th>
                <th scope="col" class="num">Costo por hora</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) ($item['id_trabajador'] ?? 0); ?>
                <tr>
                    <td><?= e(trim(($item['apellido'] ?? '') . ', ' . ($item['nombre'] ?? ''), ', ')) ?></td>
                    <td><?= e($item['documento'] ?? '—') ?></td>
                    <td><?= e($item['telefono'] ?? '—') ?></td>
                    <td class="num"><?= e(ars($item['costo_hora_actual'] ?? 0)) ?></td>
                    <td><?= estado_badge((int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo') ?></td>
                    <td>
                        <div class="actions">
                            <a class="button ghost small" href="<?= e(url('app/trabajadores/' . $id)) ?>">Ver</a>
                            <?php if (in_array('produccion.crear', $permissions, true)): ?>
                                <a class="button ghost small" href="<?= e(url('app/trabajadores/' . $id . '/editar')) ?>">Editar</a>
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
