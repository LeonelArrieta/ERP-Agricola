<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FacturacionSolicitudRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function start(int $voucherId, string $operation, string $environment, string $version, string $correlation): int
    {
        $next = $this->pdo->prepare('SELECT COALESCE(MAX(numero_intento), 0) + 1 FROM facturacion_solicitudes WHERE id_comprobante_fiscal = :voucher');
        $next->execute(['voucher' => $voucherId]);

        $query = $this->pdo->prepare(
            'INSERT INTO facturacion_solicitudes
                (id_comprobante_fiscal, numero_intento, operacion, ambiente, adaptador_version, estado, correlacion, inicio)
             VALUES (:voucher, :attempt, :operation, :environment, :version, \'iniciado\', :correlation, NOW())'
        );
        $query->execute([
            'voucher' => $voucherId,
            'attempt' => (int) $next->fetchColumn(),
            'operation' => $operation,
            'environment' => $environment,
            'version' => $version,
            'correlation' => $correlation,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function finish(int $id, string $state, ?string $code = null, ?string $message = null, ?array $detail = null): void
    {
        $errors = $message === null ? null : json_encode([['code' => $code, 'msg' => mb_substr($message, 0, 300)]], JSON_UNESCAPED_UNICODE);

        $query = $this->pdo->prepare(
            'UPDATE facturacion_solicitudes
             SET estado = :state, codigo_resultado = :code, errores = :errors, detalle_tecnico = :detail,
                 fin = NOW(), duracion_ms = ROUND(TIMESTAMPDIFF(MICROSECOND, inicio, NOW()) / 1000)
             WHERE id_solicitud = :id'
        );
        $query->execute([
            'state' => $state,
            'code' => $code,
            'errors' => $errors,
            'detail' => $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);
    }

    public function byVoucher(int $voucherId): array
    {
        $query = $this->pdo->prepare('SELECT * FROM facturacion_solicitudes WHERE id_comprobante_fiscal = :voucher ORDER BY id_solicitud');
        $query->execute(['voucher' => $voucherId]);

        return $query->fetchAll();
    }
}
