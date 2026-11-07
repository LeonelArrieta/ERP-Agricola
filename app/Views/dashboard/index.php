<?php
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
$alerts = [];
if (($summary['receivables']['overdue'] ?? 0) > 0) {
    $alerts[] = [
        'severity' => 'warning',
        'label' => 'Cuentas por cobrar vencidas',
        'value' => ars($summary['receivables']['overdue']),
        'url' => $can('cobranzas.ver') ? url('app/cxc') : null,
    ];
}
if (($summary['payables']['overdue'] ?? 0) > 0) {
    $alerts[] = [
        'severity' => 'warning',
        'label' => 'Cuentas por pagar vencidas',
        'value' => ars($summary['payables']['overdue']),
        'url' => $can('pagos.ver') ? url('app/cxp') : null,
    ];
}
if ((int) ($summary['production']['provisional_lots'] ?? 0) > 0) {
    $alerts[] = [
        'severity' => 'warning',
        'label' => 'Lotes con costo provisional sin consolidar',
        'value' => (string) (int) $summary['production']['provisional_lots'],
        'url' => $can('produccion.ver') ? url('app/ciclos-cultivo') : null,
    ];
}
if ((int) ($summary['fiscal']['uncertain'] ?? 0) > 0) {
    $alerts[] = [
        'severity' => 'danger',
        'label' => 'Comprobantes con resultado incierto o error de comunicación',
        'value' => (string) (int) $summary['fiscal']['uncertain'],
        'url' => $can('facturacion.ver') ? url('app/comprobantes?estado_fiscal=ResultadoIncierto') : null,
    ];
}
if ((int) ($summary['fiscal']['rejected'] ?? 0) > 0) {
    $alerts[] = [
        'severity' => 'danger',
        'label' => 'Comprobantes fiscales rechazados',
        'value' => (string) (int) $summary['fiscal']['rejected'],
        'url' => $can('facturacion.ver') ? url('app/comprobantes?estado_fiscal=Rechazado') : null,
    ];
}
if ((int) ($summary['inventory']['zero_stock_inputs'] ?? 0) > 0) {
    $alerts[] = [
        'severity' => 'warning',
        'label' => 'Insumos activos sin stock',
        'value' => (string) (int) $summary['inventory']['zero_stock_inputs'],
        'url' => $can('insumos.ver') ? url('app/insumos') : null,
    ];
}
$periodKey = (string) ($summary['period']['key'] ?? 'month');
$isCustomPeriod = $periodKey === 'custom';
$receivablesOverdue = (float) ($summary['receivables']['overdue'] ?? 0);
$payablesOverdue = (float) ($summary['payables']['overdue'] ?? 0);
$cashFlow = $summary['cash_flow'] ?? ['buckets' => [], 'inflows' => 0, 'outflows' => 0, 'net' => 0, 'max_value' => 0, 'has_movements' => false];
$cashFlowMax = max(1.0, (float) ($cashFlow['max_value'] ?? 0));
$cashFlowChartHeight = 150;
$cashFlowHeight = static fn (float $value): int => $value > 0 ? max(2, (int) round(($value / $cashFlowMax) * $cashFlowChartHeight)) : 0;
$cashFlowBuckets = $cashFlow['buckets'] ?? [];
$cashFlowSlots = max(1, count($cashFlowBuckets));
$cashFlowSlotWidth = 1000 / $cashFlowSlots;
$cashFlowBarWidth = min(56, max(8, $cashFlowSlotWidth * 0.32));
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Mini ERP</p>
        <h1>Panel operativo</h1>
        <p>Estado financiero, producción y alertas del período.</p>
    </div>
    <form class="filters dashboard-period" method="get" action="<?= e(url('dashboard')) ?>" data-period-form>
        <div class="field">
            <label for="period">Período</label>
            <select id="period" name="period">
                <option value="month"<?= $periodKey === 'month' ? ' selected' : '' ?>>Mes actual</option>
                <option value="today"<?= $periodKey === 'today' ? ' selected' : '' ?>>Hoy</option>
                <option value="custom"<?= $isCustomPeriod ? ' selected' : '' ?>>Rango personalizado</option>
            </select>
        </div>
        <div class="field period-custom" data-period-custom<?= $isCustomPeriod ? '' : ' hidden' ?>>
            <label for="from">Desde</label>
            <input id="from" type="date" name="from" value="<?= e($summary['period']['from'] ?? '') ?>">
        </div>
        <div class="field period-custom" data-period-custom<?= $isCustomPeriod ? '' : ' hidden' ?>>
            <label for="to">Hasta</label>
            <input id="to" type="date" name="to" value="<?= e($summary['period']['to'] ?? '') ?>">
        </div>
        <button class="button ghost">Actualizar</button>
    </form>
</div>

<p class="period-label">Período mostrado: <?= e($summary['period']['label'] ?? 'Mes actual') ?></p>

<section class="metric-grid" aria-label="Indicadores del período">
    <div class="metric-card is-primary"><span>Caja</span><strong><?= e(ars($summary['cash']['balance'] ?? 0)) ?></strong><small>Saldo disponible</small></div>
    <div class="metric-card<?= $receivablesOverdue > 0 ? ' is-alert' : '' ?>"><span>CxC pendiente</span><strong><?= e(ars($summary['receivables']['pending'] ?? 0)) ?></strong><small>Vencido: <?= e(ars($receivablesOverdue)) ?></small></div>
    <div class="metric-card<?= $payablesOverdue > 0 ? ' is-alert' : '' ?>"><span>CxP pendiente</span><strong><?= e(ars($summary['payables']['pending'] ?? 0)) ?></strong><small>Vencido: <?= e(ars($payablesOverdue)) ?></small></div>
    <div class="metric-card"><span>Ventas del período</span><strong><?= e(ars($summary['sales']['total'] ?? 0)) ?></strong><small><?= e((string) ($summary['sales']['quantity'] ?? 0)) ?> operaciones</small></div>
    <div class="metric-card"><span>Margen bruto</span><strong><?= e(ars($summary['sales']['margin_amount'] ?? 0)) ?></strong><small><?= e(number_format((float) ($summary['sales']['margin_percent'] ?? 0), 2, ',', '.')) ?> % sobre subtotal</small></div>
    <div class="metric-card"><span>Compras del período</span><strong><?= e(ars($summary['purchases']['total'] ?? 0)) ?></strong><small><?= e((string) ($summary['purchases']['quantity'] ?? 0)) ?> operaciones</small></div>
</section>

<figure class="panel cashflow-panel">
    <figcaption class="panel-head">
        <div>
            <h2>Flujo de Caja</h2>
            <p class="hint">Movimientos reales del período. No incluye cuentas pendientes.</p>
        </div>
    </figcaption>

    <dl class="cashflow-summary">
        <div><dt>Ingresos</dt><dd class="num"><?= e(ars($cashFlow['inflows'] ?? 0)) ?></dd></div>
        <div><dt>Egresos</dt><dd class="num"><?= e(ars($cashFlow['outflows'] ?? 0)) ?></dd></div>
        <div><dt>Neto</dt><dd class="num<?= (float) ($cashFlow['net'] ?? 0) < 0 ? ' is-negative' : '' ?>"><?= e(ars($cashFlow['net'] ?? 0)) ?></dd></div>
    </dl>

    <?php if (!($cashFlow['has_movements'] ?? false)): ?>
        <?= empty_state('No hay movimientos de Caja en el período.', 'Los cobros, pagos y gastos de contado aparecerán aquí.') ?>
    <?php else: ?>
        <div class="cashflow-chart" role="group" aria-label="Gráfico de flujo de Caja del período">
            <svg class="cashflow-svg" viewBox="0 0 1000 <?= $cashFlowChartHeight ?>" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <line class="cashflow-baseline" x1="0" y1="<?= $cashFlowChartHeight ?>" x2="1000" y2="<?= $cashFlowChartHeight ?>"></line>
                <?php foreach ($cashFlowBuckets as $index => $bucket): ?>
                    <?php
                    $center = $index * $cashFlowSlotWidth + $cashFlowSlotWidth / 2;
                    $inHeight = $cashFlowHeight((float) $bucket['inflows']);
                    $outHeight = $cashFlowHeight((float) $bucket['outflows']);
                    ?>
                    <g>
                        <title><?= e($bucket['label'] . ': ingresos ' . ars($bucket['inflows']) . '; egresos ' . ars($bucket['outflows'])) ?></title>
                        <rect class="cashflow-bar is-in" x="<?= e((string) ($center - $cashFlowBarWidth - 2)) ?>" y="<?= e((string) ($cashFlowChartHeight - $inHeight)) ?>" width="<?= e((string) $cashFlowBarWidth) ?>" height="<?= e((string) $inHeight) ?>" rx="2"></rect>
                        <rect class="cashflow-bar is-out" x="<?= e((string) ($center + 2)) ?>" y="<?= e((string) ($cashFlowChartHeight - $outHeight)) ?>" width="<?= e((string) $cashFlowBarWidth) ?>" height="<?= e((string) $outHeight) ?>" rx="2"></rect>
                    </g>
                <?php endforeach; ?>
            </svg>
            <div class="cashflow-labels" aria-hidden="true">
                <?php foreach ($cashFlowBuckets as $bucket): ?>
                    <span class="cashflow-label"><?= e($bucket['label']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="cashflow-legend">
            <span class="cashflow-key is-in"></span> Ingresos
            <span class="cashflow-key is-out"></span> Egresos
        </p>
        <div class="visually-hidden">
        <table>
            <caption>Flujo de Caja por período</caption>
            <thead><tr><th scope="col">Período</th><th scope="col">Ingresos</th><th scope="col">Egresos</th><th scope="col">Neto</th></tr></thead>
            <tbody>
            <?php foreach (($cashFlow['buckets'] ?? []) as $bucket): ?>
                <tr>
                    <th scope="row"><?= e($bucket['label']) ?></th>
                    <td><?= e(ars($bucket['inflows'])) ?></td>
                    <td><?= e(ars($bucket['outflows'])) ?></td>
                    <td><?= e(ars($bucket['net'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</figure>

<section class="dashboard-grid">
    <div class="panel">
        <h2>Alertas operativas</h2>
        <?php if ($alerts === []): ?>
            <?= empty_state('Sin alertas operativas.', 'No hay vencimientos, lotes provisionales ni comprobantes pendientes de revisión.') ?>
        <?php else: ?>
            <ul class="alert-list" aria-label="Alertas operativas">
                <?php foreach ($alerts as $alert): ?>
                    <li class="alert-item is-<?= e($alert['severity']) ?>">
                        <span><?= e($alert['label']) ?></span>
                        <?php if ($alert['url'] !== null): ?>
                            <a href="<?= e($alert['url']) ?>"><strong><?= e($alert['value']) ?></strong> · Ver</a>
                        <?php else: ?>
                            <strong><?= e($alert['value']) ?></strong>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h2>Producción e inventario</h2>
        <div class="stat-list">
            <div><span>En curso</span><strong><?= e((string) ($summary['production']['cycles']['EnCurso'] ?? 0)) ?></strong></div>
            <div><span>Planificados</span><strong><?= e((string) ($summary['production']['cycles']['Planificado'] ?? 0)) ?></strong></div>
            <div><span>Tareas en curso</span><strong><?= e((string) ($summary['production']['tasks']['EnCurso'] ?? 0)) ?></strong></div>
            <div><span>Lotes provisionales</span><strong><?= e((string) ($summary['production']['provisional_lots'] ?? 0)) ?></strong></div>
            <div><span>Cosechado del mes</span><strong><?= e(qty($summary['production']['harvested_current_month'] ?? 0)) ?></strong></div>
            <div><span>Movimientos últimos 30 días</span><strong><?= e((string) ($summary['inventory']['movements_last_30_days'] ?? 0)) ?></strong></div>
        </div>
    </div>
</section>

<section class="dashboard-grid">
    <div class="panel">
        <div class="panel-head">
            <h2>Ventas recientes</h2>
            <?php if ($can('ventas.ver')): ?><a href="<?= e(url('app/ventas')) ?>">Ver todas</a><?php endif; ?>
        </div>
        <?php if (($summary['recent_sales'] ?? []) === []): ?>
            <?= empty_state('Sin ventas recientes.', 'Las ventas registradas en el período aparecerán aquí.') ?>
        <?php else: ?>
            <div class="table-wrap"><table><caption class="visually-hidden">Ventas recientes</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Cliente</th><th scope="col" class="num">Total</th><th scope="col">Estado</th></tr></thead><tbody>
                <?php foreach ($summary['recent_sales'] as $sale): ?><tr><td><?= e(fecha($sale['fecha'] ?? null)) ?></td><td><?= e($sale['cliente']) ?></td><td class="num"><?= e(ars($sale['total'])) ?></td><td><?= estado_badge($sale['estado'] ?? null) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-head">
            <h2>Compras recientes</h2>
            <?php if ($can('compras.ver')): ?><a href="<?= e(url('app/compras')) ?>">Ver todas</a><?php endif; ?>
        </div>
        <?php if (($summary['recent_purchases'] ?? []) === []): ?>
            <?= empty_state('Sin compras recientes.', 'Las compras registradas en el período aparecerán aquí.') ?>
        <?php else: ?>
            <div class="table-wrap"><table><caption class="visually-hidden">Compras recientes</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Proveedor</th><th scope="col" class="num">Total</th><th scope="col">Estado</th></tr></thead><tbody>
                <?php foreach ($summary['recent_purchases'] as $purchase): ?><tr><td><?= e(fecha($purchase['fecha'] ?? null)) ?></td><td><?= e($purchase['proveedor']) ?></td><td class="num"><?= e(ars($purchase['total'])) ?></td><td><?= estado_badge($purchase['estado'] ?? null) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</section>

<section class="grid">
    <?php if ($can('ventas.crear')): ?>
        <a class="tile" href="<?= e(url('app/ventas/nuevo')) ?>"><strong>Nueva venta</strong><span>Vender un lote con disponibilidad</span></a>
    <?php endif; ?>
    <?php if ($can('compras.crear')): ?>
        <a class="tile" href="<?= e(url('app/compras/nuevo')) ?>"><strong>Nueva compra</strong><span>Ingresar insumos y actualizar PPP</span></a>
    <?php endif; ?>
    <?php if ($can('inventario.merma')): ?>
        <a class="tile" href="<?= e(url('app/inventario/mermas/nueva')) ?>"><strong>Registrar merma</strong><span>Ajustar stock por deterioro</span></a>
    <?php endif; ?>
</section>
