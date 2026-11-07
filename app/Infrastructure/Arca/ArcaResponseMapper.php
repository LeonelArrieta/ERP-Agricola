<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

use DOMDocument;
use DOMXPath;

final class ArcaResponseMapper
{
    private const NS = 'http://ar.gov.afip.dif.FEV1/';

    public function ultimoAutorizado(string $xml): int
    {
        $xpath = $this->xpath($xml);
        $this->assertNoFault($xpath);
        $this->assertNoErrors($xpath);

        return (int) $this->text($xpath, '//fe:FECompUltimoAutorizadoResult/fe:CbteNro');
    }

    /**
     * @return array{app_server:string,db_server:string,auth_server:string}
     */
    public function dummy(string $xml): array
    {
        $xpath = $this->xpath($xml);
        $this->assertNoFault($xpath);

        return [
            'app_server' => $this->text($xpath, '//fe:FEDummyResult/fe:AppServer'),
            'db_server' => $this->text($xpath, '//fe:FEDummyResult/fe:DbServer'),
            'auth_server' => $this->text($xpath, '//fe:FEDummyResult/fe:AuthServer'),
        ];
    }

    /**
     * @return array<int,array{numero:int,emision_tipo:string,bloqueado:string,fecha_baja:string}>
     */
    public function pointsOfSale(string $xml): array
    {
        $xpath = $this->xpath($xml);
        $this->assertNoFault($xpath);
        $this->assertNoErrors($xpath);

        $items = [];
        foreach ($xpath->query('//fe:FEParamGetPtosVentaResult/fe:ResultGet/fe:PtoVenta') as $node) {
            $items[] = [
                'numero' => (int) $this->text($xpath, 'fe:Nro', $node),
                'emision_tipo' => $this->text($xpath, 'fe:EmisionTipo', $node),
                'bloqueado' => $this->text($xpath, 'fe:Bloqueado', $node),
                'fecha_baja' => $this->text($xpath, 'fe:FchBaja', $node),
            ];
        }

        return $items;
    }

    public function fecaesolicitar(string $xml): array
    {
        $xpath = $this->xpath($xml);
        $this->assertNoFault($xpath);

        $response = $xpath->query('//fe:FECAESolicitarResult/fe:FeDetResp/fe:FECAEDetResponse')->item(0);
        $observations = $response !== null ? $this->pairs($xpath, $response, 'fe:Observaciones/fe:Obs') : [];
        $cae = $response !== null ? $this->text($xpath, 'fe:CAE', $response) : '';
        $expiration = $response !== null ? $this->text($xpath, 'fe:CAEFchVto', $response) : '';

        return [
            'resultado' => $response !== null ? $this->text($xpath, 'fe:Resultado', $response) : '',
            'cae' => $cae !== '' ? $cae : null,
            'cae_fch_vto' => $expiration !== '' ? $this->isoDate($expiration) : null,
            'observaciones' => $observations,
            'errores' => $this->pairs($xpath, null, '//fe:Errors/fe:Err'),
        ];
    }

    public function consultar(string $xml): ?array
    {
        $xpath = $this->xpath($xml);
        $this->assertNoFault($xpath);

        $result = $xpath->query('//fe:FECompConsultarResult/fe:ResultGet')->item(0);
        if ($result === null) {
            return null;
        }
        $cae = $this->text($xpath, 'fe:CodAutorizacion', $result);
        if ($cae === '') {
            return null;
        }
        $expiration = $this->text($xpath, 'fe:FchVto', $result);

        return [
            'resultado' => $this->text($xpath, 'fe:Resultado', $result),
            'cae' => $cae,
            'cae_fch_vto' => $expiration !== '' ? $this->isoDate($expiration) : null,
            'observaciones' => $this->pairs($xpath, $result, 'fe:Observaciones/fe:Obs'),
            'errores' => [],
        ];
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        if (!@$document->loadXML($xml)) {
            throw new ArcaCommunicationException('Respuesta SOAP de ARCA invalida.', ArcaCommunicationException::CATEGORY_RESPONSE);
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('fe', self::NS);

        return $xpath;
    }

    private function assertNoFault(DOMXPath $xpath): void
    {
        $fault = $xpath->query('//soap:Fault/faultstring')->item(0);
        if ($fault !== null) {
            throw new ArcaCommunicationException('ARCA Fault: ' . trim($fault->textContent), ArcaCommunicationException::CATEGORY_RESPONSE);
        }
    }

    private function assertNoErrors(DOMXPath $xpath, string $query = '//fe:Errors/fe:Err'): void
    {
        $error = $xpath->query($query)->item(0);
        if ($error !== null) {
            $code = $this->text($xpath, 'fe:Code', $error);
            $message = $this->text($xpath, 'fe:Msg', $error);
            throw new ArcaCommunicationException('ARCA ' . $code . ': ' . $message, ArcaCommunicationException::CATEGORY_RESPONSE);
        }
    }

    private function text(DOMXPath $xpath, string $query, ?\DOMNode $context = null): string
    {
        $node = $xpath->query($query, $context)->item(0);

        return $node === null ? '' : trim($node->textContent);
    }

    private function pairs(DOMXPath $xpath, ?\DOMNode $context, string $query): array
    {
        $result = [];
        foreach ($xpath->query($query, $context) as $node) {
            $result[] = [
                'code' => (int) $this->text($xpath, 'fe:Code', $node),
                'msg' => $this->text($xpath, 'fe:Msg', $node),
            ];
        }

        return $result;
    }

    private function isoDate(string $arcaDate): string
    {
        $arcaDate = preg_replace('/\D/', '', $arcaDate) ?? '';
        if (strlen($arcaDate) !== 8) {
            return $arcaDate;
        }

        return substr($arcaDate, 0, 4) . '-' . substr($arcaDate, 4, 2) . '-' . substr($arcaDate, 6, 2);
    }
}
