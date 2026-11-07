<div class="page-head">
    <div>
        <p class="eyebrow">Producción</p>
        <h1>Tareas de cultivo</h1>
        <p>Ejecuciones de tareas registradas en los ciclos productivos.</p>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('app/tareas-cultivo')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Tarea, cultivo o parcela">
    </div>
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['Planificada', 'EnCurso', 'Completada', 'Cancelada'] as $state): ?>
                <option value="<?= e($state) ?>"<?= ($filters['estado'] ?? '') === $state ? ' selected' : '' ?>><?= e(estado_label($state)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="button ghost">Filtrar</button>
    <?php if (has_active_filters($filters ?? [])): ?>
        <a class="button ghost" href="<?= e(url('app/tareas-cultivo')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron tareas con esos filtros.' : 'Todavía no hay tareas de cultivo registradas.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Creá tareas desde el detalle de un ciclo de cultivo.',
        $hasFilters ? ['url' => url('app/tareas-cultivo'), 'label' => 'Limpiar filtros'] : null
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Tarea</th>
                <th scope="col">Cultivo</th>
                <th scope="col">Parcela</th>
                <th scope="col">Fecha</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $id = (int) $item['id_tarea_cultivo']; ?>
                <tr>
                    <td><?= e((string) $id) ?></td>
                    <td><?= e($item['tarea'] ?? '—') ?></td>
                    <td><?= e($item['cultivo'] ?? '—') ?></td>
                    <td><?= e($item['parcela'] ?? '—') ?></td>
                    <td><?= e(fecha($item['fecha'] ?? null)) ?></td>
                    <td><?= estado_badge($item['estado'] ?? null) ?></td>
                    <td><a class="button ghost small" href="<?= e(url('app/tareas-cultivo/' . $id)) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
