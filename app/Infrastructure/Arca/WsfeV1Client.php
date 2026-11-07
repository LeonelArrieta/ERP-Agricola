<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

final class WsfeV1Client implements ArcaGateway
{
    private const NS = 'http://ar.gov.afip.dif.FEV1/';

    public function __construct(
        private readonly SoapTransport $transport,
        private readonly ArcaAuthenticator $authenticator,
        private readonly ArcaResponseMapper $responses,
        private readonly string $endpoint,
        private readonly int $cuit,
    ) {
    }

    public function lastAuthorized(int $pointOfSale, int $voucherType): int
    {
        $body = '<FECompUltimoAutorizado xmlns="' . self::NS . '">' . $this->authXml()
            . '<PtoVta>' . $pointOfSale . '</PtoVta>'
            . '<CbteTipo>' . $voucherType . '</CbteTipo>'
            . '</FECompUltimoAutorizado>';

        $xml = $this->transport->call($this->endpoint, self::NS . 'FECompUltimoAutorizado', $body);

        return $this->responses->ultimoAutorizado($xml);
    }

    public function dummy(): array
    {
        $body = '<FEDummy xmlns="' . self::NS . '"/>';
        $xml = $this->transport->call($this->endpoint, self::NS . 'FEDummy', $body);

        return $this->responses->dummy($xml);
    }

    public function authorization(): array
    {
        $ticket = $this->authenticator->ticket();

        return [
            'service' => (string) ($ticket['service'] ?? ''),
            'certificate_dn' => (string) ($ticket['certificate_dn'] ?? ''),
            'represented_cuits' => is_array($ticket['represented_cuits'] ?? null) ? $ticket['represented_cuits'] : [],
            'expires_at' => (int) ($ticket['expires_at'] ?? 0),
        ];
    }

    public function pointsOfSale(): array
    {
        $body = '<FEParamGetPtosVenta xmlns="' . self::NS . '">' . $this->authXml() . '</FEParamGetPtosVenta>';
        $xml = $this->transport->call($this->endpoint, self::NS . 'FEParamGetPtosVenta', $body);

        return $this->responses->pointsOfSale($xml);
    }

    public function requestCae(array $request): array
    {
        return $this->responses->fecaesolicitar($this->requestCaeRaw($request));
    }

    public function requestCaeRaw(array $request): string
    {
        $detail = '<Concepto>' . (int) $request['concepto'] . '</Concepto>'
            . '<DocTipo>' . (int) $request['doc_tipo'] . '</DocTipo>'
            . '<DocNro>' . htmlspecialchars((string) $request['doc_nro'], ENT_XML1) . '</DocNro>'
            . '<CbteDesde>' . (int) $request['cbte_desde'] . '</CbteDesde>'
            . '<CbteHasta>' . (int) $request['cbte_hasta'] . '</CbteHasta>'
            . '<CbteFch>' . $request['cbte_fch'] . '</CbteFch>'
            . '<ImpTotal>' . $this->amount($request['imp_total']) . '</ImpTotal>'
            . '<ImpTotConc>' . $this->amount($request['imp_tot_conc']) . '</ImpTotConc>'
            . '<ImpNeto>' . $this->amount($request['imp_neto']) . '</ImpNeto>'
            . '<ImpOpEx>' . $this->amount($request['imp_op_ex']) . '</ImpOpEx>'
            . '<ImpIVA>' . $this->amount($request['imp_iva']) . '</ImpIVA>'
            . '<ImpTrib>' . $this->amount($request['imp_trib']) . '</ImpTrib>'
            . '<MonId>' . htmlspecialchars((string) $request['mon_id'], ENT_XML1) . '</MonId>'
            . '<MonCotiz>' . $this->amount($request['mon_cotiz']) . '</MonCotiz>'
            . '<CondicionIVAReceptorId>' . (int) $request['condicion_iva_receptor_id'] . '</CondicionIVAReceptorId>';

        if ($request['fch_serv_desde'] !== null) {
            $detail .= '<FchServDesde>' . $request['fch_serv_desde'] . '</FchServDesde>'
                . '<FchServHasta>' . $request['fch_serv_hasta'] . '</FchServHasta>'
                . '<FchVtoPago>' . $request['fch_vto_pago'] . '</FchVtoPago>';
        }

        if (is_array($request['asoc'])) {
            $detail .= '<CbtesAsoc><CbteAsoc>'
                . '<Tipo>' . (int) $request['asoc']['tipo'] . '</Tipo>'
                . '<PtoVta>' . (int) $request['asoc']['pto_vta'] . '</PtoVta>'
                . '<Nro>' . (int) $request['asoc']['nro'] . '</Nro>'
                . '<Cuit>' . htmlspecialchars((string) $request['asoc']['cuit'], ENT_XML1) . '</Cuit>'
                . '<CbteFch>' . $request['asoc']['fecha'] . '</CbteFch>'
                . '</CbteAsoc></CbtesAsoc>';
        }

        if ($request['iva'] !== []) {
            $detail .= '<Iva>';
            foreach ($request['iva'] as $rate) {
                $detail .= '<AlicIva>'
                    . '<Id>' . (int) $rate['id'] . '</Id>'
                    . '<BaseImp>' . $this->amount($rate['base']) . '</BaseImp>'
                    . '<Importe>' . $this->amount($rate['importe']) . '</Importe>'
                    . '</AlicIva>';
            }
            $detail .= '</Iva>';
        }

        $body = '<FECAESolicitar xmlns="' . self::NS . '">' . $this->authXml()
            . '<FeCAEReq>'
            . '<FeCabReq><CantReg>1</CantReg><PtoVta>' . (int) $request['pto_vta'] . '</PtoVta><CbteTipo>' . (int) $request['cbte_tipo'] . '</CbteTipo></FeCabReq>'
            . '<FeDetReq><FECAEDetRequest>' . $detail . '</FECAEDetRequest></FeDetReq>'
            . '</FeCAEReq>'
            . '</FECAESolicitar>';

        $xml = $this->transport->call($this->endpoint, self::NS . 'FECAESolicitar', $body);

        return $xml;
    }

    public function consult(int $pointOfSale, int $voucherType, int $number): ?array
    {
        $body = '<FECompConsultar xmlns="' . self::NS . '">' . $this->authXml()
            . '<FeCompConsReq>'
            . '<CbteTipo>' . $voucherType . '</CbteTipo>'
            . '<CbteNro>' . $number . '</CbteNro>'
            . '<PtoVta>' . $pointOfSale . '</PtoVta>'
            . '</FeCompConsReq>'
            . '</FECompConsultar>';

        $xml = $this->transport->call($this->endpoint, self::NS . 'FECompConsultar', $body);

        return $this->responses->consultar($xml);
    }

    private function authXml(): string
    {
        $ticket = $this->authenticator->ticket();

        return '<Auth>'
            . '<Token>' . htmlspecialchars((string) $ticket['token'], ENT_XML1) . '</Token>'
            . '<Sign>' . htmlspecialchars((string) $ticket['sign'], ENT_XML1) . '</Sign>'
            . '<Cuit>' . $this->cuit . '</Cuit>'
            . '</Auth>';
    }

    private function amount(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
