<?php
use App\Infrastructure\Arca\ArcaCatalogs;

$isEdit = $mode === 'edit';
$action = $isEdit ? url('app/' . $type . '/' . (int) $item[$idColumn]) : url('app/' . $type);
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= $isEdit ? 'Editar' : 'Nuevo' ?> <?= e(mb_strtolower($singularLabel)) ?></h1>
        <p>Completá razón social o nombre. La validación final la realiza el servidor.</p>
    </div>
</div>

<?= empty($errors) ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e($action) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="razon_social">Razón social</label>
                <input id="razon_social" type="text" name="razon_social" value="<?= e($value('razon_social')) ?>"<?= isset($errors['razon_social']) ? ' aria-invalid="true"' : '' ?>>
                <span class="hint">Para empresas. Completá este campo o el nombre.</span>
                <?php if (isset($errors['razon_social'])): ?><span class="field-error"><?= e($errors['razon_social']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" value="<?= e($value('nombre')) ?>"<?= isset($errors['nombre']) ? ' aria-invalid="true"' : '' ?>>
                <span class="hint">Para personas. Completá este campo o la razón social.</span>
                <?php if (isset($errors['nombre'])): ?><span class="field-error"><?= e($errors['nombre']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="apellido">Apellido</label>
                <input id="apellido" type="text" name="apellido" value="<?= e($value('apellido')) ?>">
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
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= e($value('email')) ?>"<?= isset($errors['email']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="direccion">Dirección</label>
                <input id="direccion" type="text" name="direccion" value="<?= e($value('direccion')) ?>">
            </div>
        </div>
        <h2>Datos fiscales</h2>
        <div class="form-grid single">
            <div class="field">
                <label for="doc_tipo">Tipo de documento fiscal</label>
                <select id="doc_tipo" name="doc_tipo"<?= isset($errors['doc_tipo']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Sin definir</option>
                    <?php foreach (ArcaCatalogs::DOC_TIPOS as $code => $label): ?>
                        <option value="<?= e((string) $code) ?>"<?= $value('doc_tipo') === (string) $code ? ' selected' : '' ?>><?= e((string) $code) ?> — <?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['doc_tipo'])): ?><span class="field-error"><?= e($errors['doc_tipo']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="doc_nro">Número de documento fiscal</label>
                <input id="doc_nro" type="text" name="doc_nro" value="<?= e($value('doc_nro')) ?>"<?= isset($errors['doc_nro']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['doc_nro'])): ?><span class="field-error"><?= e($errors['doc_nro']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="condicion_iva_receptor_id">Condición frente al IVA</label>
                <select id="condicion_iva_receptor_id" name="condicion_iva_receptor_id"<?= isset($errors['condicion_iva_receptor_id']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Sin definir</option>
                    <?php foreach (ArcaCatalogs::CONDICIONES_IVA as $code => $condition): ?>
                        <option value="<?= e((string) $code) ?>"<?= $value('condicion_iva_receptor_id') === (string) $code ? ' selected' : '' ?>><?= e((string) $code) ?> — <?= e($condition['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['condicion_iva_receptor_id'])): ?><span class="field-error"><?= e($errors['condicion_iva_receptor_id']) ?></span><?php endif; ?>
                <span class="hint">Necesario para emitir comprobantes electrónicos.</span>
            </div>
        </div>
        <p class="hint">Debe indicar razón social o nombre.</p>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button><?= $isEdit ? 'Guardar cambios' : 'Crear ' . e(mb_strtolower($singularLabel)) ?></button>
            <a class="button ghost" href="<?= e(url('app/' . $type)) ?>">Volver</a>
        </div>
    </form>
</div>
