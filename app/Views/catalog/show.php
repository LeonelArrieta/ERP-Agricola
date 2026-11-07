<?php
$id = (int) $item[$idColumn];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Inventario</p>
        <h1><?= e($item['nombre'] ?? $singularLabel . ' #' . $id) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <?php if (in_array($permission . '.editar', $permissions, true)): ?>
            <a class="button" href="<?= e(url('app/' . $type . 's/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/' . $type . 's')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Nombre</dt><dd><?= e($item['nombre'] ?? '—') ?></dd></div>
    <div><dt>Unidad de medida</dt><dd><?= e($item['unidad_medida'] ?? '—') ?></dd></div>
    <?php if ($type === 'producto'): ?>
        <div><dt>Alícuota</dt><dd><?= e(number_format((float) ($item['porcentaje_alicuota'] ?? 0), 2, ',', '.')) ?> %</dd></div>
    <?php else: ?>
        <div><dt>Stock actual</dt><dd><?= e(qty($item['stock_actual'] ?? 0)) ?></dd></div>
        <div><dt>Costo PPP</dt><dd><?= e(ars($item['costo_unitario_actual'] ?? 0)) ?></dd></div>
    <?php endif; ?>
    <div><dt>Descripción</dt><dd><?= e($item['descripcion'] ?? '—') ?></dd></div>
    <div>
        <dt>Estado</dt>
        <dd>
            <span class="badge<?= (int) ($item['activo'] ?? 0) === 1 ? '' : ' badge-off' ?>">
                <?= (int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?>
            </span>
        </dd>
    </div>
</dl>

<?php if ($type === 'insumo'): ?>
    <div class="flash flash-info">El stock y el PPP son valores derivados: se actualizan mediante compras, consumos y mermas, no desde este formulario.</div>
<?php endif; ?>

<?php if ((int) ($item['activo'] ?? 0) === 1 && in_array($permission . '.editar', $permissions, true)): ?>
    <div class="panel">
        <form method="post" action="<?= e(url('app/' . $type . 's/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar este registro? La operación no elimina información histórica.">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button class="danger">Desactivar <?= e(mb_strtolower($singularLabel)) ?></button>
        </form>
    </div>
<?php endif; ?>
