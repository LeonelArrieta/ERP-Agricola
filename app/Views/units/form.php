<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/unidades/' . (int) $item['id_unidad']) : url('app/unidades');
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= $isEdit ? 'Editar unidad' : 'Nueva unidad' ?></h1>
        <p>La validación final la realiza el servidor.</p>
    </div>
</div>

<?= error_modal($error ?? null) ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e($action) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" value="<?= e($value('nombre')) ?>" placeholder="kg, litros, unidades"<?= isset($errors['nombre']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['nombre'])): ?><span class="field-error"><?= e($errors['nombre']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="descripcion">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" value="<?= e($value('descripcion')) ?>" placeholder="Kilogramo">
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear unidad' ?></button>
            <a class="button ghost" href="<?= e(url('app/unidades')) ?>">Volver</a>
        </div>
    </form>
</div>
