<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

final class ArcaEndpoints
{
    public const WSAA_HOMOLOGACION = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
    public const WSAA_PRODUCCION = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
    public const WSFE_HOMOLOGACION = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';
    public const WSFE_PRODUCCION = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';

    public static function wsaa(bool $production, string $override = ''): string
    {
        return self::resolve($production, $override, self::WSAA_HOMOLOGACION, self::WSAA_PRODUCCION);
    }

    public static function wsfe(bool $production, string $override = ''): string
    {
        return self::resolve($production, $override, self::WSFE_HOMOLOGACION, self::WSFE_PRODUCCION);
    }

    private static function resolve(bool $production, string $override, string $homologacion, string $produccion): string
    {
        $override = trim($override);
        if ($override !== '') {
            if (!str_starts_with($override, 'https://')) {
                throw new ArcaConfigurationException('Los endpoints de ARCA deben usar HTTPS.');
            }

            return $override;
        }

        return $production ? $produccion : $homologacion;
    }
}
