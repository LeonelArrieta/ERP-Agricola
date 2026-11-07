<?php /** @var array $report */ ?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Diagnóstico ARCA</h1>
        <p>Ambiente activo: <strong><?= e($report['mode']) ?></strong>. No se muestran tokens, firmas ni claves.</p>
    </div>
    <a class="button ghost" href="<?= e(url('app/comprobantes')) ?>">Volver a comprobantes</a>
</div>

<?php if ($report['ready']): ?>
    <div class="flash flash-success">Listo para emitir en este ambiente.</div>
<?php else: ?>
    <div class="flash flash-error">Hay verificaciones con error. Resolvelas antes de emitir.</div>
<?php endif; ?>

<div class="panel">
    <div class="table-wrap">
        <table>
            <caption class="visually-hidden">Verificaciones del diagnóstico ARCA</caption>
            <thead>
            <tr>
                <th scope="col">Estado</th>
                <th scope="col">Verificación</th>
                <th scope="col">Detalle</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($report['checks'] as $check): ?>
                <?php
                $badge = match ($check['status']) {
                    'ok' => 'badge-ok',
                    'warn' => 'badge-warn',
                    default => 'badge-fail',
                };
                $label = match ($check['status']) {
                    'ok' => 'OK',
                    'warn' => 'Atención',
                    default => 'Error',
                };
                ?>
                <tr>
                    <td><span class="badge <?= e($badge) ?>"><?= e($label) ?></span></td>
                    <td><?= e($check['label']) ?></td>
                    <td><?= e($check['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
