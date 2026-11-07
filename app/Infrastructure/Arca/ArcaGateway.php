<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

interface ArcaGateway
{
    public function lastAuthorized(int $pointOfSale, int $voucherType): int;

    /**
     * @return array{app_server:string,db_server:string,auth_server:string}
     */
    public function dummy(): array;

    /**
     * Metadatos del Ticket de Acceso (sin token ni firma).
     *
     * @return array{service:string,certificate_dn:string,represented_cuits:int[],expires_at:int}
     */
    public function authorization(): array;

    /**
     * @return array<int,array{numero:int,emision_tipo:string,bloqueado:string,fecha_baja:string}>
     */
    public function pointsOfSale(): array;

    /**
     * @param array $request salida de ArcaRequestMapper::fromComprobante()
     * @return array{resultado:string,cae:?string,cae_fch_vto:?string,observaciones:array,errores:array}
     */
    public function requestCae(array $request): array;

    /**
     * @return array{resultado:string,cae:?string,cae_fch_vto:?string,observaciones:array,errores:array}|null
     */
    public function consult(int $pointOfSale, int $voucherType, int $number): ?array;
}
