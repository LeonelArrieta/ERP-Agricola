<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/' . $type . 's/' . (int) $item[$idColumn]) : url('app/' . $type . 's');
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
$surfaceUnitValue = $value('unidad_superficie');
$unitNames = array_map(static fn (array $unit): string => (string) $unit['nombre'], $units ?? []);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Producción</p>
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
                <label for="descripcion">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" value="<?= e($value('descripcion')) ?>">
            </div>
            <?php if ($type === 'parcela'): ?>
                <div class="field">
                    <label for="superficie">Superficie</label>
                    <input id="superficie" type="number" step="0.001" min="0.001" name="superficie" value="<?= e($value('superficie')) ?>"<?= isset($errors['superficie']) ? ' aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors['superficie'])): ?><span class="field-error"><?= e($errors['superficie']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="unidad_superficie">Unidad de superficie</label>
                    <select id="unidad_superficie" name="unidad_superficie"<?= isset($errors['unidad_superficie']) ? ' aria-invalid="true"' : '' ?>>
                        <option value="">Seleccionar unidad</option>
                        <?php foreach (($units ?? []) as $unit): ?>
                            <option value="<?= e($unit['nombre']) ?>"<?= $surfaceUnitValue === (string) $unit['nombre'] ? ' selected' : '' ?>><?= e($unit['nombre']) ?><?= trim((string) ($unit['descripcion'] ?? '')) !== '' ? ' — ' . e($unit['descripcion']) : '' ?></option>
                        <?php endforeach; ?>
                        <?php if ($surfaceUnitValue !== '' && !in_array($surfaceUnitValue, $unitNames, true)): ?>
                            <option value="<?= e($surfaceUnitValue) ?>" selected><?= e($surfaceUnitValue) ?> (fuera del maestro)</option>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($errors['unidad_superficie'])): ?><span class="field-error"><?= e($errors['unidad_superficie']) ?></span><?php endif; ?>
                    <span class="hint">Se administra en Administración → Unidades.</span>
                </div>
            <?php endif; ?>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear ' . e(mb_strtolower($singularLabel)) ?></button>
            <a class="button ghost" href="<?= e(url('app/' . $type . 's')) ?>">Volver</a>
        </div>
    </form>
</div>
