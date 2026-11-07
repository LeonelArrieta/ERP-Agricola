<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

use DOMDocument;
use DOMXPath;

final class WsaaClient
{
    public function __construct(private readonly SoapTransport $transport, private readonly string $endpoint)
    {
    }

    public function login(string $service, string $certPath, string $keyPath): array
    {
        if (!is_file($certPath) || !is_file($keyPath)) {
            throw new ArcaCommunicationException('Certificado o clave privada de ARCA no configurados.', ArcaCommunicationException::CATEGORY_CONFIG);
        }

        $tra = $this->buildTra($service);
        $cms = $this->sign($tra, $certPath, $keyPath);
        $body = '<loginCms xmlns="http://wsaa.view.sua.dvadac.desein.afip.gov">'
            . '<in0>' . htmlspecialchars($cms, ENT_XML1) . '</in0>'
            . '</loginCms>';

        $response = $this->transport->call($this->endpoint, '', $body);

        return self::parseResponse($response);
    }

    /**
     * Parsea la respuesta SOAP de WSAA y los metadatos del token (sin exponer secretos).
     *
     * @return array{token:string,sign:string,expires_at:int,service:string,certificate_dn:string,represented_cuits:int[]}
     */
    public static function parseResponse(string $response): array
    {
        $document = new DOMDocument();
        if (!@$document->loadXML($response)) {
            throw new ArcaCommunicationException('Respuesta WSAA invalida.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('wsaa', 'http://wsaa.view.sua.dvadac.desein.afip.gov');
        $fault = $xpath->query('//soap:Fault/faultstring')->item(0);
        if ($fault !== null) {
            throw new ArcaCommunicationException('WSAA: ' . trim($fault->textContent), ArcaCommunicationException::CATEGORY_AUTH);
        }
        $return = $xpath->query('//wsaa:loginCmsReturn')->item(0);
        if ($return === null) {
            throw new ArcaCommunicationException('WSAA no devolvio el ticket de acceso.');
        }

        $ticketDocument = new DOMDocument();
        if (!@$ticketDocument->loadXML(trim($return->textContent))) {
            throw new ArcaCommunicationException('Ticket de acceso WSAA invalido.');
        }
        $ticketXpath = new DOMXPath($ticketDocument);
        $token = $ticketXpath->query('//token')->item(0)?->textContent;
        $sign = $ticketXpath->query('//sign')->item(0)?->textContent;
        $expiration = $ticketXpath->query('//expirationTime')->item(0)?->textContent;
        if ($token === null || $sign === null || $expiration === null) {
            throw new ArcaCommunicationException('Ticket de acceso WSAA incompleto.');
        }

        $metadata = self::tokenMetadata($token);

        return [
            'token' => $token,
            'sign' => $sign,
            'expires_at' => strtotime($expiration) ?: (time() + 3600),
            'service' => $metadata['service'],
            'certificate_dn' => $metadata['certificate_dn'],
            'represented_cuits' => $metadata['represented_cuits'],
        ];
    }

    /**
     * @return array{service:string,certificate_dn:string,represented_cuits:int[]}
     */
    public static function tokenMetadata(string $token): array
    {
        $empty = ['service' => '', 'certificate_dn' => '', 'represented_cuits' => []];
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return $empty;
        }

        $document = new DOMDocument();
        if (!@$document->loadXML($decoded)) {
            return $empty;
        }

        $xpath = new DOMXPath($document);
        $login = $xpath->query('//*[local-name()="login"]')->item(0);
        $cuits = [];
        foreach ($xpath->query('//*[local-name()="relation"]') as $node) {
            $key = trim($node->getAttribute('key'));
            if ($key !== '' && ctype_digit($key)) {
                $cuits[] = (int) $key;
            }
        }

        return [
            'service' => $login === null ? '' : trim($login->getAttribute('service')),
            'certificate_dn' => $login === null ? '' : trim($login->getAttribute('uid')),
            'represented_cuits' => array_values(array_unique($cuits)),
        ];
    }

    private function buildTra(string $service): string
    {
        $generation = time() - 60;
        $expiration = time() + 600;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0">'
            . '<header>'
            . '<uniqueId>' . $generation . '</uniqueId>'
            . '<generationTime>' . date('c', $generation) . '</generationTime>'
            . '<expirationTime>' . date('c', $expiration) . '</expirationTime>'
            . '</header>'
            . '<service>' . htmlspecialchars($service, ENT_XML1) . '</service>'
            . '</loginTicketRequest>';
    }

    private function sign(string $tra, string $certPath, string $keyPath): string
    {
        $input = tempnam(sys_get_temp_dir(), 'tra');
        $output = tempnam(sys_get_temp_dir(), 'cms');
        if ($input === false || $output === false) {
            throw new ArcaCommunicationException('No se pudo preparar la firma WSAA.');
        }
        file_put_contents($input, $tra);

        try {
            $signed = openssl_pkcs7_sign($input, $output, 'file://' . $certPath, 'file://' . $keyPath, [], PKCS7_BINARY);
            if (!$signed) {
                throw new ArcaCommunicationException('No se pudo firmar el TRA de WSAA.');
            }
            $contents = file_get_contents($output);
            if ($contents === false) {
                throw new ArcaCommunicationException('No se pudo leer el CMS de WSAA.');
            }
        } finally {
            @unlink($input);
            @unlink($output);
        }

        return base64_encode($this->extractDer($contents));
    }

    private function extractDer(string $contents): string
    {
        if (str_starts_with($contents, "\x30")) {
            return $contents;
        }

        if (preg_match('/-----BEGIN PKCS7-----(.*?)-----END PKCS7-----/s', $contents, $matches) === 1) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $matches[1]) ?? '', true);
            if ($decoded === false || $decoded === '') {
                throw new ArcaCommunicationException('CMS de WSAA invalido.');
            }

            return $decoded;
        }

        $separator = strpos($contents, "\r\n\r\n");
        $offset = 4;
        if ($separator === false) {
            $separator = strpos($contents, "\n\n");
            $offset = 2;
        }
        if ($separator === false) {
            throw new ArcaCommunicationException('CMS de WSAA invalido.');
        }

        $decoded = base64_decode(preg_replace('/\s+/', '', substr($contents, $separator + $offset)) ?? '', true);
        if ($decoded === false || $decoded === '') {
            throw new ArcaCommunicationException('CMS de WSAA invalido.');
        }

        return $decoded;
    }
}
