<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/categorias-gastos/' . (int) $item['id_categoria_gasto']) : url('app/categorias-gastos');
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= $isEdit ? 'Editar categoría de gasto' : 'Nueva categoría de gasto' ?></h1>
        <p>La validación final la realiza el servidor.</p>
    </div>
</div>

<?= error_modal($error ?? null) ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e($action) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" value="<?= e($value('nombre')) ?>" placeholder="Servicios, transporte, mantenimiento"<?= isset($errors['nombre']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['nombre'])): ?><span class="field-error"><?= e($errors['nombre']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="descripcion">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" value="<?= e($value('descripcion')) ?>">
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear categoría' ?></button>
            <a class="button ghost" href="<?= e(url('app/categorias-gastos')) ?>">Volver</a>
        </div>
    </form>
</div>
