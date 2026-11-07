<?php
$cycleId = (int) $cycle['id_ciclo_cultivo'];
$state = (string) ($cycle['estado'] ?? '');
$canManage = in_array('produccion.crear', $permissions, true);
$canCancel = in_array('produccion.cancelar', $permissions, true);
$taskStates = ['Planificada', 'EnCurso', 'Completada', 'Cancelada'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Producción</p>
        <h1><?= e($cycle['cultivo'] ?? 'Ciclo') ?> — <?= e($cycle['parcela'] ?? '') ?></h1>
        <p>Ciclo #<?= e((string) $cycleId) ?>. Los costos se acumulan desde trabajo y consumos.</p>
    </div>
    <div class="form-actions">
        <?= estado_badge($state) ?>
        <?php if ($state === 'Planificado' && $canManage): ?>
            <form method="post" action="<?= e(url('app/ciclos-cultivo/' . $cycleId . '/iniciar')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button>Iniciar ciclo</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($state, ['Planificado', 'EnCurso'], true) && $canCancel): ?>
            <form method="post" action="<?= e(url('app/ciclos-cultivo/' . $cycleId . '/cancelar')) ?>" data-confirm="¿Cancelar el ciclo? No se elimina información; el ciclo deja de admitir operaciones.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Cancelar ciclo</button>
            </form>
        <?php endif; ?>
        <a class="button ghost" href="<?= e(url('app/ciclos-cultivo')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Cultivo</dt><dd><?= e($cycle['cultivo'] ?? '—') ?></dd></div>
    <div><dt>Parcela</dt><dd><?= e($cycle['parcela'] ?? '—') ?></dd></div>
    <div><dt>Fecha de inicio</dt><dd><?= e(fecha($cycle['fecha_inicio'] ?? null)) ?></dd></div>
    <div><dt>Fecha de fin</dt><dd><?= e(fecha($cycle['fecha_fin'] ?? null)) ?></dd></div>
    <div><dt>Observaciones</dt><dd><?= e($cycle['observaciones'] ?? '—') ?></dd></div>
    <div><dt>Producción estimada</dt><dd><?= ($cycle['cantidad_estimada'] ?? null) !== null ? e(qty($cycle['cantidad_estimada']) . ' ' . ($cycle['unidad_estimada'] ?? '')) : '—' ?></dd></div>
</dl>

<?php if (in_array($state, ['Planificado', 'EnCurso'], true) && $canManage): ?>
    <div class="panel">
        <h2>Producción estimada</h2>
        <p>Se usa para estimar el costo provisional de los lotes al cosechar. No reemplaza la cantidad real cosechada.</p>
        <form class="filters" method="post" action="<?= e(url('app/ciclos-cultivo/' . $cycleId . '/estimacion')) ?>">
            <div class="field">
                <label for="cantidad_estimada">Cantidad</label>
                <input id="cantidad_estimada" type="number" step="0.001" min="0.001" name="cantidad_estimada" value="<?= e((string) ($cycle['cantidad_estimada'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="unidad_estimada">Unidad</label>
                <select id="unidad_estimada" name="unidad_estimada">
                    <option value="">Sin definir</option>
                    <?php foreach (($units ?? []) as $unit): ?>
                        <option value="<?= e($unit['nombre']) ?>"<?= (string) ($cycle['unidad_estimada'] ?? '') === (string) $unit['nombre'] ? ' selected' : '' ?>><?= e($unit['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button class="button ghost">Actualizar estimación</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($state === 'EnCurso'): ?>
    <div class="panel">
        <h2>Registrar cosecha</h2>
        <p>Genera un lote provisional con costo estimado hasta la consolidación.</p>
        <form class="stacked" method="post" action="<?= e(url('app/ciclos-cultivo/' . $cycleId . '/cosechas')) ?>" data-confirm="¿Registrar la cosecha? Se creará un lote con costo provisional.">
            <div class="form-grid">
                <div class="field">
                    <label for="id_producto">Producto</label>
                    <select id="id_producto" name="id_producto" data-harvest-product required>
                        <option value="">Seleccionar producto</option>
                        <?php foreach (($products ?? []) as $product): ?>
                            <option value="<?= e((string) $product['id_producto']) ?>" data-unidad="<?= e($product['unidad_medida']) ?>"><?= e($product['nombre']) ?> (<?= e($product['unidad_medida']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="cosecha_cantidad">Cantidad</label>
                    <input id="cosecha_cantidad" type="number" step="0.001" min="0.001" name="cantidad" required>
                </div>
                <div class="field">
                    <label for="cosecha_unidad">Unidad</label>
                    <input id="cosecha_unidad" type="text" data-harvest-unit readonly>
                    <span class="hint">Se toma de la unidad del producto seleccionado.</span>
                </div>
                <div class="field">
                    <label for="cosecha_fecha">Fecha</label>
                    <input id="cosecha_fecha" type="date" name="fecha" value="<?= e(date('Y-m-d')) ?>">
                </div>
            </div>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <div class="form-actions">
                <button>Registrar cosecha</button>
            </div>
        </form>
        <p class="hint">El lote se crea con costo provisional calculado con los costos acumulados hasta la cosecha<?= ($cycle['cantidad_estimada'] ?? null) !== null ? ' y la producción estimada (' . e(qty($cycle['cantidad_estimada']) . ' ' . ($cycle['unidad_estimada'] ?? '')) . ')' : '' ?>. El ciclo no está consolidado: el costo definitivo se calcula al consolidar con la cantidad realmente cosechada.</p>
    </div>

    <div class="panel">
        <h2>Consolidar ciclo</h2>
        <p>Distribuye el costo total del ciclo entre las cosechas y fija el costo definitivo de los lotes.</p>
        <form method="post" action="<?= e(url('app/ciclos-cultivo/' . $cycleId . '/consolidar')) ?>" data-confirm="¿Consolidar el ciclo? Los lotes pasarán de costo provisional a costo definitivo y el ciclo quedará Cosechado.">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button>Consolidar ciclo</button>
        </form>
    </div>
<?php endif; ?>

<?php if (in_array($state, ['Planificado', 'EnCurso'], true) && $canManage): ?>
    <?php
    $cycleEnCurso = $state === 'EnCurso';
    $resourceDisabled = $cycleEnCurso ? '' : ' disabled';
    ?>
    <div class="panel">
        <h2>Nueva tarea de cultivo</h2>
        <p>Creá la tarea y, si el ciclo está en curso, cargá los insumos y la mano de obra en el mismo paso.</p>
        <form class="stacked" method="post" action="<?= e(url('app/ciclos-cultivo/' . $cycleId . '/tareas')) ?>" data-task-form<?= $cycleEnCurso ? '' : ' data-resources-locked="1"' ?>>
            <div class="form-grid">
                <div class="field">
                    <label for="id_tarea">Tarea</label>
                    <select id="id_tarea" name="id_tarea" required>
                        <option value="">Seleccionar tarea</option>
                        <?php foreach (($taskTypes ?? []) as $taskType): ?>
                            <option value="<?= e((string) $taskType['id_tarea']) ?>"><?= e($taskType['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="tarea_fecha">Fecha</label>
                    <input id="tarea_fecha" type="date" name="fecha" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="field">
                    <label for="tarea_observaciones">Observaciones</label>
                    <input id="tarea_observaciones" type="text" name="observaciones">
                </div>
            </div>

            <div class="form-actions">
                <label class="checkbox-field">
                    <input type="checkbox" name="iniciar" value="1" data-start-task<?= $cycleEnCurso ? ' checked' : ' disabled' ?>>
                    Iniciar tarea ahora
                </label>
                <label class="checkbox-field">
                    <input type="checkbox" name="completar" value="1" data-complete-task<?= $resourceDisabled ?>>
                    Completar al guardar
                </label>
            </div>

            <?php if (!$cycleEnCurso): ?>
                <div class="flash flash-warning">Inicie el ciclo para cargar insumos y mano de obra. Por ahora solo puede planificar la tarea.</div>
            <?php endif; ?>
            <p class="hint" data-resources-hint<?= $cycleEnCurso ? ' hidden' : '' ?>>Los recursos se habilitan al iniciar la tarea.</p>

            <h3>Insumos a consumir</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Insumo</th>
                        <th scope="col" class="num">Cantidad</th>
                        <th scope="col">Unidad</th>
                        <th scope="col" class="num">Costo estimado</th>
                        <th scope="col"></th>
                    </tr>
                    </thead>
                    <tbody id="task-insumos">
                    <tr data-line data-resource-line="insumo">
                        <td>
                            <select name="detalles_insumos[0][id_insumo]" data-insumo-select data-resource-field aria-label="Insumo"<?= $resourceDisabled ?>>
                                <option value="">Sin insumo</option>
                                <?php foreach (($insumos ?? []) as $insumo): ?>
                                    <option value="<?= e((string) $insumo['id_insumo']) ?>" data-unidad="<?= e($insumo['unidad_medida']) ?>" data-costo="<?= e((string) $insumo['costo_unitario_actual']) ?>"><?= e($insumo['nombre']) ?> — stock <?= e(qty($insumo['stock_actual'])) ?> <?= e($insumo['unidad_medida']) ?> · PPP $<?= e(number_format((float) $insumo['costo_unitario_actual'], 2, ',', '.')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.001" min="0.001" name="detalles_insumos[0][cantidad]" data-quantity data-resource-field aria-label="Cantidad"<?= $resourceDisabled ?>></td>
                        <td><span data-unit>—</span></td>
                        <td><span data-line-total>—</span></td>
                        <td><button type="button" class="danger small" data-remove-line data-resource-field<?= $resourceDisabled ?>>Quitar</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="button" class="button ghost" data-add-line data-target="#task-insumos" data-template="#task-insumo-template" data-resource-field<?= $resourceDisabled ?>>Agregar insumo</button>
            </div>

            <template id="task-insumo-template">
                <tr data-line data-resource-line="insumo">
                    <td>
                        <select name="detalles_insumos[__INDEX__][id_insumo]" data-insumo-select data-resource-field aria-label="Insumo"<?= $resourceDisabled ?>>
                            <option value="">Sin insumo</option>
                            <?php foreach (($insumos ?? []) as $insumo): ?>
                                <option value="<?= e((string) $insumo['id_insumo']) ?>" data-unidad="<?= e($insumo['unidad_medida']) ?>" data-costo="<?= e((string) $insumo['costo_unitario_actual']) ?>"><?= e($insumo['nombre']) ?> — stock <?= e(qty($insumo['stock_actual'])) ?> <?= e($insumo['unidad_medida']) ?> · PPP $<?= e(number_format((float) $insumo['costo_unitario_actual'], 2, ',', '.')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.001" min="0.001" name="detalles_insumos[__INDEX__][cantidad]" data-quantity data-resource-field aria-label="Cantidad"<?= $resourceDisabled ?>></td>
                    <td><span data-unit>—</span></td>
                    <td><span data-line-total>—</span></td>
                    <td><button type="button" class="danger small" data-remove-line data-resource-field<?= $resourceDisabled ?>>Quitar</button></td>
                </tr>
            </template>

            <h3>Mano de obra</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Trabajador</th>
                        <th scope="col" class="num">Horas</th>
                        <th scope="col" class="num">Costo por hora</th>
                        <th scope="col" class="num">Subtotal</th>
                        <th scope="col"></th>
                    </tr>
                    </thead>
                    <tbody id="task-trabajos">
                    <tr data-line data-resource-line="trabajo">
                        <td>
                            <select name="detalles_trabajos[0][id_trabajador]" data-worker-select data-resource-field aria-label="Trabajador"<?= $resourceDisabled ?>>
                                <option value="">Sin trabajador</option>
                                <?php foreach (($workers ?? []) as $worker): ?>
                                    <option value="<?= e((string) $worker['id_trabajador']) ?>" data-costo="<?= e((string) $worker['costo_hora_actual']) ?>"><?= e(trim(($worker['apellido'] ?? '') . ', ' . ($worker['nombre'] ?? ''), ', ')) ?> — $<?= e(number_format((float) $worker['costo_hora_actual'], 2, ',', '.')) ?>/h</option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.25" min="0.25" name="detalles_trabajos[0][horas_aplicadas]" data-hours data-resource-field aria-label="Horas aplicadas"<?= $resourceDisabled ?>></td>
                        <td><input type="number" step="0.01" min="0" name="detalles_trabajos[0][costo_hora]" data-hour-cost data-resource-field aria-label="Costo por hora"<?= $resourceDisabled ?>></td>
                        <td><span data-line-total>—</span></td>
                        <td><button type="button" class="danger small" data-remove-line data-resource-field<?= $resourceDisabled ?>>Quitar</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="button" class="button ghost" data-add-line data-target="#task-trabajos" data-template="#task-trabajo-template" data-resource-field<?= $resourceDisabled ?>>Agregar trabajo</button>
            </div>

            <template id="task-trabajo-template">
                <tr data-line data-resource-line="trabajo">
                    <td>
                        <select name="detalles_trabajos[__INDEX__][id_trabajador]" data-worker-select data-resource-field aria-label="Trabajador"<?= $resourceDisabled ?>>
                            <option value="">Sin trabajador</option>
                            <?php foreach (($workers ?? []) as $worker): ?>
                                <option value="<?= e((string) $worker['id_trabajador']) ?>" data-costo="<?= e((string) $worker['costo_hora_actual']) ?>"><?= e(trim(($worker['apellido'] ?? '') . ', ' . ($worker['nombre'] ?? ''), ', ')) ?> — $<?= e(number_format((float) $worker['costo_hora_actual'], 2, ',', '.')) ?>/h</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.25" min="0.25" name="detalles_trabajos[__INDEX__][horas_aplicadas]" data-hours data-resource-field aria-label="Horas aplicadas"<?= $resourceDisabled ?>></td>
                    <td><input type="number" step="0.01" min="0" name="detalles_trabajos[__INDEX__][costo_hora]" data-hour-cost data-resource-field aria-label="Costo por hora"<?= $resourceDisabled ?>></td>
                    <td><span data-line-total>—</span></td>
                    <td><button type="button" class="danger small" data-remove-line data-resource-field<?= $resourceDisabled ?>>Quitar</button></td>
                </tr>
            </template>

            <p class="hint" aria-live="polite" aria-atomic="true">Insumos: <strong data-total-insumos>$ 0,00</strong> · Mano de obra: <strong data-total-trabajos>$ 0,00</strong> · Total estimado: <strong data-total-general>$ 0,00</strong></p>

            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <div class="form-actions">
                <button>Crear tarea</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>Tareas del ciclo</h2>
    <?php
    $cycleTotal = 0.0;
    foreach (($taskCosts ?? []) as $cost) {
        $cycleTotal += (float) ($cost['costo_total'] ?? 0);
    }
    ?>
    <p class="hint">Costo acumulado del ciclo: <?= e(ars($cycleTotal)) ?> (insumos + mano de obra).</p>
    <?php if (($tasks ?? []) === []): ?>
        <?= empty_state('Sin tareas registradas.', 'Creá una tarea para este ciclo y registrá insumos y mano de obra.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Tarea</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="num">Costo</th>
                    <th scope="col">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $taskId = (int) $task['id_tarea_cultivo'];
                    $taskState = (string) ($task['estado'] ?? '');
                    $taskCost = $taskCosts[$taskId] ?? null;
                    ?>
                    <tr>
                        <td><?= e((string) $taskId) ?></td>
                        <td><?= e($task['tarea'] ?? '—') ?></td>
                        <td><?= e(fecha($task['fecha'] ?? null)) ?></td>
                        <td><?= estado_badge($taskState) ?></td>
                        <td class="num"><?= e(ars($taskCost['costo_total'] ?? 0)) ?></td>
                        <td>
                            <div class="actions">
                                <a class="button ghost small" href="<?= e(url('app/tareas-cultivo/' . $taskId)) ?>">Ver</a>
                                <?php if ($taskState === 'Planificada' && $canManage): ?>
                                    <form method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/iniciar')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                                        <button class="small">Iniciar</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($taskState === 'EnCurso' && $canManage): ?>
                                    <form method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/completar')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                                        <button class="small">Completar</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($taskState, ['Planificada', 'EnCurso'], true) && $canManage): ?>
                                    <form method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/cancelar')) ?>" data-confirm="¿Cancelar la tarea?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                                        <button class="danger small">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($state === 'Cosechado'): ?>
    <div class="flash flash-success">Ciclo cosechado y costos consolidados. Los lotes tienen costo definitivo.</div>
<?php elseif ($state === 'Cancelado'): ?>
    <div class="flash flash-warning">Ciclo cancelado. No admite nuevas tareas, consumos ni cosechas.</div>
<?php endif; ?>
