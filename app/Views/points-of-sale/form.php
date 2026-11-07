<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/puntos-venta/' . (int) $item['id_punto_venta']) : url('app/puntos-venta');
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1><?= $isEdit ? 'Editar punto de venta' : 'Nuevo punto de venta' ?></h1>
        <p>El número debe coincidir con el punto de venta habilitado en ARCA.</p>
    </div>
</div>

<?= error_modal($error ?? null) ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e($action) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="numero">Número</label>
                <input id="numero" type="number" step="1" min="1" max="99999" name="numero" value="<?= e($value('numero')) ?>"<?= isset($errors['numero']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['numero'])): ?><span class="field-error"><?= e($errors['numero']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="descripcion">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" value="<?= e($value('descripcion')) ?>">
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear punto de venta' ?></button>
            <a class="button ghost" href="<?= e(url('app/puntos-venta')) ?>">Volver</a>
        </div>
    </form>
</div>
