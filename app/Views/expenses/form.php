<?php
$errors = $errors ?? [];
$fieldError = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field-error">' . e($errors[$field]) . '</span>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Finanzas</p>
        <h1>Nuevo gasto</h1>
        <p>El gasto de contado genera egreso de Caja; el gasto diferido genera una CxP.</p>
    </div>
</div>

<?= empty($errors) ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/gastos')) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="id_categoria_gasto">Categoría</label>
                <select id="id_categoria_gasto" name="id_categoria_gasto" required<?= $invalid('id_categoria_gasto') ?>>
                    <option value="">Seleccionar categoría</option>
                    <?php foreach (($categories ?? []) as $category): ?>
                        <option value="<?= e((string) $category['id_categoria_gasto']) ?>"<?= (string) ($values['id_categoria_gasto'] ?? '') === (string) $category['id_categoria_gasto'] ? ' selected' : '' ?>>
                            <?= e($category['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError('id_categoria_gasto') ?>
            </div>
            <div class="field">
                <label for="fecha">Fecha</label>
                <input id="fecha" type="date" name="fecha" value="<?= e($values['fecha'] ?? date('Y-m-d')) ?>" required<?= $invalid('fecha') ?>>
                <?= $fieldError('fecha') ?>
            </div>
            <div class="field">
                <label for="descripcion">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" value="<?= e($values['descripcion'] ?? '') ?>" required<?= $invalid('descripcion') ?>>
                <?= $fieldError('descripcion') ?>
            </div>
            <div class="field">
                <label for="importe">Importe</label>
                <input id="importe" type="number" step="0.01" min="0.01" name="importe" value="<?= e($values['importe'] ?? '') ?>" required<?= $invalid('importe') ?>>
                <?= $fieldError('importe') ?>
            </div>
            <div class="field">
                <label for="tipo_pago">Tipo de pago</label>
                <select id="tipo_pago" name="tipo_pago" required<?= $invalid('tipo_pago') ?>>
                    <option value="Contado"<?= ($values['tipo_pago'] ?? '') === 'Contado' ? ' selected' : '' ?>>Contado</option>
                    <option value="Diferido"<?= ($values['tipo_pago'] ?? 'Diferido') === 'Diferido' ? ' selected' : '' ?>>Diferido</option>
                </select>
                <?= $fieldError('tipo_pago') ?>
            </div>
            <div class="field">
                <label for="observaciones">Observaciones</label>
                <input id="observaciones" type="text" name="observaciones" value="<?= e($values['observaciones'] ?? '') ?>">
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button>Registrar gasto</button>
            <a class="button ghost" href="<?= e(url('app/gastos')) ?>">Volver</a>
        </div>
    </form>
</div>
