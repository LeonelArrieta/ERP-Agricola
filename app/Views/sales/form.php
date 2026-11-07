<?php
$lines = is_array($values['detalles'] ?? null) ? array_values($values['detalles']) : [];
if ($lines === []) {
    $lines = [[]];
}
$errors = $errors ?? [];
$fieldError = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field-error">' . e($errors[$field]) . '</span>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
$priceInvalid = isset($errors['precio_bruto']) ? ' aria-invalid="true"' : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Comercial</p>
        <h1>Nueva venta</h1>
        <p>La venta contado ingresa a Caja; la venta crédito genera CxC. El costo del lote queda congelado en el detalle.</p>
    </div>
</div>

<?= empty($errors) ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form']) || !empty($errors['precio_bruto'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form'] ?? $errors['precio_bruto']) ?></div>
<?php endif; ?>

<?php if (($lots ?? []) === []): ?>
    <div class="flash flash-warning">No hay lotes con disponibilidad. Registrá y consolidá producción antes de vender.</div>
<?php endif; ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/ventas')) ?>">
        <div class="form-section">
            <h2>Datos generales</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="id_cliente">Cliente</label>
                    <select id="id_cliente" name="id_cliente" required<?= $invalid('id_cliente') ?>>
                        <option value="">Seleccionar cliente</option>
                        <?php foreach (($clients ?? []) as $client): ?>
                            <option value="<?= e((string) $client['id_cliente']) ?>"<?= (string) ($values['id_cliente'] ?? '') === (string) $client['id_cliente'] ? ' selected' : '' ?>>
                                <?= e($client['razon_social'] ?? trim(($client['nombre'] ?? '') . ' ' . ($client['apellido'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('id_cliente') ?>
                </div>
                <div class="field">
                    <label for="fecha">Fecha</label>
                    <input id="fecha" type="date" name="fecha" value="<?= e($values['fecha'] ?? date('Y-m-d')) ?>" required<?= $invalid('fecha') ?>>
                    <?= $fieldError('fecha') ?>
                </div>
                <div class="field">
                    <label for="tipo_pago">Tipo de pago</label>
                    <select id="tipo_pago" name="tipo_pago" required<?= $invalid('tipo_pago') ?>>
                        <option value="Contado"<?= ($values['tipo_pago'] ?? '') === 'Contado' ? ' selected' : '' ?>>Contado</option>
                        <option value="Credito"<?= ($values['tipo_pago'] ?? 'Credito') === 'Credito' ? ' selected' : '' ?>>Crédito</option>
                    </select>
                    <?= $fieldError('tipo_pago') ?>
                </div>
                <div class="field">
                    <label for="observaciones">Observaciones</label>
                    <input id="observaciones" type="text" name="observaciones" value="<?= e($values['observaciones'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Detalle de la venta</h2>
            <p class="hint">Elegí el lote, la cantidad y el margen. El precio sugerido se puede editar por línea.</p>
            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Líneas de la venta</caption>
                    <thead>
                    <tr>
                        <th scope="col">Lote (producto — disponible)</th>
                        <th scope="col" class="num">Cantidad</th>
                        <th scope="col" class="num">Margen (%)</th>
                        <th scope="col" class="num">Precio bruto (sin IVA)</th>
                        <th scope="col" class="num">Importe</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                    </thead>
                    <tbody id="sale-lines">
                    <?php $position = 0; ?>
                    <?php foreach ($lines as $index => $line): ?>
                        <?php
                        $position++;
                        $line = is_array($line) ? $line : [];
                        ?>
                        <tr data-line>
                            <td>
                                <select name="detalles[<?= e((string) $index) ?>][producto_lote]" data-lot-select aria-label="Producto y lote" required>
                                    <option value="">Seleccionar lote</option>
                                    <?php foreach (($lots ?? []) as $lot): ?>
                                        <?php
                                        $lotId = (int) $lot['id_lote_produccion'];
                                        $productId = (int) $lot['id_producto'];
                                        $reference = $productId . ':' . $lotId;
                                        ?>
                                        <option value="<?= e($reference) ?>" data-costo="<?= e((string) $lot['costo_unitario']) ?>" data-alicuota="<?= e((string) $lot['porcentaje_alicuota']) ?>" data-unidad="<?= e((string) $lot['unidad_medida']) ?>" data-provisional="<?= (int) $lot['es_costo_provisional'] ?>"<?= (string) ($line['producto_lote'] ?? '') === $reference ? ' selected' : '' ?>>
                                            <?= e($lot['producto']) ?> (<?= e($lot['unidad_medida']) ?>) — lote #<?= e((string) $lotId) ?> (disp. <?= e(qty($lot['cantidad_disponible'])) ?> <?= e($lot['unidad_medida']) ?><?= (int) $lot['es_costo_provisional'] === 1 ? ', costo provisional' : '' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.001" min="0.001" name="detalles[<?= e((string) $index) ?>][cantidad]" value="<?= e($line['cantidad'] ?? '') ?>" data-quantity aria-label="Cantidad" required> <span class="hint" data-unit>—</span></td>
                            <td><input type="number" step="0.01" min="0" max="99.99" name="detalles[<?= e((string) $index) ?>][margen_porcentaje]" value="<?= e($line['margen_porcentaje'] ?? '30') ?>" data-margin aria-label="Margen (%)" required></td>
                            <td>
                                <input type="number" step="0.01" min="0" name="detalles[<?= e((string) $index) ?>][precio_bruto]" value="<?= e($line['precio_bruto'] ?? '') ?>" data-price aria-label="Precio bruto sin IVA"<?= $priceInvalid ?>>
                                <span class="hint">Final unitario: <span data-final-price>—</span></span>
                            </td>
                            <td class="num">
                                <span class="hint">Sin IVA: <span data-line-subtotal>—</span></span>
                                <span class="hint">Con IVA: <span data-line-total>—</span></span>
                            </td>
                            <td><button type="button" class="danger small" data-remove-line aria-label="Quitar línea <?= e((string) $position) ?>">Quitar</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <dl class="detail-list sale-totals" aria-live="polite" aria-atomic="true" aria-label="Totales de la venta">
            <div><dt>Subtotal</dt><dd><span data-sale-subtotal>$ 0,00</span></dd></div>
            <div><dt>IVA</dt><dd><span data-sale-tax>$ 0,00</span></dd></div>
            <div><dt>Total</dt><dd><strong><span data-sale-total>$ 0,00</span></strong></dd></div>
        </dl>

        <p class="hint">El precio sugerido se calcula con el costo del lote y el margen. Podés editarlo: el margen se recalcula automáticamente. Los importes son una estimación hasta confirmar la venta.</p>

        <div class="form-actions" data-provisional-acceptance hidden>
            <label class="checkbox-field">
                <input type="checkbox" name="acepta_costo_provisional" value="1"<?= isset($errors['acepta_costo_provisional']) ? ' aria-invalid="true"' : '' ?>>
                Acepto vender con costo provisional
            </label>
            <?= $fieldError('acepta_costo_provisional') ?>
        </div>
        <p class="hint" data-provisional-note hidden>Los lotes con costo provisional usan el costo acumulado hasta su cosecha; el ciclo todavía no está consolidado y el costo definitivo puede variar al consolidar. La venta conserva el costo usado como histórico.</p>

        <div class="form-actions">
            <button type="button" class="button ghost" data-add-line data-target="#sale-lines" data-template="#sale-line-template" aria-controls="sale-lines">Agregar línea</button>
        </div>

        <template id="sale-line-template">
            <tr data-line>
                <td>
                    <select name="detalles[__INDEX__][producto_lote]" data-lot-select aria-label="Producto y lote" required>
                        <option value="">Seleccionar lote</option>
                        <?php foreach (($lots ?? []) as $lot): ?>
                            <?php
                            $lotId = (int) $lot['id_lote_produccion'];
                            $productId = (int) $lot['id_producto'];
                            ?>
                            <option value="<?= e($productId . ':' . $lotId) ?>" data-costo="<?= e((string) $lot['costo_unitario']) ?>" data-alicuota="<?= e((string) $lot['porcentaje_alicuota']) ?>" data-unidad="<?= e((string) $lot['unidad_medida']) ?>" data-provisional="<?= (int) $lot['es_costo_provisional'] ?>">
                                <?= e($lot['producto']) ?> (<?= e($lot['unidad_medida']) ?>) — lote #<?= e((string) $lotId) ?> (disp. <?= e(qty($lot['cantidad_disponible'])) ?> <?= e($lot['unidad_medida']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" step="0.001" min="0.001" name="detalles[__INDEX__][cantidad]" data-quantity aria-label="Cantidad" required> <span class="hint" data-unit>—</span></td>
                <td><input type="number" step="0.01" min="0" max="99.99" name="detalles[__INDEX__][margen_porcentaje]" value="30" data-margin aria-label="Margen (%)" required></td>
                <td>
                    <input type="number" step="0.01" min="0" name="detalles[__INDEX__][precio_bruto]" data-price aria-label="Precio bruto sin IVA">
                    <span class="hint">Final unitario: <span data-final-price>—</span></span>
                </td>
                <td class="num">
                    <span class="hint">Sin IVA: <span data-line-subtotal>—</span></span>
                    <span class="hint">Con IVA: <span data-line-total>—</span></span>
                </td>
                <td><button type="button" class="danger small" data-remove-line>Quitar</button></td>
            </tr>
        </template>

        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button>Registrar venta</button>
            <a class="button ghost" href="<?= e(url('app/ventas')) ?>">Volver</a>
        </div>
    </form>
</div>
