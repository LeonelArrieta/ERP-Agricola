<div class="page-head">
    <div>
        <p class="eyebrow">Finanzas</p>
        <h1>Gastos</h1>
        <p>Gastos operativos y administrativos. No se confunden con costos de producción.</p>
    </div>
    <?php if (in_array('gastos.crear', $permissions, true)): ?>
        <a class="button" href="<?= e(url('app/gastos/nuevo')) ?>">Nuevo gasto</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(url('app/gastos')) ?>">
    <div class="field">
        <label for="search">Buscar</label>
        <input id="search" type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Descripción o categoría">
    </div>
    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach (($categories ?? []) as $category): ?>
                <option value="<?= e((string) $category['id_categoria_gasto']) ?>"<?= (string) ($filters['categoria'] ?? '') === (string) $category['id_categoria_gasto'] ? ' selected' : '' ?>><?= e($category['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="tipo_pago">Tipo</label>
        <select id="tipo_pago" name="tipo_pago">
            <option value="">Todos</option>
            <?php foreach (['Contado', 'Diferido'] as $paymentType): ?>
                <option value="<?= e($paymentType) ?>"<?= ($filters['tipo_pago'] ?? '') === $paymentType ? ' selected' : '' ?>><?= e(estado_label($paymentType)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['Pendiente', 'Pagado', 'Cancelado'] as $state): ?>
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
        <a class="button ghost" href="<?= e(url('app/gastos')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<?php if (($items ?? []) === []): ?>
    <?php $hasFilters = has_active_filters($filters ?? []); ?>
    <?= empty_state(
        $hasFilters ? 'No se encontraron gastos con esos filtros.' : 'Todavía no hay gastos registrados.',
        $hasFilters ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Registrá gastos operativos de contado o diferidos.',
        $hasFilters
            ? ['url' => url('app/gastos'), 'label' => 'Limpiar filtros']
            : (in_array('gastos.crear', $permissions, true) ? ['url' => url('app/gastos/nuevo'), 'label' => 'Nuevo gasto'] : null)
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Fecha</th>
                <th scope="col">Categoría</th>
                <th scope="col">Descripción</th>
                <th scope="col" class="num">Importe</th>
                <th scope="col">Tipo</th>
                <th scope="col">Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e((string) $item['id_gasto']) ?></td>
                    <td><?= e(fecha($item['fecha'] ?? null)) ?></td>
                    <td><?= e($item['categoria'] ?? '—') ?></td>
                    <td><?= e($item['descripcion'] ?? '—') ?></td>
                    <td class="num"><?= e(ars($item['importe'] ?? 0)) ?></td>
                    <td><?= e($item['tipo_pago'] ?? '—') ?></td>
                    <td><?= estado_badge($item['estado'] ?? null) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
