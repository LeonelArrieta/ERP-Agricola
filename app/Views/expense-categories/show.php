<?php
$id = (int) $item['id_categoria_gasto'];
$isActive = (int) ($item['activo'] ?? 0) === 1;
$canManage = in_array('gastos.crear', $permissions, true);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= e($item['nombre'] ?? 'Categoría #' . $id) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <span class="badge<?= $isActive ? '' : ' badge-off' ?>"><?= $isActive ? 'Activa' : 'Inactiva' ?></span>
        <?php if ($canManage): ?>
            <a class="button" href="<?= e(url('app/categorias-gastos/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/categorias-gastos')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Nombre</dt><dd><?= e($item['nombre'] ?? '—') ?></dd></div>
    <div><dt>Descripción</dt><dd><?= e($item['descripcion'] ?? '—') ?></dd></div>
</dl>

<?php if ($canManage): ?>
    <div class="panel">
        <?php if ($isActive): ?>
            <form method="post" action="<?= e(url('app/categorias-gastos/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar esta categoría? No estará disponible para nuevos gastos, pero los gastos históricos no se modifican.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Desactivar categoría</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('app/categorias-gastos/' . $id . '/activar')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Activar categoría</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
