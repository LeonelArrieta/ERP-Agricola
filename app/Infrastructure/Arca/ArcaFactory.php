<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

use App\Core\Config;
use App\Repositories\FiscalRepository;

final class ArcaFactory
{
    public static function create(Config $config, FiscalRepository $fiscal): ArcaGateway
    {
        $mode = (string) $config->get('arca_mode');
        if ($mode === 'mock') {
            return new FakeArcaGateway(
                static fn (int $point, int $type): int => $fiscal->maxAuthorizedNumber($point, $type)
            );
        }

        $settings = (new ArcaConfigValidator($config))->settingsOrFail();
        $transport = new SoapTransport((int) $settings['timeout']);
        $authenticator = new ArcaAuthenticator(
            new WsaaClient($transport, (string) $settings['wsaa']),
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'arca',
            (bool) $settings['production'] ? 'produccion' : 'homologacion',
            (string) $settings['cert'],
            (string) $settings['key'],
        );

        return new WsfeV1Client(
            $transport,
            $authenticator,
            new ArcaResponseMapper(),
            (string) $settings['wsfe'],
            (int) $settings['cuit'],
        );
    }
}
