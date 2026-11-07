<?php
$id = (int) $item[$idColumn];
$display = trim((string) ($item['razon_social'] ?? '')) !== ''
    ? (string) $item['razon_social']
    : trim((string) ($item['nombre'] ?? '') . ' ' . (string) ($item['apellido'] ?? ''));
$docTipoId = $item['doc_tipo'] !== null ? (int) $item['doc_tipo'] : null;
$docNro = trim((string) ($item['doc_nro'] ?? ''));
$condicionId = $item['condicion_iva_receptor_id'] !== null ? (int) $item['condicion_iva_receptor_id'] : null;
$docTipoLabel = $docTipoId !== null ? (\App\Infrastructure\Arca\ArcaCatalogs::DOC_TIPOS[$docTipoId] ?? (string) $docTipoId) : 'Sin definir';
$condicionLabel = $condicionId !== null ? (\App\Infrastructure\Arca\ArcaCatalogs::CONDICIONES_IVA[$condicionId]['label'] ?? (string) $condicionId) : 'Sin definir';
$fiscalComplete = $docTipoId !== null && $docNro !== '' && $condicionId !== null;
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Administración</p>
        <h1><?= e($display !== '' ? $display : $singularLabel . ' #' . $id) ?></h1>
        <p>Detalle del registro.</p>
    </div>
    <div class="form-actions">
        <?php if (in_array($permission . '.editar', $permissions, true)): ?>
            <a class="button" href="<?= e(url('app/' . $type . '/' . $id . '/editar')) ?>">Editar</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/' . $type)) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Razón social</dt><dd><?= e($item['razon_social'] ?? '—') ?></dd></div>
    <div><dt>Nombre</dt><dd><?= e(trim((string) ($item['nombre'] ?? '') . ' ' . (string) ($item['apellido'] ?? ''))) ?: '—' ?></dd></div>
    <div><dt>Documento</dt><dd><?= e($item['documento'] ?? '—') ?></dd></div>
    <div><dt>Teléfono</dt><dd><?= e($item['telefono'] ?? '—') ?></dd></div>
    <div><dt>Email</dt><dd><?= e($item['email'] ?? '—') ?></dd></div>
    <div><dt>Dirección</dt><dd><?= e($item['direccion'] ?? '—') ?></dd></div>
    <div>
        <dt>Estado</dt>
        <dd>
            <span class="badge<?= (int) ($item['activo'] ?? 0) === 1 ? '' : ' badge-off' ?>">
                <?= (int) ($item['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?>
            </span>
        </dd>
    </div>
</dl>

<div class="panel">
    <h2>Datos fiscales</h2>
    <?php if (!$fiscalComplete): ?>
        <p class="flash flash-warning">Faltan datos fiscales. Completalos para poder emitir comprobantes electrónicos.</p>
    <?php endif; ?>
    <dl class="detail-list">
        <div><dt>Tipo de documento</dt><dd><?= e($docTipoLabel) ?></dd></div>
        <div><dt>Número de documento</dt><dd><?= e($docNro !== '' ? $docNro : 'Sin definir') ?></dd></div>
        <div><dt>Condición frente al IVA</dt><dd><?= e($condicionLabel) ?></dd></div>
    </dl>
</div>

<?php if ((int) ($item['activo'] ?? 0) === 1 && in_array($permission . '.editar', $permissions, true)): ?>
    <div class="panel">
        <form method="post" action="<?= e(url('app/' . $type . '/' . $id . '/desactivar')) ?>" data-confirm="¿Desactivar este registro? La operación no elimina información histórica.">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button class="danger">Desactivar <?= e(mb_strtolower($singularLabel)) ?></button>
        </form>
    </div>
<?php endif; ?>
