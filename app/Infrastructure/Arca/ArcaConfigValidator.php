<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

use App\Core\Config;

final class ArcaConfigValidator
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{errors:string[],warnings:string[],info:string[],settings:array<string,mixed>}
     */
    public function inspect(): array
    {
        $mode = (string) $this->config->get('arca_mode');
        $production = $mode === 'produccion';
        $cuit = trim((string) $this->config->get('arca_cuit'));
        $certPath = trim((string) $this->config->get('arca_cert_path'));
        $keyPath = trim((string) $this->config->get('arca_key_path'));
        $timeout = (int) $this->config->get('arca_timeout');
        $errors = [];
        $warnings = [];
        $info = [];

        if (!in_array($mode, ['mock', 'homologacion', 'produccion'], true)) {
            $errors[] = 'ARCA_MODE invalido: use mock, homologacion o produccion.';
        }

        if ($mode === 'mock') {
            $info[] = 'Modo mock: no se valida certificado ni endpoints.';

            return ['errors' => $errors, 'warnings' => $warnings, 'info' => $info, 'settings' => ['mode' => 'mock']];
        }

        if (!preg_match('/^\d{11}$/', $cuit)) {
            $errors[] = 'ARCA_CUIT debe tener 11 digitos.';
        }
        if ($timeout < 1 || $timeout > 300) {
            $warnings[] = 'ARCA_TIMEOUT fuera de rango (1-300); se usa 30 segundos.';
            $timeout = 30;
        }

        $certificate = null;
        if ($certPath === '' || !is_file($certPath) || !is_readable($certPath)) {
            $errors[] = 'ARCA_CERT_PATH no apunta a un certificado legible.';
        }
        if ($keyPath === '' || !is_file($keyPath) || !is_readable($keyPath)) {
            $errors[] = 'ARCA_KEY_PATH no apunta a una clave privada legible.';
        }

        if ($certPath !== '' && is_file($certPath) && is_readable($certPath)) {
            $certificate = $this->inspectCertificate($certPath, $keyPath, $cuit, $production, $errors, $warnings, $info);
        }

        $wsaa = ArcaEndpoints::WSAA_HOMOLOGACION;
        $wsfe = ArcaEndpoints::WSFE_HOMOLOGACION;
        try {
            $wsaa = ArcaEndpoints::wsaa($production, (string) $this->config->get('arca_wsaa_url'));
            $wsfe = ArcaEndpoints::wsfe($production, (string) $this->config->get('arca_wsfe_url'));
        } catch (ArcaConfigurationException $exception) {
            $errors[] = $exception->getMessage();
            $wsaa = ArcaEndpoints::wsaa($production);
            $wsfe = ArcaEndpoints::wsfe($production);
        }

        $settings = [
            'mode' => $mode,
            'production' => $production,
            'cuit' => (int) $cuit,
            'cert' => $certPath,
            'key' => $keyPath,
            'wsaa' => $wsaa,
            'wsfe' => $wsfe,            'timeout' => $timeout,
            'condicion_emisor' => (int) $this->config->get('arca_condicion_emisor'),
            'punto_venta' => (int) $this->config->get('arca_punto_venta_default'),
            'allow_emission' => (bool) $this->config->get('arca_allow_emission'),
            'wsdl_version' => (string) $this->config->get('arca_wsdl_version'),
            'certificate' => $certificate,
        ];

        return ['errors' => $errors, 'warnings' => $warnings, 'info' => $info, 'settings' => $settings];
    }

    /**
     * @return array<string,mixed>
     */
    public function settingsOrFail(): array
    {
        $report = $this->inspect();
        if ($report['errors'] !== []) {
            throw new ArcaConfigurationException(implode(' ', $report['errors']));
        }

        return $report['settings'];
    }

    /**
     * @param string[] $errors
     * @param string[] $warnings
     * @param string[] $info
     * @return array<string,mixed>|null
     */
    private function inspectCertificate(string $certPath, string $keyPath, string $cuit, bool $production, array &$errors, array &$warnings, array &$info): ?array
    {
        $certificate = @file_get_contents($certPath);
        if ($certificate === false) {
            $errors[] = 'No se pudo leer el certificado de ARCA.';

            return null;
        }

        $parsed = @openssl_x509_parse($certificate);
        if (!is_array($parsed)) {
            $errors[] = 'El certificado de ARCA no es un X.509 valido en formato PEM.';

            return null;
        }

        $subject = $parsed['subject']['serialNumber'] ?? '';
        $commonName = (string) ($parsed['subject']['CN'] ?? '');
        $issuerCommonName = (string) ($parsed['issuer']['CN'] ?? '');
        $expiresAt = (int) ($parsed['validTo_time_t'] ?? 0);
        $daysLeft = (int) floor(($expiresAt - time()) / 86400);

        $looksHomologacion = stripos($issuerCommonName, 'test') !== false;
        if ($production && $looksHomologacion) {
            $errors[] = 'El certificado parece de homologacion (emisor ' . $issuerCommonName . '); no sirve para produccion.';
        } elseif (!$production && !$looksHomologacion) {
            $errors[] = 'El certificado parece de produccion (emisor ' . $issuerCommonName . '); WSAA de homologacion lo rechazara. Genere el certificado en WSASS para testing.';
        }

        if ($expiresAt > 0 && $expiresAt < time()) {
            $errors[] = 'El certificado de ARCA esta vencido.';
        } elseif ($daysLeft <= 30) {
            $warnings[] = 'El certificado de ARCA vence en ' . $daysLeft . ' dias.';
        }

        if (preg_match('/(\d{11})/', (string) $subject, $matches) === 1) {
            if ($cuit !== '' && $matches[1] !== $cuit) {
                $errors[] = 'El CUIT del certificado (' . $matches[1] . ') no coincide con ARCA_CUIT (' . $cuit . ').';
            }
        } else {
            $warnings[] = 'No se pudo leer el CUIT del certificado (serialNumber).';
        }

        $keyMatches = false;
        if ($keyPath !== '' && is_file($keyPath) && is_readable($keyPath)) {
            $privateKey = @file_get_contents($keyPath);
            $resource = $privateKey === false ? false : @openssl_pkey_get_private($privateKey);
            if ($resource === false) {
                $errors[] = 'La clave privada de ARCA no se pudo interpretar (revise formato o passphrase).';
            } else {
                $keyMatches = @openssl_x509_check_private_key($certificate, $resource);
                if (!$keyMatches) {
                    $errors[] = 'La clave privada no corresponde al certificado de ARCA.';
                }
                if (DIRECTORY_SEPARATOR === '/' && (fileperms($keyPath) & 0x0004) !== 0) {
                    $warnings[] = 'La clave privada es legible por otros usuarios; aplique chmod 600.';
                }
            }
        }

        $info[] = 'Certificado: CN=' . $commonName . ', CUIT=' . $subject . ', emisor ' . $issuerCommonName . ', vence ' . ($expiresAt > 0 ? date('Y-m-d', $expiresAt) : 'desconocido') . '.';

        return [
            'common_name' => $commonName,
            'cuit' => (string) $subject,
            'issuer' => $issuerCommonName,
            'expires_at' => $expiresAt > 0 ? date('Y-m-d', $expiresAt) : null,
            'days_left' => $daysLeft,
            'key_matches' => $keyMatches,
        ];
    }
}
