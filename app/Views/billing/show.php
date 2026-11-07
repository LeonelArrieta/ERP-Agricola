<?php
$id = (int) $item['id_comprobante_fiscal'];
$state = (string) $item['estado_fiscal'];
$authorized = $state === 'Autorizado';
$canEmit = in_array('facturacion.emitir', $permissions, true);
$canNote = in_array('facturacion.notas', $permissions, true);
$errors = $item['errores'] !== null ? json_decode((string) $item['errores'], true) : [];
$observations = $item['observaciones'] !== null ? json_decode((string) $item['observaciones'], true) : [];
$concepto = \App\Infrastructure\Arca\ArcaCatalogs::CONCEPTOS[(int) $item['concepto']] ?? (string) $item['concepto'];
$docTipo = \App\Infrastructure\Arca\ArcaCatalogs::DOC_TIPOS[(int) $item['doc_tipo']] ?? (string) $item['doc_tipo'];
$condicionIva = \App\Infrastructure\Arca\ArcaCatalogs::CONDICIONES_IVA[(int) $item['condicion_iva_receptor_id']]['label'] ?? (string) $item['condicion_iva_receptor_id'];
$resultadoArca = ['A' => 'Aprobado', 'R' => 'Rechazado'][(string) ($item['resultado_arca'] ?? '')] ?? ($item['resultado_arca'] ?? '—');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1><?= e($item['tipo_nombre'] ?? 'Comprobante') ?> PV <?= e((string) $item['punto_venta_numero']) ?> — <?= e((string) ($item['numero'] ?? 'sin número')) ?></h1>
        <p>Venta #<?= e((string) $item['id_venta']) ?> — <?= e($item['cliente'] ?? '') ?></p>
    </div>
    <div class="form-actions">
        <?= estado_badge($state) ?>
        <?php if ($authorized && $canNote): ?>
            <a class="button" href="<?= e(url('app/comprobantes/' . $id . '/notas/nueva')) ?>">Emitir nota</a>
        <?php endif; ?>
        <?php if ($authorized): ?>
            <a class="button" href="<?= e(url('app/comprobantes/' . $id . '/imprimir')) ?>">Ver / imprimir</a>
            <a class="button ghost" href="<?= e(url('app/comprobantes/' . $id . '/pdf?descargar=1')) ?>">Descargar PDF</a>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/ventas/' . (int) $item['id_venta'])) ?>">Ver venta</a>
        <a class="button ghost" href="<?= e(url('app/comprobantes')) ?>">Volver</a>
    </div>
</div>

<?php if ($state === 'ErrorComunicacion' && $canEmit): ?>
    <div class="flash flash-warning">
        El comprobante no llegó a enviarse a ARCA. Puede reintentar la emisión sin duplicar comprobantes.
    </div>
    <div class="panel">
        <form method="post" action="<?= e(url('app/comprobantes/' . $id . '/reintentar')) ?>" data-confirm="¿Reintentar la emisión de este comprobante?">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button>Reintentar emisión</button>
        </form>
    </div>
<?php elseif (in_array($state, ['ResultadoIncierto', 'Enviando'], true) && $canEmit): ?>
    <div class="flash flash-warning">
        El comprobante no tiene respuesta definitiva de ARCA. No se debe emitir otro comprobante: primero reconciliá la situación.
    </div>
    <div class="panel">
        <form method="post" action="<?= e(url('app/comprobantes/' . $id . '/reconciliar')) ?>" data-confirm="¿Consultar en ARCA si el comprobante fue autorizado?">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button>Reconciliar con ARCA</button>
        </form>
    </div>
<?php endif; ?>

<dl class="detail-list">
    <div><dt>Tipo</dt><dd><?= e($item['tipo_nombre'] ?? '—') ?> (código <?= e((string) $item['id_tipo_comprobante']) ?>, clase <?= e($item['clase'] ?? '') ?>)</dd></div>
    <div><dt>Punto de venta</dt><dd>PV <?= e((string) $item['punto_venta_numero']) ?></dd></div>
    <div><dt>Número</dt><dd><?= e((string) ($item['numero'] ?? '—')) ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e(fecha($item['fecha'] ?? null)) ?></dd></div>
    <div><dt>Concepto</dt><dd><?= e($concepto) ?></dd></div>
    <div><dt>Documento receptor</dt><dd><?= e($docTipo) ?> — <?= e($item['doc_nro'] ?? '') ?></dd></div>
    <div><dt>Condición IVA receptor</dt><dd><?= e($condicionIva) ?></dd></div>
    <div><dt>Importe total</dt><dd><?= e(ars($item['imp_total'])) ?></dd></div>
    <div><dt>Importe neto</dt><dd><?= e(ars($item['imp_neto'])) ?></dd></div>
    <div><dt>Importe IVA</dt><dd><?= e(ars($item['imp_iva'])) ?></dd></div>
    <div><dt>No gravado / Exento / Tributos</dt><dd><?= e(ars($item['imp_tot_conc'])) ?> / <?= e(ars($item['imp_op_ex'])) ?> / <?= e(ars($item['imp_trib'])) ?></dd></div>
    <div><dt>Moneda</dt><dd><?= e($item['moneda'] ?? 'PES') ?> (cotización <?= e((string) $item['cotizacion']) ?>)</dd></div>
    <div><dt>CAE</dt><dd><?= e($item['cae'] ?? '—') ?></dd></div>
    <div><dt>Vencimiento CAE</dt><dd><?= e(fecha($item['cae_fch_vto'] ?? null)) ?></dd></div>
    <div><dt>Resultado ARCA</dt><dd><?= e($resultadoArca) ?></dd></div>
</dl>

<?php if (($iva ?? []) !== []): ?>
    <div class="panel">
        <h2>IVA discriminado</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Alícuota</th><th scope="col" class="num">Base imponible</th><th scope="col" class="num">Importe</th></tr></thead>
                <tbody>
                <?php foreach ($iva as $row): ?>
                    <tr>
                        <td><?= e((\App\Infrastructure\Arca\ArcaCatalogs::ALICUOTA_POR_ID[(int) $row['alic_iva_id']] ?? (string) $row['alic_iva_id'])) ?> %</td>
                        <td><?= e(ars($row['base_imp'])) ?></td>
                        <td><?= e(ars($row['importe'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (($asociado ?? null) !== null): ?>
    <div class="panel">
        <h2>Comprobante asociado</h2>
        <dl class="detail-list">
            <div><dt>Tipo</dt><dd><?= e($asociado['tipo_nombre'] ?? (string) $asociado['id_tipo_comprobante']) ?></dd></div>
            <div><dt>Punto / número</dt><dd>PV <?= e((string) $asociado['punto_venta_numero']) ?> — <?= e((string) $asociado['numero']) ?></dd></div>
            <div><dt>Fecha</dt><dd><?= e(fecha($asociado['fecha'] ?? null)) ?></dd></div>
            <div><dt>CUIT</dt><dd><?= e($item['asoc_cuit'] ?? '—') ?></dd></div>
        </dl>
    </div>
<?php endif; ?>

<?php if ($errors !== [] || $observations !== []): ?>
    <div class="panel">
        <h2>Errores y observaciones</h2>
        <?php if ($errors !== []): ?>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><strong><?= e((string) ($error['code'] ?? '')) ?></strong>: <?= e((string) ($error['msg'] ?? '')) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($observations !== []): ?>
            <ul>
                <?php foreach ($observations as $observation): ?>
                    <li><strong><?= e((string) ($observation['code'] ?? '')) ?></strong>: <?= e((string) ($observation['msg'] ?? '')) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (($attempts ?? []) !== []): ?>
    <div class="panel">
        <h2>Intentos de autorización</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">#</th><th scope="col">Operación</th><th scope="col">Estado</th><th scope="col">Resultado</th><th scope="col" class="num">Duración</th><th scope="col">Inicio</th></tr></thead>
                <tbody>
                <?php foreach ($attempts as $attempt): ?>
                    <tr>
                        <td><?= e((string) $attempt['numero_intento']) ?></td>
                        <td><?= e((string) $attempt['operacion']) ?></td>
                        <td><?= e((string) $attempt['estado']) ?></td>
                        <td><?= e((string) ($attempt['codigo_resultado'] ?? '—')) ?></td>
                        <td class="num"><?= e((string) ($attempt['duracion_ms'] ?? '—')) ?> ms</td>
                        <td><?= e(fecha_hora($attempt['inicio'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
