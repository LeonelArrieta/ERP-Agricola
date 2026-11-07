<?php
$v = $voucher;
$receptorNombre = '';
if (is_array($client)) {
    $receptorNombre = trim((string) ($client['razon_social'] ?? '')) !== ''
        ? (string) $client['razon_social']
        : trim((string) ($client['nombre'] ?? '') . ' ' . (string) ($client['apellido'] ?? ''));
}
if ($receptorNombre === '') {
    $receptorNombre = (string) ($v['cliente'] ?? '—');
}
$condicionReceptor = $condiciones[(int) $v['condicion_iva_receptor_id']]['label'] ?? (string) $v['condicion_iva_receptor_id'];
$docTipoLabel = $docTipos[(int) $v['doc_tipo']] ?? (string) $v['doc_tipo'];
$discriminaIva = (int) ($v['discrimina_iva'] ?? 1) === 1;
$concepto = [1 => 'Productos', 2 => 'Servicios', 3 => 'Productos y servicios'][(int) $v['concepto']] ?? (string) $v['concepto'];
$qrInline = $qrSvg !== null ? preg_replace('/<svg /', '<svg width="150" height="150" ', $qrSvg, 1) : null;
?>
<article class="invoice">
    <table class="invoice-head">
        <tr>
            <td class="invoice-emisor">
                <?php if ($emisor !== null): ?>
                    <p class="invoice-emisor-name"><?= e($emisor['razon_social']) ?></p>
                    <p><?= e($emisor['domicilio']) ?></p>
                    <p>CUIT: <?= e($emisor['cuit']) ?></p>
                    <p><?= e($condiciones[$emisor['condicion_iva']]['label'] ?? '') ?></p>
                    <?php if (($emisor['ingresos_brutos'] ?? null) !== null): ?><p>Ingresos brutos: <?= e($emisor['ingresos_brutos']) ?></p><?php endif; ?>
                    <?php if (($emisor['inicio_actividades'] ?? null) !== null): ?><p>Inicio de actividades: <?= e(fecha($emisor['inicio_actividades'])) ?></p><?php endif; ?>
                <?php else: ?>
                    <p class="invoice-emisor-name">Datos del emisor no disponibles</p>
                    <p>Comprobante histórico incompleto.</p>
                <?php endif; ?>
            </td>
            <td class="invoice-tipo">
                <p class="invoice-letter"><?= e((string) ($v['clase'] ?? '')) ?></p>
                <p class="invoice-tipo-name"><?= e((string) ($v['tipo_nombre'] ?? '')) ?></p>
                <p>N.º <?= e(str_pad((string) ((int) ($v['numero'] ?? 0)), 8, '0', STR_PAD_LEFT)) ?></p>
                <p>Fecha: <?= e(fecha($v['fecha'] ?? null)) ?></p>
                <p>Punto de venta: <?= e((string) $v['punto_venta_numero']) ?></p>
            </td>
        </tr>
    </table>

    <?php if ($incomplete): ?>
        <p class="invoice-incomplete">Documento histórico incompleto: el comprobante no registró los datos del emisor ni el código QR en el momento de la emisión. No se muestran datos actuales del emisor.</p>
    <?php endif; ?>

    <section class="invoice-receptor">
        <h2>Receptor</h2>
        <p><strong><?= e($receptorNombre) ?></strong></p>
        <p><?= e($docTipoLabel) ?>: <?= e((string) $v['doc_nro']) ?></p>
        <p>Condición frente al IVA: <?= e($condicionReceptor) ?></p>
        <?php if (is_array($client) && trim((string) ($client['direccion'] ?? '')) !== ''): ?>
            <p>Domicilio: <?= e((string) $client['direccion']) ?></p>
        <?php endif; ?>
        <p>Concepto: <?= e($concepto) ?></p>
    </section>

    <table class="invoice-items">
        <thead>
        <tr>
            <th scope="col">Descripción</th>
            <th scope="col" class="num">Cantidad</th>
            <th scope="col" class="num">P. unitario</th>
            <th scope="col" class="num">Subtotal</th>
            <?php if ($discriminaIva): ?><th scope="col" class="num">IVA</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $detail): ?>
            <tr>
                <td><?= e((string) ($detail['producto'] ?? '')) ?></td>
                <td class="num"><?= e(qty($detail['cantidad'] ?? 0)) ?> <?= e((string) ($detail['unidad_medida'] ?? '')) ?></td>
                <td class="num"><?= e(ars($detail['precio_neto_final'] ?? $detail['precio_bruto'] ?? 0)) ?></td>
                <td class="num"><?= e(ars($detail['subtotal'] ?? 0)) ?></td>
                <?php if ($discriminaIva): ?><td class="num"><?= e(ars($detail['importe_alicuota'] ?? 0)) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="invoice-totals">
        <?php if ($discriminaIva): ?>
            <tr><td>Importe neto</td><td class="num"><?= e(ars($v['imp_neto'])) ?></td></tr>
            <?php foreach ($iva as $row): ?>
                <tr><td>IVA <?= e($alicuotas[(int) $row['alic_iva_id']] ?? (string) $row['alic_iva_id']) ?>%</td><td class="num"><?= e(ars($row['importe'])) ?></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr class="invoice-total"><td>Importe total</td><td class="num"><?= e(ars($v['imp_total'])) ?></td></tr>
    </table>

    <table class="invoice-foot">
        <tr>
            <td class="invoice-cae">
                <p>CAE: <?= e((string) ($v['cae'] ?? '—')) ?></p>
                <p>Vencimiento CAE: <?= e(fecha($v['cae_fch_vto'] ?? null)) ?></p>
                <p>Comprobante autorizado por ARCA (RG 4291/2018).</p>
            </td>
            <td class="invoice-qr-cell">
                <?php if ($qrSvg !== null): ?>
                    <?php if ($qrAsImage): ?>
                        <img src="data:image/svg+xml;base64,<?= base64_encode($qrSvg) ?>" alt="Código QR del comprobante" width="150" height="150">
                    <?php else: ?>
                        <div class="invoice-qr-svg" role="img" aria-label="Código QR del comprobante"><?= $qrInline ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</article>
