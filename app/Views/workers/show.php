<?php
$id = (int) $item['id_trabajador'];
$isActive = (int) ($item['activo'] ?? 0) === 1;
$canManage = in_array('produccion.crear', $permissions, true);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= e(trim(($item['apellido'] ?? '') . ', ' . ($item['nombre'] ?? ''), ', ')) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <span class="badge<?= $isActive ? '' : ' badge-off' ?>"><?= $isActive ? 'Activo' : 'Inactivo' ?></span>
        <?php if ($canManage): ?>
            <a class="button" href="<?= e(url('app/trabajadores/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/trabajadores')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Nombre</dt><dd><?= e($item['nombre'] ?? '—') ?></dd></div>
    <div><dt>Apellido</dt><dd><?= e($item['apellido'] ?? '—') ?></dd></div>
    <div><dt>Documento</dt><dd><?= e($item['documento'] ?? '—') ?></dd></div>
    <div><dt>Teléfono</dt><dd><?= e($item['telefono'] ?? '—') ?></dd></div>
    <div><dt>Costo por hora actual</dt><dd><?= e(ars($item['costo_hora_actual'] ?? 0)) ?></dd></div>
</dl>

<?php if ($canManage): ?>
    <div class="panel">
        <?php if ($isActive): ?>
            <form method="post" action="<?= e(url('app/trabajadores/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar este trabajador? No estará disponible para nuevos trabajos, pero los registros históricos no se modifican.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Desactivar trabajador</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('app/trabajadores/' . $id . '/activar')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Activar trabajador</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
