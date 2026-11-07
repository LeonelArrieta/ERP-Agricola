<?php
$id = (int) $item['id_punto_venta'];
$isActive = (int) ($item['activo'] ?? 0) === 1;
$canManage = in_array('facturacion.emitir', $permissions, true);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Punto de venta <?= e((string) $item['numero']) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <span class="badge<?= $isActive ? '' : ' badge-off' ?>"><?= $isActive ? 'Activo' : 'Inactivo' ?></span>
        <?php if ($canManage): ?>
            <a class="button" href="<?= e(url('app/puntos-venta/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/puntos-venta')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Número</dt><dd><?= e((string) $item['numero']) ?></dd></div>
    <div><dt>Descripción</dt><dd><?= e($item['descripcion'] ?? '—') ?></dd></div>
</dl>

<?php if ($canManage): ?>
    <div class="panel">
        <?php if ($isActive): ?>
            <form method="post" action="<?= e(url('app/puntos-venta/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar este punto de venta? No estará disponible para nuevas emisiones, pero los comprobantes históricos no se modifican.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Desactivar punto de venta</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('app/puntos-venta/' . $id . '/activar')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Activar punto de venta</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
