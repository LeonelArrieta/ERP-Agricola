<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/' . $type . 's/' . (int) $item[$idColumn]) : url('app/' . $type . 's');
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
$selectedRate = (string) ($item['porcentaje_alicuota'] ?? '');
$unitValue = $value('unidad_medida');
$unitNames = array_map(static fn (array $unit): string => (string) $unit['nombre'], $units ?? []);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Inventario</p>
        <h1><?= $isEdit ? 'Editar' : 'Nuevo' ?> <?= e(mb_strtolower($singularLabel)) ?></h1>
        <p>La validación final la realiza el servidor.</p>
    </div>
</div>

<?= error_modal($error ?? null) ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e($action) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" value="<?= e($value('nombre')) ?>"<?= isset($errors['nombre']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['nombre'])): ?><span class="field-error"><?= e($errors['nombre']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="unidad_medida">Unidad de medida</label>
                <select id="unidad_medida" name="unidad_medida"<?= isset($errors['unidad_medida']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Seleccionar unidad</option>
                    <?php foreach (($units ?? []) as $unit): ?>
                        <option value="<?= e($unit['nombre']) ?>"<?= $unitValue === (string) $unit['nombre'] ? ' selected' : '' ?>><?= e($unit['nombre']) ?><?= trim((string) ($unit['descripcion'] ?? '')) !== '' ? ' — ' . e($unit['descripcion']) : '' ?></option>
                    <?php endforeach; ?>
                    <?php if ($unitValue !== '' && !in_array($unitValue, $unitNames, true)): ?>
                        <option value="<?= e($unitValue) ?>" selected><?= e($unitValue) ?> (fuera del maestro)</option>
                    <?php endif; ?>
                </select>
                <?php if (isset($errors['unidad_medida'])): ?><span class="field-error"><?= e($errors['unidad_medida']) ?></span><?php endif; ?>
                <span class="hint">Se administra en Administración → Unidades.</span>
            </div>
            <?php if ($type === 'producto'): ?>
                <div class="field">
                    <label for="porcentaje_alicuota">Alícuota</label>
                    <select id="porcentaje_alicuota" name="porcentaje_alicuota"<?= isset($errors['porcentaje_alicuota']) ? ' aria-invalid="true"' : '' ?>>
                        <?php foreach (['10.50', '21.00', '27.00'] as $rate): ?>
                            <option value="<?= e($rate) ?>"<?= $selectedRate !== '' && (float) $selectedRate === (float) $rate ? ' selected' : '' ?>><?= e(number_format((float) $rate, 2, ',', '.')) ?> %</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['porcentaje_alicuota'])): ?><span class="field-error"><?= e($errors['porcentaje_alicuota']) ?></span><?php endif; ?>
                    <span class="hint">Catálogo cerrado: 10,50 %, 21,00 % o 27,00 %.</span>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="descripcion">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" value="<?= e($value('descripcion')) ?>">
            </div>
        </div>
        <?php if ($type === 'insumo'): ?>
            <p class="hint">El stock y el costo PPP no se editan aquí: se modifican mediante compras, consumos y mermas.</p>
        <?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear ' . e(mb_strtolower($singularLabel)) ?></button>
            <a class="button ghost" href="<?= e(url('app/' . $type . 's')) ?>">Volver</a>
        </div>
    </form>
</div>
