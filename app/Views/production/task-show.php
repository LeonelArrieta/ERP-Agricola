<?php
$taskId = (int) $task['id_tarea_cultivo'];
$taskState = (string) ($task['estado'] ?? '');
$cycleId = (int) ($task['id_ciclo_cultivo'] ?? 0);
$cycleState = (string) ($task['ciclo_estado'] ?? '');
$canManage = in_array('produccion.crear', $permissions, true);
$canTransition = $canManage && $cycleState !== 'Cancelado';
$costos = $costos ?? ['costo_insumos' => 0, 'costo_trabajos' => 0, 'costo_total' => 0];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Producción</p>
        <h1><?= e($task['tarea'] ?? 'Tarea') ?></h1>
        <p><?= e($task['cultivo'] ?? '') ?> — <?= e($task['parcela'] ?? '') ?></p>
    </div>
    <div class="form-actions">
        <?= estado_badge($taskState) ?>
        <a class="button ghost" href="<?= e(url('app/ciclos-cultivo/' . $cycleId)) ?>">Ver ciclo</a>
        <a class="button ghost" href="<?= e(url('app/tareas-cultivo')) ?>">Volver</a>
    </div>
</div>

<dl class="detail-list">
    <div><dt>Tarea</dt><dd><?= e($task['tarea'] ?? '—') ?></dd></div>
    <div><dt>Cultivo</dt><dd><?= e($task['cultivo'] ?? '—') ?></dd></div>
    <div><dt>Parcela</dt><dd><?= e($task['parcela'] ?? '—') ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e(fecha($task['fecha'] ?? null)) ?></dd></div>
    <div><dt>Estado de la tarea</dt><dd><?= estado_badge($taskState) ?></dd></div>
    <div><dt>Estado del ciclo</dt><dd><?= estado_badge($cycleState) ?></dd></div>
    <div><dt>Observaciones</dt><dd><?= e($task['observaciones'] ?? '—') ?></dd></div>
    <div><dt>Costo de insumos</dt><dd><?= e(ars($costos['costo_insumos'])) ?></dd></div>
    <div><dt>Costo de mano de obra</dt><dd><?= e(ars($costos['costo_trabajos'])) ?></dd></div>
    <div><dt>Costo total de la tarea</dt><dd><strong><?= e(ars($costos['costo_total'])) ?></strong></dd></div>
</dl>

<?php if ($canTransition && in_array($taskState, ['Planificada', 'EnCurso'], true)): ?>
    <div class="panel">
        <h2>Acciones</h2>
        <div class="form-actions">
            <?php if ($taskState === 'Planificada' && $cycleState === 'EnCurso'): ?>
                <form method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/iniciar')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                    <button>Iniciar tarea</button>
                </form>
            <?php endif; ?>
            <?php if ($taskState === 'EnCurso'): ?>
                <form method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/completar')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                    <button>Completar tarea</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/cancelar')) ?>" data-confirm="¿Cancelar la tarea?">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
                <button class="danger">Cancelar tarea</button>
            </form>
        </div>
        <?php if ($taskState === 'Planificada' && $cycleState !== 'EnCurso'): ?>
            <p class="hint">La tarea podrá iniciarse cuando el ciclo esté EnCurso.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($taskState === 'EnCurso' && $canManage): ?>
    <div class="panel">
        <h2>Registrar consumo de insumo</h2>
        <p>El consumo descuenta stock, usa el PPP vigente y queda registrado con su costo histórico.</p>
        <form class="stacked" method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/consumos')) ?>" data-confirm="¿Registrar el consumo? Se descontará stock del insumo y se sumará al costo del ciclo.">
            <div class="form-grid">
                <div class="field">
                    <label for="id_insumo">Insumo</label>
                    <select id="id_insumo" name="id_insumo" required>
                        <option value="">Seleccionar insumo</option>
                        <?php foreach (($insumos ?? []) as $insumo): ?>
                            <option value="<?= e((string) $insumo['id_insumo']) ?>">
                                <?= e($insumo['nombre']) ?> — stock <?= e(qty($insumo['stock_actual'])) ?> — PPP $<?= e(number_format((float) $insumo['costo_unitario_actual'], 2, ',', '.')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="cantidad">Cantidad</label>
                    <input id="cantidad" type="number" step="0.001" min="0.001" name="cantidad" required>
                </div>
                <div class="field">
                    <label for="unidad_medida">Unidad</label>
                    <select id="unidad_medida" name="unidad_medida" required>
                        <option value="">Seleccionar</option>
                        <?php foreach (($units ?? []) as $unit): ?>
                            <option value="<?= e($unit['nombre']) ?>"><?= e($unit['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="observaciones">Observaciones</label>
                    <input id="observaciones" type="text" name="observaciones">
                </div>
            </div>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <div class="form-actions">
                <button class="danger">Registrar consumo</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($taskState === 'EnCurso' && $canManage): ?>
    <div class="panel">
        <h2>Registrar trabajo humano</h2>
        <p>La mano de obra se incorpora al costo del ciclo y queda congelada con su tarifa histórica.</p>
        <form class="stacked" method="post" action="<?= e(url('app/tareas-cultivo/' . $taskId . '/trabajos')) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="id_trabajador">Trabajador</label>
                    <select id="id_trabajador" name="id_trabajador" data-worker-select required>
                        <option value="">Seleccionar trabajador</option>
                        <?php foreach (($workers ?? []) as $worker): ?>
                            <option value="<?= e((string) $worker['id_trabajador']) ?>" data-costo="<?= e((string) $worker['costo_hora_actual']) ?>">
                                <?= e(trim(($worker['apellido'] ?? '') . ', ' . ($worker['nombre'] ?? ''), ', ')) ?> — $<?= e(number_format((float) $worker['costo_hora_actual'], 2, ',', '.')) ?>/h
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="horas_aplicadas">Horas aplicadas</label>
                    <input id="horas_aplicadas" type="number" step="0.25" min="0.25" name="horas_aplicadas" required>
                </div>
                <div class="field">
                    <label for="costo_hora">Costo por hora</label>
                    <input id="costo_hora" type="number" step="0.01" min="0" name="costo_hora" data-hour-cost>
                    <span class="hint">Se precarga la tarifa del trabajador y puede ajustarse.</span>
                </div>
                <div class="field">
                    <label for="trabajo_fecha">Fecha</label>
                    <input id="trabajo_fecha" type="date" name="fecha" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
            </div>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <div class="form-actions">
                <button>Registrar trabajo</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>Consumos registrados</h2>
    <?php if (($consumptions ?? []) === []): ?>
        <p class="empty">Sin consumos registrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Insumo</th>
                    <th scope="col">Fecha</th>
                    <th scope="col" class="num">Cantidad</th>
                    <th scope="col">Unidad</th>
                    <th scope="col" class="num">Costo unitario histórico</th>
                    <th scope="col" class="num">Costo total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($consumptions as $consumption): ?>
                    <tr>
                        <td><?= e((string) $consumption['id_consumo_insumo']) ?></td>
                        <td><?= e($consumption['insumo'] ?? '—') ?></td>
                        <td><?= e(fecha($consumption['fecha'] ?? null)) ?></td>
                        <td class="num"><?= e(qty($consumption['cantidad'])) ?></td>
                        <td><?= e($consumption['unidad_medida'] ?? '—') ?></td>
                        <td class="num"><?= e(ars($consumption['costo_unitario'])) ?></td>
                        <td class="num"><?= e(ars($consumption['costo_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Trabajos humanos registrados</h2>
    <?php if (($trabajos ?? []) === []): ?>
        <p class="empty">Sin trabajos registrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Trabajador</th>
                    <th scope="col">Fecha</th>
                    <th scope="col" class="num">Horas</th>
                    <th scope="col" class="num">Costo por hora</th>
                    <th scope="col" class="num">Costo total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($trabajos as $trabajo): ?>
                    <tr>
                        <td><?= e((string) $trabajo['id_trabajo_humano']) ?></td>
                        <td><?= e(trim(($trabajo['apellido'] ?? '') . ', ' . ($trabajo['nombre'] ?? ''), ', ')) ?></td>
                        <td><?= e(fecha($trabajo['fecha'] ?? null)) ?></td>
                        <td class="num"><?= e(qty($trabajo['horas_aplicadas'])) ?></td>
                        <td class="num"><?= e(ars($trabajo['costo_hora'])) ?></td>
                        <td class="num"><?= e(ars($trabajo['costo_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Costos de la tarea</h2>
    <dl class="detail-list">
        <div><dt>Insumos consumidos</dt><dd><?= e(ars($costos['costo_insumos'])) ?></dd></div>
        <div><dt>Mano de obra</dt><dd><?= e(ars($costos['costo_trabajos'])) ?></dd></div>
        <div><dt>Total</dt><dd><strong><?= e(ars($costos['costo_total'])) ?></strong></dd></div>
    </dl>
</div>

<?php if ($taskState === 'Completada'): ?>
    <div class="flash flash-success">Tarea completada. No admite nuevos consumos.</div>
<?php elseif ($taskState === 'Cancelada'): ?>
    <div class="flash flash-warning">Tarea cancelada. No admite trabajo ni consumos.</div>
<?php endif; ?>
