<?php
$purchaseId = (int) $purchase['id_compra'];
$cancelled = ($purchase['estado'] ?? '') === 'Cancelado';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Comercial</p>
        <h1>Compra #<?= e((string) $purchaseId) ?></h1>
        <p><?= e($purchase['proveedor'] ?? '') ?></p>
    </div>
    <div class="form-actions">
        <?= estado_badge($purchase['estado'] ?? null) ?>
        <?php if (!$cancelled && in_array('compras.cancelar', $permissions, true)): ?>
            <form method="post" action="<?= e(url('app/compras/' . $purchaseId . '/cancelar')) ?>" data-confirm="¿Cancelar la compra? Se revertirán inventario, CxP y Caja según los efectos reales.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Cancelar compra</button>
            </form>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/compras')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Fecha</dt><dd><?= e(fecha($purchase['fecha'] ?? null)) ?></dd></div>
    <div><dt>Proveedor</dt><dd><?= e($purchase['proveedor'] ?? '—') ?></dd></div>
    <div><dt>Tipo de pago</dt><dd><?= e($purchase['tipo_pago'] ?? '—') ?></dd></div>
    <div><dt>Subtotal</dt><dd><?= e(ars($purchase['subtotal'] ?? 0)) ?></dd></div>
    <div><dt>Total</dt><dd><?= e(ars($purchase['total'] ?? 0)) ?></dd></div>
    <div><dt>Observaciones</dt><dd><?= e($purchase['observaciones'] ?? '—') ?></dd></div>
</dl>

<div class="panel">
    <h2>Detalle</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Insumo</th>
                <th scope="col" class="num">Cantidad</th>
                <th scope="col">Unidad</th>
                <th scope="col" class="num">Costo unitario</th>
                <th scope="col" class="num">Importe</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($details ?? []) as $detail): ?>
                <tr>
                    <td><?= e($detail['insumo'] ?? '—') ?></td>
                    <td class="num"><?= e(qty($detail['cantidad'] ?? 0)) ?></td>
                    <td><?= e($detail['unidad_medida'] ?? '—') ?></td>
                    <td class="num"><?= e(ars($detail['costo_unitario'] ?? 0)) ?></td>
                    <td class="num"><?= e(ars($detail['importe_linea'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($cxp ?? null) !== null): ?>
    <div class="panel">
        <h2>Cuenta por pagar</h2>
        <dl class="detail-list">
            <div><dt>Importe original</dt><dd><?= e(ars($cxp['importe_original'])) ?></dd></div>
            <div><dt>Saldo pendiente</dt><dd><?= e(ars($cxp['saldo_actual'])) ?></dd></div>
            <div><dt>Estado</dt><dd><?= estado_badge($cxp['estado'] ?? null) ?></dd></div>
        </dl>
        <?php if (!$cancelled && (float) $cxp['saldo_actual'] > 0 && in_array('pagos.crear', $permissions, true)): ?>
            <form class="filters" method="post" action="<?= e(url('app/compras/' . $purchaseId . '/pago')) ?>" data-confirm="¿Registrar el pago? Se debitará de Caja y se reducirá la cuenta por pagar.">
                <div class="field">
                    <label for="pago_importe">Importe a pagar</label>
                    <input id="pago_importe" type="number" step="0.01" min="0.01" max="<?= e((string) $cxp['saldo_actual']) ?>" name="importe" required>
                    <span class="hint">Saldo pendiente: <?= e(ars($cxp['saldo_actual'])) ?></span>
                </div>
                <div class="field">
                    <label for="pago_fecha">Fecha</label>
                    <input id="pago_fecha" type="date" name="fecha" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="field">
                    <label for="pago_observaciones">Observaciones</label>
                    <input id="pago_observaciones" type="text" name="observaciones">
                </div>
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Registrar pago</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif (($purchase['tipo_pago'] ?? '') === 'Contado' && !$cancelled): ?>
    <div class="flash flash-info">Compra de contado: el egreso de Caja se registró al confirmar la compra.</div>
<?php endif; ?>
