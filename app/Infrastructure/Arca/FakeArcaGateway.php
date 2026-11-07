<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

use Closure;

final class FakeArcaGateway implements ArcaGateway
{
    private bool $failNextRequest = false;
    private bool $failNextLastAuthorized = false;
    private bool $rejectNext = false;
    private bool $observeNext = false;
    private bool $consultAuthorized = false;
    private int $lastConsultCae = 1;

    public function __construct(private readonly ?Closure $lastAuthorizedResolver = null)
    {
    }

    public function failNextRequestCae(): void
    {
        $this->failNextRequest = true;
    }

    public function failNextLastAuthorized(): void
    {
        $this->failNextLastAuthorized = true;
    }

    public function rejectNext(): void
    {
        $this->rejectNext = true;
    }

    public function observeNext(): void
    {
        $this->observeNext = true;
    }

    public function consultWillReturnAuthorized(int $cae = 70000000000001): void
    {
        $this->consultAuthorized = true;
        $this->lastConsultCae = $cae;
    }

    public function lastAuthorized(int $pointOfSale, int $voucherType): int
    {
        if ($this->failNextLastAuthorized) {
            $this->failNextLastAuthorized = false;
            throw new ArcaCommunicationException('Fallo de autenticacion simulado.', ArcaCommunicationException::CATEGORY_AUTH);
        }

        if ($this->lastAuthorizedResolver !== null) {
            return (int) ($this->lastAuthorizedResolver)($pointOfSale, $voucherType);
        }

        return 0;
    }

    public function dummy(): array
    {
        return ['app_server' => 'OK', 'db_server' => 'OK', 'auth_server' => 'OK'];
    }

    public function authorization(): array
    {
        return [
            'service' => 'wsfe',
            'certificate_dn' => 'SERIALNUMBER=CUIT 20111111112, CN=mock',
            'represented_cuits' => [20111111112],
            'expires_at' => time() + 3600,
        ];
    }

    public function pointsOfSale(): array
    {
        return [['numero' => 1, 'emision_tipo' => 'CAE', 'bloqueado' => 'N', 'fecha_baja' => '']];
    }

    public function requestCae(array $request): array
    {
        if ($this->failNextRequest) {
            $this->failNextRequest = false;
            throw new ArcaCommunicationException('Fallo de comunicacion simulado.');
        }

        if ($this->rejectNext) {
            $this->rejectNext = false;

            return [
                'resultado' => 'R',
                'cae' => null,
                'cae_fch_vto' => null,
                'observaciones' => [],
                'errores' => [['code' => 10016, 'msg' => 'Comprobante rechazado (mock)']],
            ];
        }

        $observations = [];
        if ($this->observeNext) {
            $observations[] = ['code' => 10018, 'msg' => 'Observacion simulada (mock)'];
            $this->observeNext = false;
        }

        $number = (int) $request['cbte_desde'];

        return [
            'resultado' => 'A',
            'cae' => str_pad((string) (70000000000000 + $number), 14, '0', STR_PAD_LEFT),
            'cae_fch_vto' => date('Y-m-d', strtotime('+10 days')),
            'observaciones' => $observations,
            'errores' => [],
        ];
    }

    public function consult(int $pointOfSale, int $voucherType, int $number): ?array
    {
        if (!$this->consultAuthorized) {
            return null;
        }

        return [
            'resultado' => 'A',
            'cae' => (string) $this->lastConsultCae,
            'cae_fch_vto' => date('Y-m-d', strtotime('+10 days')),
            'observaciones' => [],
            'errores' => [],
        ];
    }
}
