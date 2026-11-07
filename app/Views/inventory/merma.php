<?php
$errors = $errors ?? [];
$fieldError = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field-error">' . e($errors[$field]) . '</span>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Inventario</p>
        <h1>Registrar merma</h1>
        <p>La merma disminuye stock y queda registrada como movimiento histórico.</p>
    </div>
</div>

<div class="flash flash-warning">
    Esta operación disminuirá el stock disponible del insumo seleccionado. No genera venta, CxC, CxP ni movimiento de Caja.
</div>

<?= empty($errors) ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/inventario/mermas')) ?>" data-confirm="¿Registrar la merma? Se descontará stock del insumo seleccionado y no se puede deshacer.">
        <div class="form-grid">
            <div class="field">
                <label for="id_insumo">Insumo afectado</label>
                <select id="id_insumo" name="id_insumo" required<?= $invalid('id_insumo') ?>>
                    <option value="">Seleccionar insumo</option>
                    <?php foreach (($insumos ?? []) as $insumo): ?>
                        <option value="<?= e((string) $insumo['id_insumo']) ?>"<?= (string) ($values['id_insumo'] ?? '') === (string) $insumo['id_insumo'] ? ' selected' : '' ?>>
                            <?= e($insumo['nombre']) ?> — stock <?= e(qty($insumo['stock_actual'])) ?> <?= e($insumo['unidad_medida']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError('id_insumo') ?>
            </div>
            <div class="field">
                <label for="cantidad">Cantidad a descontar</label>
                <input id="cantidad" type="number" step="0.001" min="0.001" name="cantidad" value="<?= e($values['cantidad'] ?? '') ?>" required<?= $invalid('cantidad') ?>>
                <?= $fieldError('cantidad') ?>
            </div>
            <div class="field">
                <label for="motivo">Motivo</label>
                <input id="motivo" type="text" name="motivo" value="<?= e($values['motivo'] ?? '') ?>" placeholder="Deterioro, vencimiento, daño u otro" required<?= $invalid('motivo') ?>>
                <?= $fieldError('motivo') ?>
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button class="danger">Registrar merma</button>
            <a class="button ghost" href="<?= e(url('app/inventario')) ?>">Volver</a>
        </div>
    </form>
</div>
