<?php
$errors = $errors ?? [];
$fieldError = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field-error">' . e($errors[$field]) . '</span>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Producción</p>
        <h1>Nuevo ciclo de cultivo</h1>
        <p>El ciclo inicia en estado Planificado.</p>
    </div>
</div>

<?= empty($errors) ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/ciclos-cultivo')) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="id_cultivo">Cultivo</label>
                <select id="id_cultivo" name="id_cultivo" required<?= $invalid('id_cultivo') ?>>
                    <option value="">Seleccionar cultivo</option>
                    <?php foreach (($cultivos ?? []) as $cultivo): ?>
                        <option value="<?= e((string) $cultivo['id_cultivo']) ?>"<?= (string) ($values['id_cultivo'] ?? '') === (string) $cultivo['id_cultivo'] ? ' selected' : '' ?>>
                            <?= e($cultivo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError('id_cultivo') ?>
            </div>
            <div class="field">
                <label for="id_parcela">Parcela</label>
                <select id="id_parcela" name="id_parcela" required<?= $invalid('id_parcela') ?>>
                    <option value="">Seleccionar parcela</option>
                    <?php foreach (($parcelas ?? []) as $parcela): ?>
                        <option value="<?= e((string) $parcela['id_parcela']) ?>"<?= (string) ($values['id_parcela'] ?? '') === (string) $parcela['id_parcela'] ? ' selected' : '' ?>>
                            <?= e($parcela['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError('id_parcela') ?>
            </div>
            <div class="field">
                <label for="fecha_inicio">Fecha de inicio</label>
                <input id="fecha_inicio" type="date" name="fecha_inicio" value="<?= e($values['fecha_inicio'] ?? date('Y-m-d')) ?>" required<?= $invalid('fecha_inicio') ?>>
                <?= $fieldError('fecha_inicio') ?>
            </div>
            <div class="field">
                <label for="cantidad_estimada">Producción estimada (opcional)</label>
                <input id="cantidad_estimada" type="number" step="0.001" min="0.001" name="cantidad_estimada" value="<?= e($values['cantidad_estimada'] ?? '') ?>"<?= $invalid('cantidad_estimada') ?>>
                <span class="hint">Se usa para calcular el costo provisional de los lotes al cosechar.</span>
                <?= $fieldError('cantidad_estimada') ?>
            </div>
            <div class="field">
                <label for="unidad_estimada">Unidad estimada</label>
                <select id="unidad_estimada" name="unidad_estimada">
                    <option value="">Sin definir</option>
                    <?php foreach (($units ?? []) as $unit): ?>
                        <option value="<?= e($unit['nombre']) ?>"<?= (string) ($values['unidad_estimada'] ?? '') === (string) $unit['nombre'] ? ' selected' : '' ?>><?= e($unit['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="observaciones">Observaciones</label>
                <input id="observaciones" type="text" name="observaciones" value="<?= e($values['observaciones'] ?? '') ?>">
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button>Crear ciclo</button>
            <a class="button ghost" href="<?= e(url('app/ciclos-cultivo')) ?>">Volver</a>
        </div>
    </form>
</div>
