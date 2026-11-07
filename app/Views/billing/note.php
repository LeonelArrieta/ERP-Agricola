<?php
$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$errors = $errors ?? [];
$selectedType = $value('id_tipo_comprobante');
foreach (($types ?? []) as $type) {
    if ($selectedType === '' && (string) $type['clase'] === (string) ($original['clase'] ?? '')) {
        $selectedType = (string) $type['id_tipo_comprobante'];
        break;
    }
}
$fieldError = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field-error">' . e($errors[$field]) . '</span>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
$originalId = (int) $original['id_comprobante_fiscal'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Emitir nota</h1>
        <p>Sobre <?= e($original['tipo_nombre'] ?? '') ?> PV <?= e((string) $original['punto_venta_numero']) ?> — <?= e((string) $original['numero']) ?></p>
    </div>
    <div class="form-actions">
        <a class="button ghost" href="<?= e(url('app/comprobantes/' . $originalId)) ?>">Volver al comprobante</a>
    </div>
</div>

<?= $errors === [] ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="panel">
    <h2>Comprobante original</h2>
    <dl class="detail-list">
        <div><dt>Tipo</dt><dd><?= e($original['tipo_nombre'] ?? '') ?> (clase <?= e($original['clase'] ?? '') ?>)</dd></div>
        <div><dt>Total</dt><dd><?= e(ars($original['imp_total'])) ?></dd></div>
        <div><dt>Neto</dt><dd><?= e(ars($original['imp_neto'])) ?></dd></div>
        <div><dt>IVA</dt><dd><?= e(ars($original['imp_iva'])) ?></dd></div>
        <div><dt>CAE</dt><dd><?= e($original['cae'] ?? '—') ?></dd></div>
    </dl>
</div>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/comprobantes/' . $originalId . '/notas')) ?>" data-confirm="¿Emitir la nota? La operación fiscal es irreversible.">
        <div class="form-grid">
            <div class="field">
                <label for="id_tipo_comprobante">Tipo de nota</label>
                <select id="id_tipo_comprobante" name="id_tipo_comprobante" required<?= $invalid('id_tipo_comprobante') ?>>
                    <?php foreach (($types ?? []) as $type): ?>
                        <option value="<?= e((string) $type['id_tipo_comprobante']) ?>"<?= $selectedType === (string) $type['id_tipo_comprobante'] ? ' selected' : '' ?>><?= e($type['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">La clase debe coincidir con el comprobante original.</span>
                <?= $fieldError('id_tipo_comprobante') ?>
            </div>
            <div class="field">
                <label for="id_punto_venta">Punto de venta</label>
                <select id="id_punto_venta" name="id_punto_venta" required<?= $invalid('id_punto_venta') ?>>
                    <?php foreach (($points ?? []) as $point): ?>
                        <option value="<?= e((string) $point['id_punto_venta']) ?>"<?= $value('id_punto_venta') === (string) $point['id_punto_venta'] ? ' selected' : '' ?>>PV <?= e((string) $point['numero']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError('id_punto_venta') ?>
            </div>
            <div class="field">
                <label for="importe">Importe total de la nota</label>
                <input id="importe" type="number" step="0.01" min="0.01" name="importe" value="<?= e($value('importe')) ?>" required<?= $invalid('importe') ?>>
                <span class="hint">El neto y el IVA se distribuyen según las alícuotas del comprobante original.</span>
                <?= $fieldError('importe') ?>
            </div>
            <div class="field">
                <label for="fecha">Fecha de emisión</label>
                <input id="fecha" type="date" name="fecha" value="<?= e($value('fecha') !== '' ? $value('fecha') : date('Y-m-d')) ?>"<?= $invalid('fecha') ?>>
                <?= $fieldError('fecha') ?>
            </div>
        </div>
        <input type="hidden" name="concepto" value="1">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button>Emitir nota</button>
        </div>
    </form>
</div>
