<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Logger;
use App\Domain\Facturacion\CalculadorFiscal;
use App\Domain\Facturacion\ValidadorFiscal;
use App\Infrastructure\Arca\ArcaCatalogs;
use App\Infrastructure\Arca\ArcaCommunicationException;
use App\Infrastructure\Arca\ArcaGateway;
use App\Infrastructure\Arca\ArcaRequestMapper;
use App\Repositories\ContactRepository;
use App\Repositories\FacturacionSolicitudRepository;
use App\Repositories\FiscalRepository;
use App\Repositories\SaleRepository;
use PDO;

final class FacturacionService
{
    private const ADAPTER_VERSION = 'wsfev1-v1';

    public function __construct(
        private readonly PDO $pdo,
        private readonly FiscalRepository $fiscal,
        private readonly SaleRepository $sales,
        private readonly ContactRepository $clients,
        private readonly ArcaGateway $gateway,
        private readonly int $cuit,
        private readonly int $condicionEmisor,
        private readonly bool $emissionEnabled = true,
        private readonly ValidadorFiscal $validator = new ValidadorFiscal(),
        private readonly CalculadorFiscal $calculator = new CalculadorFiscal(),
        private readonly string $environment = 'mock',
        private readonly ?FacturacionSolicitudRepository $solicitudes = null,
        private readonly ?Logger $logger = null,
        private readonly ?EmisorService $emisor = null,
        private readonly ?int $defaultPointOfSale = null,
    ) {
    }

    public function list(int $page, int $perPage, array $filters = []): array
    {
        $result = $this->fiscal->vouchers(($page - 1) * $perPage, $perPage, $filters);

        return ['items' => $result['items'], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']]];
    }

    public function get(int $id): array
    {
        $voucher = $this->fiscal->voucher($id);
        if ($voucher === null) {
            throw new HttpException('NOT_FOUND', 'El comprobante fiscal no existe.', 404);
        }

        $associated = null;
        if (($voucher['id_comprobante_asociado'] ?? null) !== null) {
            $associated = $this->fiscal->voucher((int) $voucher['id_comprobante_asociado']);
        }

        return ['comprobante' => $voucher, 'iva' => $this->fiscal->iva($id), 'asociado' => $associated];
    }

    public function vouchersBySale(int $saleId): array
    {
        return $this->fiscal->vouchersBySale($saleId);
    }

    public function attempts(int $voucherId): array
    {
        return $this->solicitudes?->byVoucher($voucherId) ?? [];
    }

    public function types(): array
    {
        return $this->fiscal->voucherTypes();
    }

    public function invoiceDefaults(int $saleId): array
    {
        $sale = $this->sales->find($saleId);
        if ($sale === null) {
            throw new HttpException('NOT_FOUND', 'La venta no existe.', 404);
        }

        $client = $this->clients->find((int) $sale['id_cliente']);
        if ($client === null) {
            throw new HttpException('NOT_FOUND', 'El cliente de la venta no existe.', 404);
        }

        $docTipo = $client['doc_tipo'] !== null ? (int) $client['doc_tipo'] : null;
        $docNro = trim((string) ($client['doc_nro'] ?? ''));
        $condicion = $client['condicion_iva_receptor_id'] !== null ? (int) $client['condicion_iva_receptor_id'] : null;
        $clientComplete = $docTipo !== null
            && isset(ArcaCatalogs::DOC_TIPOS[$docTipo])
            && $docNro !== ''
            && $condicion !== null
            && isset(ArcaCatalogs::CONDICIONES_IVA[$condicion]);

        $types = [];
        foreach ($this->fiscal->voucherTypes() as $type) {
            $typeId = (int) $type['id_tipo_comprobante'];
            if (!in_array($typeId, ArcaCatalogs::FACTURAS, true)) {
                continue;
            }
            if ($condicion === null || !ArcaCatalogs::condicionCompatible($condicion, (string) $type['clase'])) {
                continue;
            }
            if (!ArcaCatalogs::condicionEmisorPermite($this->condicionEmisor, (string) $type['clase'])) {
                continue;
            }
            $types[] = $type;
        }

        $suggestedType = null;
        if ($clientComplete && $types !== []) {
            $preferred = $this->condicionEmisor !== 1
                ? 11
                : ($condicion === 1 ? 1 : 6);
            foreach ($types as $type) {
                if ((int) $type['id_tipo_comprobante'] === $preferred) {
                    $suggestedType = $preferred;
                    break;
                }
            }
            if ($suggestedType === null) {
                $suggestedType = (int) $types[0]['id_tipo_comprobante'];
            }
        }

        $points = $this->fiscal->pointOfSaleOptions();
        $defaultPoint = null;
        if ($this->defaultPointOfSale !== null) {
            foreach ($points as $point) {
                if ((int) $point['numero'] === $this->defaultPointOfSale) {
                    $defaultPoint = (int) $point['id_punto_venta'];
                    break;
                }
            }
        }
        if ($defaultPoint === null && $points !== []) {
            $defaultPoint = (int) $points[0]['id_punto_venta'];
        }

        $reason = null;
        if (!$clientComplete) {
            $reason = 'El cliente no tiene datos fiscales completos.';
        } elseif ($types === []) {
            $reason = 'La condición IVA del cliente no es compatible con los comprobantes disponibles.';
        } elseif ($points === []) {
            $reason = 'No hay puntos de venta activos.';
        }

        return [
            'client' => [
                'id' => (int) $client['id_cliente'],
                'name' => trim((string) ($client['razon_social'] ?? '')) !== ''
                    ? (string) $client['razon_social']
                    : trim((string) ($client['nombre'] ?? '') . ' ' . (string) ($client['apellido'] ?? '')),
                'doc_tipo' => $docTipo,
                'doc_tipo_label' => $docTipo !== null ? (ArcaCatalogs::DOC_TIPOS[$docTipo] ?? '') : '',
                'doc_nro' => $docNro,
                'condicion_iva' => $condicion,
                'condicion_iva_label' => $condicion !== null ? (ArcaCatalogs::CONDICIONES_IVA[$condicion]['label'] ?? '') : '',
                'complete' => $clientComplete,
            ],
            'types' => $types,
            'suggested_type' => $suggestedType,
            'points' => $points,
            'default_point' => $defaultPoint,
            'concept' => 1,
            'date' => date('Y-m-d'),
            'can_emit' => $clientComplete && $types !== [] && $points !== [],
            'reason' => $reason,
        ];
    }

    public function emitInvoice(int $saleId, array $input, int $actor): array
    {
        $this->assertEmissionEnabled();
        $emisor = $this->requireEmisor();
        $sale = $this->sales->find($saleId);
        if ($sale === null) {
            throw new HttpException('NOT_FOUND', 'La venta no existe.', 404);
        }
        if ((string) $sale['estado'] === 'Cancelado') {
            throw new HttpException('CONFLICT', 'No se puede facturar una venta cancelada.', 409);
        }
        if ($this->fiscal->authorizedInvoiceForSale($saleId) !== null) {
            throw new HttpException('CONFLICT', 'La venta ya tiene un comprobante autorizado.', 409);
        }

        $type = $this->requireType($input['id_tipo_comprobante'] ?? null, ArcaCatalogs::FACTURAS, 'factura');
        $point = $this->requirePointOfSale($input['id_punto_venta'] ?? null);
        $concepto = $this->validator->concepto($input);
        $receptor = $this->receptorFromSale($sale, $type);

        $details = $this->sales->details($saleId);
        [$neto, $iva, $total, $ivaRows] = $this->calculator->forInvoice($sale, $details, $type);

        $voucherData = [
            'id_venta' => $saleId,
            'id_punto_venta' => (int) $point['id_punto_venta'],
            'id_tipo_comprobante' => (int) $type['id_tipo_comprobante'],
            'numero' => null,
            'fecha' => $this->validator->fecha($input['fecha'] ?? null),
            'concepto' => $concepto['concepto'],
            'doc_tipo' => $receptor['doc_tipo'],
            'doc_nro' => $receptor['doc_nro'],
            'condicion_iva_receptor_id' => $receptor['condicion_iva_receptor_id'],
            'imp_total' => $total,
            'imp_tot_conc' => 0,
            'imp_neto' => $neto,
            'imp_op_ex' => 0,
            'imp_iva' => $iva,
            'imp_trib' => 0,
            'moneda' => 'PES',
            'cotizacion' => 1,
            'paga_misma_moneda' => null,
            'fch_serv_desde' => $concepto['fch_serv_desde'],
            'fch_serv_hasta' => $concepto['fch_serv_hasta'],
            'fch_vto_pago' => $concepto['fch_vto_pago'],
            'id_comprobante_asociado' => null,
            'asoc_tipo' => null,
            'asoc_pto_vta' => null,
            'asoc_nro' => null,
            'asoc_fecha' => null,
            'asoc_cuit' => null,
        ] + $this->emisorSnapshot($emisor);

        return $this->createAndSend($voucherData, $ivaRows, $actor);
    }

    public function emitNote(int $voucherId, array $input, int $actor): array
    {
        $this->assertEmissionEnabled();
        $emisor = $this->requireEmisor();
        $original = $this->fiscal->voucher($voucherId);
        if ($original === null) {
            throw new HttpException('NOT_FOUND', 'El comprobante original no existe.', 404);
        }
        if ((string) $original['estado_fiscal'] !== 'Autorizado') {
            throw new HttpException('CONFLICT', 'Solo se pueden asociar notas a comprobantes autorizados.', 409);
        }
        if ($original['numero'] === null) {
            throw new HttpException('CONFLICT', 'El comprobante original no tiene numero fiscal.', 409);
        }

        $type = $this->requireType($input['id_tipo_comprobante'] ?? null, array_merge(ArcaCatalogs::NOTAS_CREDITO, ArcaCatalogs::NOTAS_DEBITO), 'nota');
        if ((string) $type['clase'] !== (string) $original['clase']) {
            throw new HttpException('VALIDATION_ERROR', 'La clase de la nota debe coincidir con la del comprobante original.', 422);
        }
        $point = $this->requirePointOfSale($input['id_punto_venta'] ?? null);
        $concepto = $this->validator->concepto($input);
        $total = $this->validator->importeNota($input['importe'] ?? null);
        [$neto, $iva, $ivaRows] = $this->calculator->forNote($original, $this->fiscal->iva($voucherId), $total);

        $voucherData = [
            'id_venta' => (int) $original['id_venta'],
            'id_punto_venta' => (int) $point['id_punto_venta'],
            'id_tipo_comprobante' => (int) $type['id_tipo_comprobante'],
            'numero' => null,
            'fecha' => $this->validator->fecha($input['fecha'] ?? null),
            'concepto' => $concepto['concepto'],
            'doc_tipo' => (int) $original['doc_tipo'],
            'doc_nro' => (string) $original['doc_nro'],
            'condicion_iva_receptor_id' => (int) $original['condicion_iva_receptor_id'],
            'imp_total' => $total,
            'imp_tot_conc' => 0,
            'imp_neto' => $neto,
            'imp_op_ex' => 0,
            'imp_iva' => $iva,
            'imp_trib' => 0,
            'moneda' => 'PES',
            'cotizacion' => 1,
            'paga_misma_moneda' => null,
            'fch_serv_desde' => $concepto['fch_serv_desde'],
            'fch_serv_hasta' => $concepto['fch_serv_hasta'],
            'fch_vto_pago' => $concepto['fch_vto_pago'],
            'id_comprobante_asociado' => $voucherId,
            'asoc_tipo' => (int) $original['id_tipo_comprobante'],
            'asoc_pto_vta' => (int) $original['punto_venta_numero'],
            'asoc_nro' => (int) $original['numero'],
            'asoc_fecha' => (string) $original['fecha'],
            'asoc_cuit' => (string) $this->cuit,
        ] + $this->emisorSnapshot($emisor);

        return $this->createAndSend($voucherData, $ivaRows, $actor);
    }

    public function reconcile(int $voucherId, int $actor): array
    {
        $voucher = $this->fiscal->voucher($voucherId);
        if ($voucher === null) {
            throw new HttpException('NOT_FOUND', 'El comprobante fiscal no existe.', 404);
        }
        if (!in_array((string) $voucher['estado_fiscal'], ['ErrorComunicacion', 'ResultadoIncierto', 'Enviando'], true)) {
            throw new HttpException('CONFLICT', 'Solo se reconcilian comprobantes con resultado incierto o envio pendiente.', 409);
        }
        if ($voucher['numero'] === null) {
            throw new HttpException('CONFLICT', 'El comprobante no tiene numero asignado para consultar.', 409);
        }

        try {
            $result = $this->gateway->consult((int) $voucher['punto_venta_numero'], (int) $voucher['id_tipo_comprobante'], (int) $voucher['numero']);
        } catch (ArcaCommunicationException $exception) {
            $this->audit($actor, 'ReconciliarError', $voucherId, ['error' => $exception->getMessage()]);
            throw new HttpException('CONFLICT', 'No se pudo consultar el comprobante en ARCA. Reintente mas tarde.', 409);
        }

        if ($result !== null && ($result['resultado'] ?? '') === 'A' && $result['cae'] !== null) {
            $this->fiscal->updateVoucher($voucherId, [
                'estado_fiscal' => 'Autorizado',
                'cae' => $result['cae'],
                'cae_fch_vto' => $result['cae_fch_vto'],
                'resultado_arca' => 'A',
                'observaciones' => $result['observaciones'] !== [] ? json_encode($result['observaciones']) : null,
            ]);
            $this->fiscal->markSaleInvoiced((int) $voucher['id_venta']);
            $this->audit($actor, 'ReconciliarAutorizado', $voucherId, ['cae' => $result['cae']]);
            $this->logger?->info('arca.reconcile.result', ['voucher' => $voucherId, 'state' => 'Autorizado', 'environment' => $this->environment]);
        } else {
            $this->fiscal->updateVoucher($voucherId, ['estado_fiscal' => 'Pendiente']);
            $this->audit($actor, 'ReconciliarPendiente', $voucherId, []);
            $this->logger?->info('arca.reconcile.result', ['voucher' => $voucherId, 'state' => 'Pendiente', 'environment' => $this->environment]);
        }

        return $this->get($voucherId);
    }

    public function retry(int $voucherId, int $actor): array
    {
        $this->assertEmissionEnabled();
        $voucher = $this->fiscal->voucher($voucherId);
        if ($voucher === null) {
            throw new HttpException('NOT_FOUND', 'El comprobante fiscal no existe.', 404);
        }
        if ((string) $voucher['estado_fiscal'] !== 'ErrorComunicacion') {
            throw new HttpException('CONFLICT', 'Solo se reintentan comprobantes con error de comunicacion previo al envio.', 409);
        }
        $this->audit($actor, 'Reintentar', $voucherId, []);

        return $this->send($voucherId, $actor);
    }

    private function createAndSend(array $voucherData, array $ivaRows, int $actor): array
    {
        $this->pdo->beginTransaction();
        try {
            $id = $this->fiscal->createVoucher($voucherData + ['estado_fiscal' => 'Pendiente', 'id_usuario' => $actor]);
            foreach ($ivaRows as $row) {
                $this->fiscal->addIva(['voucher' => $id, 'rate' => $row['id'], 'base' => $row['base'], 'amount' => $row['importe']]);
            }
            $this->audit($actor, 'Crear', $id, ['venta' => $voucherData['id_venta'], 'tipo' => $voucherData['id_tipo_comprobante']]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->send($id, $actor);
    }

    private function send(int $id, int $actor): array
    {
        $voucher = $this->fiscal->voucher($id);
        if ($voucher === null) {
            throw new HttpException('NOT_FOUND', 'El comprobante fiscal no existe.', 404);
        }

        $correlation = bin2hex(random_bytes(16));
        $startedAt = microtime(true);
        $this->logger?->info('arca.emit.start', [
            'correlation' => $correlation,
            'voucher' => $id,
            'point' => (int) $voucher['punto_venta_numero'],
            'type' => (int) $voucher['id_tipo_comprobante'],
            'environment' => $this->environment,
        ]);
        $lock = 'arca_' . (int) $voucher['punto_venta_numero'] . '_' . (int) $voucher['id_tipo_comprobante'];
        if (!$this->acquireLock($lock)) {
            throw new HttpException('CONFLICT', 'Hay otra emision en curso para el mismo punto de venta y tipo.', 409);
        }

        try {
            if ($voucher['numero'] === null) {
                $attempt = $this->attemptStart($id, 'ultimo_autorizado', $correlation);
                try {
                    $last = $this->gateway->lastAuthorized((int) $voucher['punto_venta_numero'], (int) $voucher['id_tipo_comprobante']);
                    $voucher['numero'] = $last + 1;
                    $this->attemptFinish($attempt, 'completado', 'ultimo=' . $last);
                } catch (ArcaCommunicationException $exception) {
                    $this->attemptFinish($attempt, 'error', $exception->getCategory(), $exception->getMessage());
                    $this->fiscal->updateVoucher($id, ['estado_fiscal' => 'ErrorComunicacion', 'resultado_arca' => null]);
                    $this->audit($actor, 'ErrorComunicacion', $id, ['error' => $exception->getMessage(), 'correlacion' => $correlation]);
                    $this->logEmission($correlation, $id, 'ErrorComunicacion', $startedAt, $exception->getCategory());

                    return $this->get($id);
                }
            }

            $numero = (int) $voucher['numero'];
            if ($this->fiscal->numberExists((int) $voucher['id_punto_venta'], (int) $voucher['id_tipo_comprobante'], $numero, $id)) {
                throw new HttpException('CONFLICT', 'El numero ' . $numero . ' ya esta asignado a otro comprobante local para el punto de venta y tipo. Revise la numeracion contra ARCA.', 409);
            }
            try {
                $this->fiscal->updateVoucher($id, ['numero' => $numero, 'estado_fiscal' => 'Enviando']);
            } catch (\PDOException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    throw new HttpException('CONFLICT', 'El numero ' . $numero . ' ya esta asignado a otro comprobante local para el punto de venta y tipo. Revise la numeracion contra ARCA.', 409);
                }
                throw $exception;
            }
            $request = (new ArcaRequestMapper())->fromComprobante($voucher, $this->fiscal->iva($id), $this->cuit);
            $attempt = $this->attemptStart($id, 'solicitar_cae', $correlation);
            try {
                $result = $this->gateway->requestCae($request);
                $this->attemptFinish($attempt, 'completado', (string) ($result['resultado'] ?? ''), null, ['numero' => (int) $voucher['numero']]);
            } catch (ArcaCommunicationException $exception) {
                $this->attemptFinish($attempt, 'error', $exception->getCategory(), $exception->getMessage());
                $state = $exception->isPreSend() ? 'ErrorComunicacion' : 'ResultadoIncierto';
                $this->fiscal->updateVoucher($id, ['estado_fiscal' => $state, 'resultado_arca' => null]);
                $this->audit($actor, $state, $id, ['error' => $exception->getMessage(), 'correlacion' => $correlation]);
                $this->logEmission($correlation, $id, $state, $startedAt, $exception->getCategory());

                return $this->get($id);
            }
        } finally {
            $this->releaseLock($lock);
        }

        if (($result['resultado'] ?? '') === 'A' && ($result['cae'] ?? null) !== null) {
            $this->fiscal->updateVoucher($id, [
                'estado_fiscal' => 'Autorizado',
                'cae' => $result['cae'],
                'cae_fch_vto' => $result['cae_fch_vto'],
                'resultado_arca' => 'A',
                'observaciones' => $result['observaciones'] !== [] ? json_encode($result['observaciones']) : null,
                'errores' => null,
            ]);
            $this->fiscal->markSaleInvoiced((int) $voucher['id_venta']);
            $this->audit($actor, 'Autorizar', $id, ['cae' => $result['cae'], 'correlacion' => $correlation]);
            $this->logEmission($correlation, $id, 'Autorizado', $startedAt, 'A');
        } else {
            $this->fiscal->updateVoucher($id, [
                'estado_fiscal' => 'Rechazado',
                'numero' => null,
                'resultado_arca' => 'R',
                'observaciones' => $result['observaciones'] !== [] ? json_encode($result['observaciones']) : null,
                'errores' => $result['errores'] !== [] ? json_encode($result['errores']) : null,
            ]);
            $this->audit($actor, 'Rechazar', $id, ['errores' => $result['errores'], 'correlacion' => $correlation]);
            $this->logEmission($correlation, $id, 'Rechazado', $startedAt, 'R');
        }

        return $this->get($id);
    }

    private function acquireLock(string $name): bool
    {
        $query = $this->pdo->prepare('SELECT GET_LOCK(:name, 5)');
        $query->execute(['name' => $name]);

        return (int) $query->fetchColumn() === 1;
    }

    private function releaseLock(string $name): void
    {
        $this->pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $name]);
    }

    private function attemptStart(int $voucherId, string $operation, string $correlation): ?int
    {
        if ($this->solicitudes === null) {
            return null;
        }

        return $this->solicitudes->start($voucherId, $operation, $this->environment, self::ADAPTER_VERSION, $correlation);
    }

    private function attemptFinish(?int $attempt, string $state, ?string $code = null, ?string $message = null, ?array $detail = null): void
    {
        if ($attempt === null || $this->solicitudes === null) {
            return;
        }

        $this->solicitudes->finish($attempt, $state, $code, $message, $detail);
    }

    private function logEmission(string $correlation, int $voucherId, string $state, float $startedAt, ?string $code = null): void
    {
        $this->logger?->info('arca.emit.result', [
            'correlation' => $correlation,
            'voucher' => $voucherId,
            'state' => $state,
            'code' => $code,
            'environment' => $this->environment,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function assertEmissionEnabled(): void
    {
        if (!$this->emissionEnabled) {
            throw new HttpException('CONFLICT', 'La emision de comprobantes esta deshabilitada hasta completar la habilitacion (ARCA_ALLOW_EMISSION).', 409);
        }
    }

    private function requireEmisor(): array
    {
        $emisor = $this->emisor?->get();
        if ($this->emisor === null || !$this->emisor->isComplete($emisor)) {
            throw new HttpException('CONFLICT', 'Complete los datos del emisor antes de emitir comprobantes.', 409);
        }
        if ((int) $emisor['cuit'] !== $this->cuit) {
            throw new HttpException('CONFLICT', 'El CUIT del emisor no coincide con el CUIT configurado para ARCA.', 409);
        }

        return $emisor;
    }

    private function emisorSnapshot(array $emisor): array
    {
        return [
            'emisor_razon_social' => (string) $emisor['razon_social'],
            'emisor_cuit' => (string) $emisor['cuit'],
            'emisor_domicilio' => (string) $emisor['domicilio'],
            'emisor_condicion_iva' => (int) $emisor['condicion_iva'],
            'emisor_ingresos_brutos' => $emisor['ingresos_brutos'] !== null ? (string) $emisor['ingresos_brutos'] : null,
            'emisor_inicio_actividades' => $emisor['inicio_actividades'] !== null ? (string) $emisor['inicio_actividades'] : null,
        ];
    }

    private function requireType(mixed $value, array $allowed, string $label): array
    {
        if (!is_numeric($value)) {
            throw new HttpException('VALIDATION_ERROR', 'El tipo de comprobante es obligatorio.', 422, ['field' => 'id_tipo_comprobante']);
        }
        $type = $this->fiscal->voucherType((int) $value);
        if ($type === null || !in_array((int) $type['id_tipo_comprobante'], $allowed, true)) {
            throw new HttpException('VALIDATION_ERROR', 'El tipo de comprobante no es valido para una ' . $label . '.', 422, ['field' => 'id_tipo_comprobante']);
        }

        return $type;
    }

    private function requirePointOfSale(mixed $value): array
    {
        if (!is_numeric($value)) {
            throw new HttpException('VALIDATION_ERROR', 'El punto de venta es obligatorio.', 422, ['field' => 'id_punto_venta']);
        }
        $point = $this->fiscal->pointOfSale((int) $value);
        if ($point === null || !(int) $point['activo']) {
            throw new HttpException('VALIDATION_ERROR', 'El punto de venta no existe o esta inactivo.', 422, ['field' => 'id_punto_venta']);
        }

        return $point;
    }

    private function receptorFromSale(array $sale, array $type): array
    {
        $client = $this->clients->find((int) $sale['id_cliente']);
        if ($client === null) {
            throw new HttpException('NOT_FOUND', 'El cliente de la venta no existe.', 404);
        }

        return $this->validator->receptor($client, $type, $this->condicionEmisor);
    }

    private function audit(int $actor, string $action, int $id, array $values): void
    {
        $query = $this->pdo->prepare('INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_nuevos) VALUES (:user_id, :action, :entity, :entity_id, :values)');
        $query->execute(['user_id' => $actor, 'action' => $action, 'entity' => 'comprobantes_fiscales', 'entity_id' => $id, 'values' => json_encode($values, JSON_THROW_ON_ERROR)]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
