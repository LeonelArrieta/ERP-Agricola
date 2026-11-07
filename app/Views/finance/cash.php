<div class="page-head">
    <div>
        <p class="eyebrow">Finanzas</p>
        <h1>Caja</h1>
        <p>Dinero efectivamente disponible y sus movimientos históricos.</p>
    </div>
    <div class="page-head-metric">
        <span>Saldo actual de Caja</span>
        <strong><?= e(ars($balance ?? 0)) ?></strong>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('app/caja/movimientos')) ?>">
    <div class="field">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <?php foreach (['Ingreso', 'Egreso', 'ReversionIngreso', 'ReversionEgreso'] as $type): ?>
                <option value="<?= e($type) ?>"<?= ($filters['tipo'] ?? '') === $type ? ' selected' : '' ?>><?= e(estado_label($type)) ?></option>
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
        <a class="button ghost" href="<?= e(url('app/caja/movimientos')) ?>">Limpiar filtros</a>
    <?php endif; ?>
</form>

<div class="panel">
    <h2>Movimientos</h2>
    <?php if (($items ?? []) === []): ?>
        <?php $hasFilters = has_active_filters($filters ?? []); ?>
        <?= empty_state(
            $hasFilters ? 'No se encontraron movimientos con esos filtros.' : 'Todavía no hay movimientos de Caja.',
            $hasFilters ? 'Probá con otro rango o limpiá los filtros.' : 'Los cobros, pagos y gastos de contado generan movimientos.',
            $hasFilters ? ['url' => url('app/caja/movimientos'), 'label' => 'Limpiar filtros'] : null
        ) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" class="num">Importe</th>
                    <th scope="col">Concepto</th>
                    <th scope="col">Origen</th>
                    <th scope="col">Usuario</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e(fecha_hora($item['fecha_hora'] ?? null)) ?></td>
                        <td><span class="badge"><?= e($item['tipo'] ?? '') ?></span></td>
                        <td class="num"><?= e(ars($item['importe'] ?? 0)) ?></td>
                        <td><?= e($item['concepto'] ?? '—') ?></td>
                        <td><?= e(($item['origen_tipo'] ?? '—') . (($item['origen_id'] ?? null) !== null ? ' #' . $item['origen_id'] : '')) ?></td>
                        <td><?= e($item['usuario'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
