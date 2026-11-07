<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Infrastructure\Arca\ArcaConfigValidator;
use App\Infrastructure\Arca\ArcaGateway;
use App\Repositories\FiscalRepository;
use Closure;
use Throwable;

final class ArcaDiagnosticsService
{
    public function __construct(
        private readonly Config $config,
        private readonly Closure $gatewayFactory,
        private readonly FiscalRepository $fiscal,
    ) {
    }

    /**
     * @return array{mode:string,ready:bool,checks:array<int,array{key:string,label:string,status:string,detail:string}>}
     */
    public function run(): array
    {
        $checks = [];
        $mode = (string) $this->config->get('arca_mode');
        $report = (new ArcaConfigValidator($this->config))->inspect();

        if ($report['errors'] !== []) {
            $checks[] = $this->check('config', 'Configuracion ARCA', 'fail', implode(' ', $report['errors']));
        } elseif ($report['warnings'] !== []) {
            $checks[] = $this->check('config', 'Configuracion ARCA', 'warn', implode(' ', $report['warnings']));
        } else {
            $checks[] = $this->check('config', 'Configuracion ARCA', 'ok', 'Configuracion completa para el modo ' . $mode . '.');
        }

        if ($mode === 'mock') {
            $checks[] = $this->check('mock', 'Modo mock', 'ok', 'Emision simulada sin red.');

            return ['mode' => $mode, 'ready' => true, 'checks' => $checks];
        }

        $settings = $report['settings'];
        if (is_array($settings['certificate'] ?? null)) {
            $certificate = $settings['certificate'];
            $checks[] = $this->check(
                'certificate',
                'Certificado y clave',
                $certificate['key_matches'] ? 'ok' : 'fail',
                'CN ' . $certificate['common_name'] . ', emisor ' . $certificate['issuer'] . ', vence ' . ($certificate['expires_at'] ?? 'desconocido') . ' (' . $certificate['days_left'] . ' dias).'
            );
        }

        try {
            $gateway = ($this->gatewayFactory)();
        } catch (Throwable $exception) {
            $checks[] = $this->check('gateway', 'Cliente ARCA', 'fail', $exception->getMessage());

            return ['mode' => $mode, 'ready' => false, 'checks' => $checks];
        }

        $checks[] = $this->dummyCheck($gateway);
        $checks[] = $this->authorizationCheck($gateway, (int) ($settings['cuit'] ?? 0));
        $production = (bool) ($settings['production'] ?? false);
        [$pointsCheck, $pointsEmpty] = $this->pointsOfSaleCheck($gateway, $production);
        $checks[] = $pointsCheck;

        $point = (int) ($settings['punto_venta'] ?? 1);
        foreach ([6 => 'Factura B', 11 => 'Factura C'] as $type => $label) {
            $checks[] = $this->lastAuthorizedCheck($gateway, $point, $type, $label, $pointsEmpty, $production);
        }

        $ready = !in_array('fail', array_column($checks, 'status'), true);

        return ['mode' => $mode, 'ready' => $ready, 'checks' => $checks];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string}
     */
    private function dummyCheck(ArcaGateway $gateway): array
    {
        try {
            $dummy = $gateway->dummy();
            $ok = ($dummy['app_server'] ?? '') === 'OK' && ($dummy['db_server'] ?? '') === 'OK';
            $detail = 'AppServer ' . ($dummy['app_server'] ?: '?')
                . ', DbServer ' . ($dummy['db_server'] ?: '?')
                . ', AuthServer ' . ($dummy['auth_server'] ?: '?') . '.';

            return $this->check('dummy', 'Servicio WSFEv1 (FEDummy)', $ok ? 'ok' : 'fail', $detail);
        } catch (Throwable $exception) {
            return $this->check('dummy', 'Servicio WSFEv1 (FEDummy)', 'fail', $exception->getMessage());
        }
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string}
     */
    private function authorizationCheck(ArcaGateway $gateway, int $cuit): array
    {
        try {
            $auth = $gateway->authorization();
            $cuits = array_map(static fn (mixed $value): int => (int) $value, $auth['represented_cuits'] ?? []);
            $authorized = in_array($cuit, $cuits, true);
            $detail = 'Servicio ' . ($auth['service'] !== '' ? $auth['service'] : '?')
                . ', certificado ' . ($auth['certificate_dn'] !== '' ? $auth['certificate_dn'] : '?')
                . ', CUIT autorizados: ' . ($cuits === [] ? 'ninguno' : implode(', ', $cuits)) . '.';
            if (!$authorized) {
                $detail .= ' ARCA_CUIT ' . $cuit . ' no figura entre los CUIT representados del TA; revise la autorizacion en WSASS.';
            }

            return $this->check('authorization', 'CUIT representado (TA)', $authorized ? 'ok' : 'fail', $detail);
        } catch (Throwable $exception) {
            return $this->check('authorization', 'CUIT representado (TA)', 'fail', $exception->getMessage());
        }
    }

    /**
     * @return array{0:array{key:string,label:string,status:string,detail:string},1:bool}
     */
    private function pointsOfSaleCheck(ArcaGateway $gateway, bool $production): array
    {
        try {
            $remote = $gateway->pointsOfSale();
            $remoteNumbers = array_map(static fn (array $item): int => (int) $item['numero'], $remote);
            $localNumbers = array_map(static fn (array $item): int => (int) $item['numero'], $this->fiscal->pointOfSaleOptions());
            $result = self::classifyPoints($production, $remoteNumbers, $localNumbers, null);
        } catch (Throwable $exception) {
            $result = self::classifyPoints($production, [], [], $exception->getMessage());
        }

        return [$this->check('points', 'WSAA y puntos de venta', $result['status'], $result['detail']), $result['empty']];
    }

    /**
     * Clasifica el estado de los puntos de venta sin presuponer el ambiente.
     *
     * @param int[] $remoteNumbers
     * @param int[] $localNumbers
     * @return array{status:string,detail:string,empty:bool}
     */
    public static function classifyPoints(bool $production, array $remoteNumbers, array $localNumbers, ?string $error): array
    {
        if ($error !== null) {
            if (stripos($error, 'no autorizado') !== false) {
                return [
                    'status' => 'fail',
                    'detail' => $error . ' Falta crear la autorizacion al servicio wsfe en WSASS.',
                    'empty' => false,
                ];
            }
            if (stripos($error, 'sin resultados') !== false || str_contains($error, '602')) {
                $next = $production
                    ? 'En produccion el punto debe estar habilitado como RECE/Web Services.'
                    : 'En homologacion la emision funciona sin registro previo (verificado); en produccion el punto debe estar habilitado como RECE/Web Services.';

                return ['status' => $production ? 'fail' : 'warn', 'detail' => $error . ' ' . $next, 'empty' => true];
            }

            return ['status' => 'fail', 'detail' => $error, 'empty' => false];
        }

        $missing = array_values(array_diff($localNumbers, $remoteNumbers));
        $detail = 'ARCA: ' . ($remoteNumbers === [] ? 'sin puntos de venta' : implode(', ', $remoteNumbers))
            . '. Locales: ' . ($localNumbers === [] ? 'ninguno' : implode(', ', $localNumbers)) . '.';
        if ($missing !== []) {
            $detail .= ' Faltan en ARCA: ' . implode(', ', $missing) . '.';
        }

        return ['status' => $missing === [] ? 'ok' : 'warn', 'detail' => $detail, 'empty' => false];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string}
     */
    private function lastAuthorizedCheck(ArcaGateway $gateway, int $point, int $type, string $label, bool $pointsEmpty, bool $production): array
    {
        try {
            $last = $gateway->lastAuthorized($point, $type);
            $result = self::classifyLastAuthorized($production, $pointsEmpty, $point, $last);

            return $this->check('last_' . $type, 'Ultimo autorizado ' . $label, $result['status'], $result['detail']);
        } catch (Throwable $exception) {
            return $this->check('last_' . $type, 'Ultimo autorizado ' . $label, 'fail', $exception->getMessage());
        }
    }

    /**
     * @return array{status:string,detail:string}
     */
    public static function classifyLastAuthorized(bool $production, bool $pointsEmpty, int $point, int $last): array
    {
        if (!$pointsEmpty) {
            return ['status' => 'ok', 'detail' => 'PV ' . $point . ': ultimo ' . $last . '.'];
        }

        $next = $production
            ? 'En produccion la emision fallaria hasta habilitar el punto de venta.'
            : 'En homologacion la emision funciona sin registro previo.';

        return ['status' => $production ? 'fail' : 'warn', 'detail' => 'PV ' . $point . ' no registrado en ARCA; ultimo ' . $last . '. ' . $next];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string}
     */
    private function check(string $key, string $label, string $status, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }
}
