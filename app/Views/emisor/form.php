<?php
$value = static fn (string $field): string => (string) ($item[$field] ?? '');
$condicion = (int) ($item['condicion_iva'] ?? 0);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Datos del emisor</h1>
        <p>Datos fiscales que se imprimen en los comprobantes autorizados. El CUIT debe coincidir con el certificado de ARCA.</p>
    </div>
</div>

<?= error_modal($error ?? null) ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/emisor')) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="razon_social">Razón social</label>
                <input id="razon_social" type="text" name="razon_social" value="<?= e($value('razon_social')) ?>"<?= isset($errors['razon_social']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['razon_social'])): ?><span class="field-error"><?= e($errors['razon_social']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="cuit">CUIT</label>
                <input id="cuit" type="text" inputmode="numeric" name="cuit" value="<?= e($value('cuit')) ?>"<?= isset($errors['cuit']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['cuit'])): ?><span class="field-error"><?= e($errors['cuit']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="domicilio">Domicilio comercial</label>
                <input id="domicilio" type="text" name="domicilio" value="<?= e($value('domicilio')) ?>"<?= isset($errors['domicilio']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['domicilio'])): ?><span class="field-error"><?= e($errors['domicilio']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="condicion_iva">Condición frente al IVA</label>
                <select id="condicion_iva" name="condicion_iva"<?= isset($errors['condicion_iva']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Seleccionar</option>
                    <?php foreach ($condiciones as $id => $condicionItem): ?>
                        <option value="<?= e((string) $id) ?>"<?= $condicion === (int) $id ? ' selected' : '' ?>><?= e($condicionItem['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['condicion_iva'])): ?><span class="field-error"><?= e($errors['condicion_iva']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="ingresos_brutos">Ingresos brutos</label>
                <input id="ingresos_brutos" type="text" name="ingresos_brutos" value="<?= e($value('ingresos_brutos')) ?>"<?= isset($errors['ingresos_brutos']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['ingresos_brutos'])): ?><span class="field-error"><?= e($errors['ingresos_brutos']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="inicio_actividades">Inicio de actividades</label>
                <input id="inicio_actividades" type="date" name="inicio_actividades" value="<?= e($value('inicio_actividades')) ?>"<?= isset($errors['inicio_actividades']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['inicio_actividades'])): ?><span class="field-error"><?= e($errors['inicio_actividades']) ?></span><?php endif; ?>
            </div>
        </div>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button>Guardar cambios</button>
        </div>
    </form>
</div>
