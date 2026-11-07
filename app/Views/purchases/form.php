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
$unitNames = array_map(static fn (array $unit): string => (string) $unit['nombre'], $units ?? []);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Comercial</p>
        <h1>Nueva compra</h1>
        <p>La compra contado genera egreso de Caja; la compra crédito genera una CxP. El inventario se actualiza en ambos casos.</p>
    </div>
</div>

<?= empty($errors) ? error_modal($error ?? null) : '' ?>
<?php if (!empty($errors['_form'])): ?>
    <div class="form-error-summary" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<div class="panel">
    <form class="stacked" method="post" action="<?= e(url('app/compras')) ?>">
        <div class="form-grid">
            <div class="field">
                <label for="id_proveedor">Proveedor</label>
                <select id="id_proveedor" name="id_proveedor" required<?= $invalid('id_proveedor') ?>>
                    <option value="">Seleccionar proveedor</option>
                    <?php foreach (($suppliers ?? []) as $supplier): ?>
                        <option value="<?= e((string) $supplier['id_proveedor']) ?>"<?= (string) ($values['id_proveedor'] ?? '') === (string) $supplier['id_proveedor'] ? ' selected' : '' ?>>
                            <?= e($supplier['razon_social'] ?? trim(($supplier['nombre'] ?? '') . ' ' . ($supplier['apellido'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError('id_proveedor') ?>
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

        <h2>Detalle</h2>
        <div class="table-wrap">
            <table>
                <caption class="visually-hidden">Líneas de la compra</caption>
                <thead>
                <tr>
                    <th scope="col">Insumo</th>
                    <th scope="col" class="num">Cantidad</th>
                    <th scope="col">Unidad</th>
                    <th scope="col" class="num">Costo unitario</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody id="purchase-lines">
                <?php $position = 0; ?>
                <?php foreach ($lines as $index => $line): ?>
                    <?php
                    $position++;
                    $line = is_array($line) ? $line : [];
                    $lineUnit = (string) ($line['unidad_medida'] ?? '');
                    ?>
                    <tr data-line>
                        <td>
                            <select name="detalles[<?= e((string) $index) ?>][id_insumo]" aria-label="Insumo" required>
                                <option value="">Seleccionar insumo</option>
                                <?php foreach (($insumos ?? []) as $insumo): ?>
                                    <option value="<?= e((string) $insumo['id_insumo']) ?>"<?= (string) ($line['id_insumo'] ?? '') === (string) $insumo['id_insumo'] ? ' selected' : '' ?>>
                                        <?= e($insumo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.001" min="0.001" name="detalles[<?= e((string) $index) ?>][cantidad]" value="<?= e($line['cantidad'] ?? '') ?>" aria-label="Cantidad" required></td>
                        <td>
                            <select name="detalles[<?= e((string) $index) ?>][unidad_medida]" aria-label="Unidad de medida" required>
                                <option value="">Seleccionar</option>
                                <?php foreach (($units ?? []) as $unit): ?>
                                    <option value="<?= e($unit['nombre']) ?>"<?= $lineUnit === (string) $unit['nombre'] ? ' selected' : '' ?>><?= e($unit['nombre']) ?></option>
                                <?php endforeach; ?>
                                <?php if ($lineUnit !== '' && !in_array($lineUnit, $unitNames, true)): ?>
                                    <option value="<?= e($lineUnit) ?>" selected><?= e($lineUnit) ?> (fuera del maestro)</option>
                                <?php endif; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" min="0" name="detalles[<?= e((string) $index) ?>][costo_unitario]" value="<?= e($line['costo_unitario'] ?? '') ?>" aria-label="Costo unitario" required></td>
                        <td><button type="button" class="danger small" data-remove-line aria-label="Quitar línea <?= e((string) $position) ?>">Quitar</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="button" class="button ghost" data-add-line data-target="#purchase-lines" data-template="#purchase-line-template">Agregar línea</button>
        </div>

        <template id="purchase-line-template">
            <tr data-line>
                <td>
                    <select name="detalles[__INDEX__][id_insumo]" aria-label="Insumo" required>
                        <option value="">Seleccionar insumo</option>
                        <?php foreach (($insumos ?? []) as $insumo): ?>
                            <option value="<?= e((string) $insumo['id_insumo']) ?>"><?= e($insumo['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" step="0.001" min="0.001" name="detalles[__INDEX__][cantidad]" aria-label="Cantidad" required></td>
                <td>
                    <select name="detalles[__INDEX__][unidad_medida]" aria-label="Unidad de medida" required>
                        <option value="">Seleccionar</option>
                        <?php foreach (($units ?? []) as $unit): ?>
                            <option value="<?= e($unit['nombre']) ?>"><?= e($unit['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" step="0.01" min="0" name="detalles[__INDEX__][costo_unitario]" aria-label="Costo unitario" required></td>
                <td><button type="button" class="danger small" data-remove-line>Quitar</button></td>
            </tr>
        </template>

        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <div class="form-actions">
            <button>Registrar compra</button>
            <a class="button ghost" href="<?= e(url('app/compras')) ?>">Volver</a>
        </div>
    </form>
</div>
