<?php
$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$errors = $errors ?? [];
$defaults = $defaults ?? [];
$client = $defaults['client'] ?? [
    'id' => 0,
    'name' => '',
    'doc_tipo_label' => '',
    'doc_nro' => '',
    'condicion_iva_label' => '',
    'complete' => false,
];
$types = $defaults['types'] ?? [];
$points = $defaults['points'] ?? [];
$selectedType = $value('id_tipo_comprobante') !== ''
    ? $value('id_tipo_comprobante')
    : (string) ($defaults['suggested_type'] ?? '');
$selectedPoint = $value('id_punto_venta') !== ''
    ? $value('id_punto_venta')
    : (string) ($defaults['default_point'] ?? '');
$selectedDate = $value('fecha') !== '' ? $value('fecha') : (string) ($defaults['date'] ?? date('Y-m-d'));
$canEmit = (bool) ($defaults['can_emit'] ?? false);
$selectedTypeLabel = 'Sin comprobante';
foreach ($types as $type) {
    if ((string) $type['id_tipo_comprobante'] === $selectedType) {
        $selectedTypeLabel = (string) $type['nombre'];
        break;
    }
}
$selectedPointLabel = 'Sin punto de venta';
foreach ($points as $point) {
    if ((string) $point['id_punto_venta'] === $selectedPoint) {
        $selectedPointLabel = 'PV ' . $point['numero'] . ($point['descripcion'] !== null ? ' — ' . $point['descripcion'] : '');
        break;
    }
}
$documentLabel = ($client['doc_tipo_label'] ?? '') !== '' && ($client['doc_nro'] ?? '') !== ''
    ? $client['doc_tipo_label'] . ': ' . $client['doc_nro']
    : 'Sin datos';
$fieldError = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field-error">' . e($errors[$field]) . '</span>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Emitir comprobante</h1>
        <p>Venta #<?= e((string) $saleId) ?> — <?= e($sale['cliente'] ?? '') ?> (<?= e(fecha($sale['fecha'] ?? null)) ?>)</p>
    </div>
    <div class="form-actions">
        <a class="button ghost" href="<?= e(url('app/ventas/' . $saleId)) ?>">Volver a la venta</a>
    </div>
</div>

<?= $errors === [] ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="billing-summary">
    <section class="panel">
        <h2>Receptor</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($client['name'] ?? '') ?></dd></div>
            <div><dt>Documento</dt><dd><?= e($documentLabel) ?></dd></div>
            <div><dt>Condición IVA</dt><dd><?= e($client['condicion_iva_label'] ?? '') ?: 'Sin definir' ?></dd></div>
        </dl>
        <?php if (!($client['complete'] ?? false)): ?>
            <div class="flash flash-warning" role="alert">
                El cliente no tiene datos fiscales completos.
                <?php if (($client['id'] ?? 0) > 0): ?>
                    <a href="<?= e(url('app/clientes/' . (int) $client['id'] . '/editar')) ?>">Completar datos fiscales</a>.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Comprobante a emitir</h2>
        <dl class="detail-list">
            <div><dt>Comprobante</dt><dd data-billing-summary-type><?= e($selectedTypeLabel) ?></dd></div>
            <div><dt>Punto de venta</dt><dd data-billing-summary-point><?= e($selectedPointLabel) ?></dd></div>
            <div><dt>Concepto</dt><dd>Productos</dd></div>
            <div><dt>Fecha de emisión</dt><dd data-billing-summary-date><?= e(fecha($selectedDate)) ?></dd></div>
            <div><dt>Total</dt><dd><strong><?= e(ars($sale['total'] ?? 0)) ?></strong></dd></div>
        </dl>

        <?php if (!$canEmit): ?>
            <div class="flash flash-warning" role="alert">
                <?= e($defaults['reason'] ?? 'No se puede emitir el comprobante con los datos actuales.') ?>
            </div>
        <?php else: ?>
            <form class="stacked" method="post" action="<?= e(url('app/ventas/' . $saleId . '/facturar')) ?>" data-billing-form data-client-name="<?= e($client['name'] ?? '') ?>" data-total="<?= e(ars($sale['total'] ?? 0)) ?>" data-confirm="¿Emitir el comprobante? La operación fiscal es irreversible.">
                <input type="hidden" name="concepto" value="1">
                <details class="billing-options"<?= $errors !== [] ? ' open' : '' ?>>
                    <summary>Cambiar datos fiscales</summary>
                    <div class="form-grid">
                        <div class="field">
                            <label for="id_tipo_comprobante">Tipo de comprobante</label>
                            <select id="id_tipo_comprobante" name="id_tipo_comprobante" required<?= $invalid('id_tipo_comprobante') ?>>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= e((string) $type['id_tipo_comprobante']) ?>"<?= $selectedType === (string) $type['id_tipo_comprobante'] ? ' selected' : '' ?>><?= e($type['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint">Sugerido según la condición IVA del cliente y del emisor.</span>
                            <?= $fieldError('id_tipo_comprobante') ?>
                        </div>
                        <div class="field">
                            <label for="id_punto_venta">Punto de venta</label>
                            <select id="id_punto_venta" name="id_punto_venta" required<?= $invalid('id_punto_venta') ?>>
                                <?php foreach ($points as $point): ?>
                                    <option value="<?= e((string) $point['id_punto_venta']) ?>"<?= $selectedPoint === (string) $point['id_punto_venta'] ? ' selected' : '' ?>>PV <?= e((string) $point['numero']) ?><?= ($point['descripcion'] ?? null) !== null ? ' — ' . e($point['descripcion']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $fieldError('id_punto_venta') ?>
                        </div>
                        <div class="field">
                            <label for="fecha">Fecha de emisión</label>
                            <input id="fecha" type="date" name="fecha" value="<?= e($selectedDate) ?>" required<?= $invalid('fecha') ?>>
                            <?= $fieldError('fecha') ?>
                        </div>
                    </div>
                </details>
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <div class="form-actions">
                    <button>Emitir comprobante</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
