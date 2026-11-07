<?php
$id = (int) $item[$idColumn];
$isActive = (int) ($item['activo'] ?? 0) === 1;
$canManage = in_array('produccion.crear', $permissions, true);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Producción</p>
        <h1><?= e($item['nombre'] ?? $singularLabel . ' #' . $id) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <span class="badge<?= $isActive ? '' : ' badge-off' ?>"><?= $isActive ? 'Activo' : 'Inactivo' ?></span>
        <?php if ($canManage): ?>
            <a class="button" href="<?= e(url('app/' . $type . 's/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/' . $type . 's')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Nombre</dt><dd><?= e($item['nombre'] ?? '—') ?></dd></div>
    <div><dt>Descripción</dt><dd><?= e($item['descripcion'] ?? '—') ?></dd></div>
    <?php if ($type === 'parcela'): ?>
        <div><dt>Superficie</dt><dd><?= e(qty($item['superficie'] ?? 0)) ?> <?= e($item['unidad_superficie'] ?? '') ?></dd></div>
        <div><dt>Ciclo en curso</dt><dd><?= ($hasActiveCycle ?? false) ? 'Sí' : 'No' ?></dd></div>
    <?php endif; ?>
</dl>

<?php if ($type === 'parcela' && ($hasActiveCycle ?? false)): ?>
    <div class="flash flash-warning">Esta parcela tiene un ciclo EnCurso y no puede desactivarse hasta que el ciclo finalice o se cancele.</div>
<?php endif; ?>

<?php if ($canManage): ?>
    <div class="panel">
        <?php if ($isActive && !($type === 'parcela' && ($hasActiveCycle ?? false))): ?>
            <form method="post" action="<?= e(url('app/' . $type . 's/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar este registro? No se elimina información histórica.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Desactivar <?= e(mb_strtolower($singularLabel)) ?></button>
            </form>
        <?php elseif (!$isActive): ?>
            <form method="post" action="<?= e(url('app/' . $type . 's/' . $id . '/activar')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Activar <?= e(mb_strtolower($singularLabel)) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
