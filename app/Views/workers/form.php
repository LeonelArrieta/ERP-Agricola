<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/trabajadores/' . (int) $item['id_trabajador']) : url('app/trabajadores');
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= $isEdit ? 'Editar trabajador' : 'Nuevo trabajador' ?></h1>
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
                <label for="apellido">Apellido</label>
                <input id="apellido" type="text" name="apellido" value="<?= e($value('apellido')) ?>"<?= isset($errors['apellido']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['apellido'])): ?><span class="field-error"><?= e($errors['apellido']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="documento">Documento</label>
                <input id="documento" type="text" name="documento" value="<?= e($value('documento')) ?>">
            </div>
            <div class="field">
                <label for="telefono">Teléfono</label>
                <input id="telefono" type="text" name="telefono" value="<?= e($value('telefono')) ?>">
            </div>
            <div class="field">
                <label for="costo_hora_actual">Costo por hora</label>
                <input id="costo_hora_actual" type="number" step="0.01" min="0" name="costo_hora_actual" value="<?= e($value('costo_hora_actual')) ?>"<?= isset($errors['costo_hora_actual']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['costo_hora_actual'])): ?><span class="field-error"><?= e($errors['costo_hora_actual']) ?></span><?php endif; ?>
                <span class="hint">El costo histórico de cada trabajo queda congelado al registrarse.</span>
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear trabajador' ?></button>
            <a class="button ghost" href="<?= e(url('app/trabajadores')) ?>">Volver</a>
        </div>
    </form>
</div>
