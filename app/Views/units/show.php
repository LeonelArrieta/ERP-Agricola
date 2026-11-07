<?php
$id = (int) $item['id_unidad'];
$isActive = (int) ($item['activo'] ?? 0) === 1;
$canManage = in_array('unidades.editar', $permissions, true);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= e($item['nombre'] ?? 'Unidad #' . $id) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <span class="badge<?= $isActive ? '' : ' badge-off' ?>"><?= $isActive ? 'Activa' : 'Inactiva' ?></span>
        <?php if ($canManage): ?>
            <a class="button" href="<?= e(url('app/unidades/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/unidades')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Nombre</dt><dd><?= e($item['nombre'] ?? '—') ?></dd></div>
    <div><dt>Descripción</dt><dd><?= e($item['descripcion'] ?? '—') ?></dd></div>
</dl>

<?php if ($canManage): ?>
    <div class="panel">
        <?php if ($isActive): ?>
            <form method="post" action="<?= e(url('app/unidades/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar esta unidad? No estará disponible en los formularios, pero los registros históricos no se modifican.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Desactivar unidad</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('app/unidades/' . $id . '/activar')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Activar unidad</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
