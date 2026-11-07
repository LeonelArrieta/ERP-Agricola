<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

final class SoapTransport
{
    public function __construct(private readonly int $timeout = 30)
    {
    }

    public function call(string $endpoint, string $action, string $bodyXml): string
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            . '<soap:Body>' . $bodyXml . '</soap:Body></soap:Envelope>';

        $headers = ['Content-Type: text/xml; charset=utf-8'];
        $headers[] = 'SOAPAction: "' . $action . '"';

        $handle = curl_init($endpoint);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);
        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($response === false || $error !== '') {
            throw new ArcaCommunicationException('Error de comunicacion con ARCA: ' . $error);
        }
        if ($status === 0 || $status >= 500) {
            $fault = $this->faultMessage((string) $response);
            throw new ArcaCommunicationException('ARCA respondio HTTP ' . $status . ($fault === '' ? '' : ': ' . $fault));
        }

        return (string) $response;
    }

    private function faultMessage(string $response): string
    {
        if ($response === '') {
            return '';
        }
        $document = new \DOMDocument();
        if (!@$document->loadXML($response)) {
            return '';
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $fault = $xpath->query('//soap:Fault/faultstring')->item(0) ?? $xpath->query('//faultstring')->item(0);
        if ($fault === null) {
            return '';
        }

        $message = trim(preg_replace('/\s+/', ' ', $fault->textContent) ?? '');

        return mb_substr($message, 0, 300);
    }
}
