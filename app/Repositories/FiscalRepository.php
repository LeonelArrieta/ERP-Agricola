<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FiscalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function pointsOfSale(int $offset, int $limit, ?bool $active, string $search): array
    {
        $where = [];
        $params = [];
        if ($active !== null) {
            $where[] = 'activo = :active';
            $params['active'] = $active ? 1 : 0;
        }
        if ($search !== '') {
            $where[] = '(descripcion LIKE :search_desc OR numero = :search_number)';
            $params['search_desc'] = '%' . $search . '%';
            $params['search_number'] = is_numeric($search) ? (int) $search : -1;
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM puntos_venta' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare('SELECT * FROM puntos_venta' . $condition . ' ORDER BY numero LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function pointOfSale(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM puntos_venta WHERE id_punto_venta = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function savePointOfSale(array $data, ?int $id = null): int
    {
        if ($id === null) {
            $query = $this->pdo->prepare('INSERT INTO puntos_venta (numero, descripcion) VALUES (:numero, :descripcion)');
            $query->execute($data);

            return (int) $this->pdo->lastInsertId();
        }
        $query = $this->pdo->prepare('UPDATE puntos_venta SET numero = :numero, descripcion = :descripcion WHERE id_punto_venta = :id');
        $query->execute($data + ['id' => $id]);

        return $id;
    }

    public function deactivatePointOfSale(int $id): void
    {
        $this->pdo->prepare('UPDATE puntos_venta SET activo = 0 WHERE id_punto_venta = :id')->execute(['id' => $id]);
    }

    public function activatePointOfSale(int $id): void
    {
        $this->pdo->prepare('UPDATE puntos_venta SET activo = 1 WHERE id_punto_venta = :id')->execute(['id' => $id]);
    }

    public function pointOfSaleOptions(): array
    {
        return $this->pdo->query('SELECT id_punto_venta, numero, descripcion FROM puntos_venta WHERE activo = 1 ORDER BY numero')->fetchAll();
    }

    public function voucherTypes(): array
    {
        return $this->pdo->query('SELECT * FROM tipos_comprobante WHERE activo = 1 ORDER BY id_tipo_comprobante')->fetchAll();
    }

    public function voucherType(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM tipos_comprobante WHERE id_tipo_comprobante = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function createVoucher(array $data): int
    {
        $fields = [
            'id_venta', 'id_punto_venta', 'id_tipo_comprobante', 'numero', 'fecha', 'concepto',
            'doc_tipo', 'doc_nro', 'condicion_iva_receptor_id',
            'imp_total', 'imp_tot_conc', 'imp_neto', 'imp_op_ex', 'imp_iva', 'imp_trib',
            'moneda', 'cotizacion', 'paga_misma_moneda', 'fch_serv_desde', 'fch_serv_hasta', 'fch_vto_pago',
            'estado_fiscal', 'id_comprobante_asociado', 'asoc_tipo', 'asoc_pto_vta', 'asoc_nro', 'asoc_fecha', 'asoc_cuit',
            'emisor_razon_social', 'emisor_cuit', 'emisor_domicilio', 'emisor_condicion_iva', 'emisor_ingresos_brutos', 'emisor_inicio_actividades',
            'id_usuario',
        ];
        $query = $this->pdo->prepare(
            'INSERT INTO comprobantes_fiscales (' . implode(', ', $fields) . ') VALUES (:' . implode(', :', $fields) . ')'
        );
        $query->execute(array_intersect_key($data, array_flip($fields)));

        return (int) $this->pdo->lastInsertId();
    }

    public function updateVoucher(int $id, array $fields): void
    {
        $allowed = ['numero', 'estado_fiscal', 'cae', 'cae_fch_vto', 'resultado_arca', 'observaciones', 'errores'];
        $data = array_intersect_key($fields, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, array_keys($data)));
        $query = $this->pdo->prepare('UPDATE comprobantes_fiscales SET ' . $assignments . ' WHERE id_comprobante_fiscal = :id');
        $query->execute($data + ['id' => $id]);
    }

    public function voucher(int $id): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT cf.*, tc.nombre AS tipo_nombre, tc.clase, tc.discrimina_iva, tc.signo, pv.numero AS punto_venta_numero,
                    v.fecha AS venta_fecha, v.total AS venta_total, c.razon_social AS cliente
             FROM comprobantes_fiscales cf
             INNER JOIN tipos_comprobante tc ON tc.id_tipo_comprobante = cf.id_tipo_comprobante
             INNER JOIN puntos_venta pv ON pv.id_punto_venta = cf.id_punto_venta
             INNER JOIN ventas v ON v.id_venta = cf.id_venta
             INNER JOIN clientes c ON c.id_cliente = v.id_cliente
             WHERE cf.id_comprobante_fiscal = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function vouchers(int $offset, int $limit, array $filters = []): array
    {
        $where = [];
        $params = [];
        if (($filters['estado_fiscal'] ?? null) !== null) {
            $where[] = 'cf.estado_fiscal = :estado';
            $params['estado'] = $filters['estado_fiscal'];
        }
        if (($filters['tipo'] ?? null) !== null) {
            $where[] = 'cf.id_tipo_comprobante = :tipo';
            $params['tipo'] = $filters['tipo'];
        }
        if (($filters['punto_venta'] ?? null) !== null) {
            $where[] = 'cf.id_punto_venta = :point';
            $params['point'] = $filters['punto_venta'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(c.razon_social LIKE :search_client OR cf.cae LIKE :search_cae OR cf.numero = :search_number)';
            $params['search_client'] = '%' . $filters['search'] . '%';
            $params['search_cae'] = '%' . $filters['search'] . '%';
            $params['search_number'] = is_numeric($filters['search']) ? (int) $filters['search'] : -1;
        }
        if (($filters['from'] ?? null) !== null) {
            $where[] = 'cf.fecha >= :date_from';
            $params['date_from'] = $filters['from'];
        }
        if (($filters['to'] ?? null) !== null) {
            $where[] = 'cf.fecha <= :date_to';
            $params['date_to'] = $filters['to'];
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM comprobantes_fiscales cf
             INNER JOIN ventas v ON v.id_venta = cf.id_venta
             INNER JOIN clientes c ON c.id_cliente = v.id_cliente' . $condition
        );
        $count->execute($params);
        $query = $this->pdo->prepare(
            'SELECT cf.*, tc.nombre AS tipo_nombre, tc.clase, tc.signo, pv.numero AS punto_venta_numero,
                    v.fecha AS venta_fecha, c.razon_social AS cliente
             FROM comprobantes_fiscales cf
             INNER JOIN tipos_comprobante tc ON tc.id_tipo_comprobante = cf.id_tipo_comprobante
             INNER JOIN puntos_venta pv ON pv.id_punto_venta = cf.id_punto_venta
             INNER JOIN ventas v ON v.id_venta = cf.id_venta
             INNER JOIN clientes c ON c.id_cliente = v.id_cliente' . $condition .
            ' ORDER BY cf.id_comprobante_fiscal DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function vouchersBySale(int $sale): array
    {
        $query = $this->pdo->prepare(
            'SELECT cf.*, tc.nombre AS tipo_nombre, tc.clase, tc.signo, pv.numero AS punto_venta_numero
             FROM comprobantes_fiscales cf
             INNER JOIN tipos_comprobante tc ON tc.id_tipo_comprobante = cf.id_tipo_comprobante
             INNER JOIN puntos_venta pv ON pv.id_punto_venta = cf.id_punto_venta
             WHERE cf.id_venta = :sale
             ORDER BY cf.id_comprobante_fiscal'
        );
        $query->execute(['sale' => $sale]);

        return $query->fetchAll();
    }

    public function authorizedInvoiceForSale(int $sale): ?array
    {
        $query = $this->pdo->prepare(
            "SELECT cf.* FROM comprobantes_fiscales cf
             INNER JOIN tipos_comprobante tc ON tc.id_tipo_comprobante = cf.id_tipo_comprobante
             WHERE cf.id_venta = :sale AND cf.estado_fiscal = 'Autorizado' AND tc.signo = 'D' AND tc.discrimina_iva IN (0, 1) AND tc.id_tipo_comprobante IN (1, 6, 11)
             ORDER BY cf.id_comprobante_fiscal LIMIT 1"
        );
        $query->execute(['sale' => $sale]);
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function maxAuthorizedNumber(int $pointId, int $tipo): int
    {
        $query = $this->pdo->prepare(
            "SELECT COALESCE(MAX(numero), 0) FROM comprobantes_fiscales WHERE id_punto_venta = :point AND id_tipo_comprobante = :tipo AND estado_fiscal = 'Autorizado'"
        );
        $query->execute(['point' => $pointId, 'tipo' => $tipo]);

        return (int) $query->fetchColumn();
    }

    public function numberExists(int $pointId, int $tipo, int $numero, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM comprobantes_fiscales WHERE id_punto_venta = :point AND id_tipo_comprobante = :tipo AND numero = :numero';
        $params = ['point' => $pointId, 'tipo' => $tipo, 'numero' => $numero];
        if ($excludeId !== null) {
            $sql .= ' AND id_comprobante_fiscal <> :exclude';
            $params['exclude'] = $excludeId;
        }
        $query = $this->pdo->prepare($sql . ' LIMIT 1');
        $query->execute($params);

        return $query->fetchColumn() !== false;
    }

    public function addIva(array $data): void
    {
        $query = $this->pdo->prepare('INSERT INTO comprobante_iva (id_comprobante_fiscal, alic_iva_id, base_imp, importe) VALUES (:voucher, :rate, :base, :amount)');
        $query->execute($data);
    }

    public function iva(int $voucherId): array
    {
        $query = $this->pdo->prepare('SELECT * FROM comprobante_iva WHERE id_comprobante_fiscal = :voucher ORDER BY alic_iva_id');
        $query->execute(['voucher' => $voucherId]);

        return $query->fetchAll();
    }

    public function markSaleInvoiced(int $sale): void
    {
        $query = $this->pdo->prepare("UPDATE ventas SET estado = 'Facturado' WHERE id_venta = :id AND estado = 'Pendiente'");
        $query->execute(['id' => $sale]);
    }
}
