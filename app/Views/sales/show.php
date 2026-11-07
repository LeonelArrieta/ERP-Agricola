<?php
$saleId = (int) $sale['id_venta'];
$cancelled = ($sale['estado'] ?? '') === 'Cancelado';
$hasAuthorizedInvoice = false;
foreach (($vouchers ?? []) as $voucher) {
    if ((string) $voucher['estado_fiscal'] === 'Autorizado' && in_array((int) $voucher['id_tipo_comprobante'], [1, 6, 11], true)) {
        $hasAuthorizedInvoice = true;
    }
}
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Comercial</p>
        <h1>Venta #<?= e((string) $saleId) ?></h1>
        <p><?= e($sale['cliente'] ?? '') ?></p>
    </div>
    <div class="form-actions">
        <?= estado_badge($sale['estado'] ?? null) ?>
        <?php if (!$cancelled && !$hasAuthorizedInvoice && in_array('facturacion.emitir', $permissions, true)): ?>
            <a class="button" href="<?= e(url('app/ventas/' . $saleId . '/facturar')) ?>">Emitir comprobante</a>
        <?php endif; ?>
        <?php if (!$cancelled && in_array('ventas.cancelar', $permissions, true)): ?>
            <form method="post" action="<?= e(url('app/ventas/' . $saleId . '/cancelar')) ?>" data-confirm="¿Cancelar la venta? Se revertirán lote, CxC y Caja según los efectos reales.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Cancelar venta</button>
            </form>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/ventas')) ?>">Volver</a>
    </div>
</div>

<section class="panel">
    <h2>Resumen de la venta</h2>
    <dl class="detail-list">
        <div><dt>Fecha</dt><dd><?= e(fecha($sale['fecha'] ?? null)) ?></dd></div>
        <div><dt>Cliente</dt><dd><?= e($sale['cliente'] ?? '—') ?></dd></div>
        <div><dt>Tipo de pago</dt><dd><?= e(($sale['tipo_pago'] ?? '') === 'Credito' ? 'Crédito' : ($sale['tipo_pago'] ?? '—')) ?></dd></div>
        <div><dt>Subtotal</dt><dd><?= e(ars($sale['subtotal'] ?? 0)) ?></dd></div>
        <div><dt>Impuesto</dt><dd><?= e(ars($sale['importe_alicuota'] ?? 0)) ?></dd></div>
        <div><dt>Total</dt><dd><?= e(ars($sale['total'] ?? 0)) ?></dd></div>
        <div><dt>Observaciones</dt><dd><?= e($sale['observaciones'] ?? '—') ?></dd></div>
    </dl>
</section>

<div class="panel">
    <h2>Detalle histórico</h2>
    <p>Estos valores quedaron congelados al confirmar la venta y no cambian si el producto o el lote se actualizan después.</p>
    <div class="table-wrap">
        <table>
            <caption class="visually-hidden">Detalle histórico de la venta</caption>
            <thead>
            <tr>
                <th scope="col">Producto</th>
                <th scope="col">Lote</th>
                <th scope="col" class="num">Cantidad</th>
                <th scope="col">Unidad</th>
                <th scope="col" class="num">Costo</th>
                <th scope="col" class="num">Margen</th>
                <th scope="col" class="num">Precio bruto</th>
                <th scope="col" class="num">Alícuota</th>
                <th scope="col" class="num">Subtotal</th>
                <th scope="col" class="num">Impuesto</th>
                <th scope="col" class="num">Precio final unitario</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($details ?? []) as $detail): ?>
                <tr>
                    <td><?= e($detail['producto'] ?? '—') ?></td>
                    <td>#<?= e((string) ($detail['id_lote_produccion'] ?? '')) ?></td>
                    <td class="num"><?= e(qty($detail['cantidad'] ?? 0)) ?></td>
                    <td><?= e($detail['unidad_medida'] ?? '—') ?></td>
                    <td class="num"><?= e(ars($detail['precio_costo'] ?? 0)) ?></td>
                    <td class="num"><?= e(number_format((float) ($detail['porcentaje_rentabilidad'] ?? 0) * 100, 2, ',', '.')) ?> %</td>
                    <td class="num"><?= e(ars($detail['precio_bruto'] ?? 0)) ?></td>
                    <td class="num"><?= e(number_format((float) ($detail['porcentaje_alicuota'] ?? 0), 2, ',', '.')) ?> %</td>
                    <td class="num"><?= e(ars($detail['subtotal'] ?? 0)) ?></td>
                    <td class="num"><?= e(ars($detail['importe_alicuota'] ?? 0)) ?></td>
                    <td class="num"><?= e(ars($detail['precio_neto_final'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($cxc ?? null) !== null): ?>
    <div class="panel">
        <h2>Cuenta por cobrar</h2>
        <dl class="detail-list">
            <div><dt>Importe original</dt><dd><?= e(ars($cxc['importe_original'])) ?></dd></div>
            <div><dt>Saldo pendiente</dt><dd><?= e(ars($cxc['saldo_actual'])) ?></dd></div>
            <div><dt>Estado</dt><dd><?= estado_badge($cxc['estado'] ?? null) ?></dd></div>
        </dl>
        <?php if (!$cancelled && (float) $cxc['saldo_actual'] > 0 && in_array('cobranzas.crear', $permissions, true)): ?>
            <form class="filters" method="post" action="<?= e(url('app/ventas/' . $saleId . '/cobro')) ?>" data-confirm="¿Registrar el cobro? Se acreditará en Caja y se reducirá la cuenta por cobrar.">
                <div class="field">
                    <label for="cobro_importe">Importe a cobrar</label>
                    <input id="cobro_importe" type="number" step="0.01" min="0.01" max="<?= e((string) $cxc['saldo_actual']) ?>" name="importe" required>
                    <span class="hint">Saldo pendiente: <?= e(ars($cxc['saldo_actual'])) ?></span>
                </div>
                <div class="field">
                    <label for="cobro_fecha">Fecha</label>
                    <input id="cobro_fecha" type="date" name="fecha" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="field">
                    <label for="cobro_observaciones">Observaciones</label>
                    <input id="cobro_observaciones" type="text" name="observaciones">
                </div>
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Registrar cobro</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif (($sale['tipo_pago'] ?? '') === 'Contado' && !$cancelled): ?>
    <div class="flash flash-info">Venta de contado: el ingreso de Caja se registró al confirmar la venta.</div>
<?php endif; ?>

<?php if (($vouchers ?? []) !== []): ?>
    <div class="panel">
        <h2>Comprobantes fiscales</h2>
        <div class="table-wrap">
            <table>
                <caption class="visually-hidden">Comprobantes fiscales de la venta</caption>
                <thead>
                <tr>
                    <th scope="col">Tipo</th>
                    <th scope="col">Punto / número</th>
                    <th scope="col">CAE</th>
                    <th scope="col">Estado fiscal</th>
                    <th scope="col">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($vouchers as $voucher): ?>
                    <?php $voucherId = (int) $voucher['id_comprobante_fiscal']; ?>
                    <tr>
                        <td><?= e($voucher['tipo_nombre'] ?? '') ?></td>
                        <td>PV <?= e((string) $voucher['punto_venta_numero']) ?> — <?= e((string) ($voucher['numero'] ?? '—')) ?></td>
                        <td><?= e($voucher['cae'] ?? '—') ?></td>
                        <td><?= estado_badge($voucher['estado_fiscal'] ?? null) ?></td>
                        <td><a class="button ghost small" href="<?= e(url('app/comprobantes/' . $voucherId)) ?>" aria-label="Ver comprobante <?= e($voucher['tipo_nombre'] ?? '') ?> número <?= e((string) ($voucher['numero'] ?? '')) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif (!$cancelled && in_array('facturacion.emitir', $permissions, true)): ?>
    <div class="flash flash-info">La venta todavía no tiene comprobantes fiscales emitidos.</div>
<?php endif; ?>
