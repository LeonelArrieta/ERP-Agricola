<?php

declare(strict_types=1);

namespace App\Repositories;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

final class DashboardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function cashBalance(): float
    {
        return (float) $this->pdo->query('SELECT saldo_actual FROM caja WHERE id_caja = 1')->fetchColumn();
    }

    public function receivables(): array
    {
        $row = $this->pdo->query("SELECT COALESCE(SUM(CASE WHEN estado <> 'Cancelada' THEN saldo_actual ELSE 0 END), 0) AS pending, COALESCE(SUM(CASE WHEN estado <> 'Cancelada' AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURRENT_DATE AND saldo_actual > 0 THEN saldo_actual ELSE 0 END), 0) AS overdue FROM cuentas_por_cobrar")->fetch();
        return ['pending' => (float) $row['pending'], 'overdue' => (float) $row['overdue']];
    }

    public function payables(): array
    {
        $row = $this->pdo->query("SELECT COALESCE(SUM(CASE WHEN estado <> 'Cancelada' THEN saldo_actual ELSE 0 END), 0) AS pending, COALESCE(SUM(CASE WHEN estado <> 'Cancelada' AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURRENT_DATE AND saldo_actual > 0 THEN saldo_actual ELSE 0 END), 0) AS overdue FROM cuentas_por_pagar")->fetch();
        return ['pending' => (float) $row['pending'], 'overdue' => (float) $row['overdue']];
    }

    public function sales(string $from, string $to): array
    {
        $query = $this->pdo->prepare("SELECT COUNT(*) AS quantity, COALESCE(SUM(total), 0) AS total FROM ventas WHERE fecha BETWEEN :from_date AND :to_date AND estado NOT IN ('Presupuesto', 'Cancelado')");
        $query->execute(['from_date' => $from, 'to_date' => $to]);
        $sales = $query->fetch();

        $margin = $this->pdo->prepare("SELECT COALESCE(SUM(d.subtotal - (d.precio_costo * d.cantidad)), 0) AS amount, COALESCE(SUM(d.subtotal), 0) AS revenue FROM detalles_ventas d INNER JOIN ventas v ON v.id_venta = d.id_venta WHERE v.fecha BETWEEN :margin_from AND :margin_to AND v.estado NOT IN ('Presupuesto', 'Cancelado')");
        $margin->execute(['margin_from' => $from, 'margin_to' => $to]);
        $marginRow = $margin->fetch();

        return [
            'quantity' => (int) $sales['quantity'],
            'total' => (float) $sales['total'],
            'margin_amount' => (float) $marginRow['amount'],
            'margin_percent' => (float) $marginRow['revenue'] > 0 ? ((float) $marginRow['amount'] / (float) $marginRow['revenue']) * 100 : 0.0,
        ];
    }

    public function purchases(string $from, string $to): array
    {
        $query = $this->pdo->prepare("SELECT COUNT(*) AS quantity, COALESCE(SUM(total), 0) AS total FROM compras WHERE fecha BETWEEN :from_date AND :to_date AND estado NOT IN ('Presupuesto', 'Cancelado')");
        $query->execute(['from_date' => $from, 'to_date' => $to]);
        $row = $query->fetch();
        return ['quantity' => (int) $row['quantity'], 'total' => (float) $row['total']];
    }

    public function cashFlow(string $from, string $to, string $granularity): array
    {
        $bucketExpression = match ($granularity) {
            'week' => "DATE_SUB(DATE(fecha_hora), INTERVAL WEEKDAY(fecha_hora) DAY)",
            'month' => "DATE_FORMAT(fecha_hora, '%Y-%m-01')",
            default => 'DATE(fecha_hora)',
        };

        $toExclusive = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
        $query = $this->pdo->prepare(
            "SELECT {$bucketExpression} AS bucket,
                    COALESCE(SUM(CASE WHEN tipo IN ('Ingreso', 'ReversionEgreso') THEN importe ELSE 0 END), 0) AS inflows,
                    COALESCE(SUM(CASE WHEN tipo IN ('Egreso', 'ReversionIngreso') THEN importe ELSE 0 END), 0) AS outflows
             FROM movimientos_caja
             WHERE fecha_hora >= :from_date AND fecha_hora < :to_date
             GROUP BY bucket
             ORDER BY bucket"
        );
        $query->execute([
            'from_date' => $from . ' 00:00:00',
            'to_date' => $toExclusive . ' 00:00:00',
        ]);

        $rows = [];
        foreach ($query->fetchAll() as $row) {
            $rows[(string) $row['bucket']] = [
                'inflows' => (float) $row['inflows'],
                'outflows' => (float) $row['outflows'],
            ];
        }

        $start = new DateTimeImmutable($from);
        if ($granularity === 'week') {
            $start = $start->modify('-' . ((int) $start->format('N') - 1) . ' days');
        } elseif ($granularity === 'month') {
            $start = $start->modify('first day of this month');
        }

        $interval = match ($granularity) {
            'week' => new DateInterval('P7D'),
            'month' => new DateInterval('P1M'),
            default => new DateInterval('P1D'),
        };

        $end = (new DateTimeImmutable($to))->modify('+1 day');
        $buckets = [];
        $totalInflows = 0.0;
        $totalOutflows = 0.0;
        $maxValue = 0.0;

        foreach (new DatePeriod($start, $interval, $end) as $date) {
            $key = $date->format('Y-m-d');
            $values = $rows[$key] ?? ['inflows' => 0.0, 'outflows' => 0.0];
            $net = $values['inflows'] - $values['outflows'];
            $totalInflows += $values['inflows'];
            $totalOutflows += $values['outflows'];
            $maxValue = max($maxValue, $values['inflows'], $values['outflows']);
            $buckets[] = [
                'key' => $key,
                'label' => $this->cashFlowLabel($date, $granularity),
                'inflows' => $values['inflows'],
                'outflows' => $values['outflows'],
                'net' => $net,
            ];
        }

        return [
            'granularity' => $granularity,
            'buckets' => $buckets,
            'inflows' => $totalInflows,
            'outflows' => $totalOutflows,
            'net' => $totalInflows - $totalOutflows,
            'max_value' => $maxValue,
            'has_movements' => $totalInflows > 0 || $totalOutflows > 0,
        ];
    }

    public function budgets(string $from, string $to): array
    {
        $sales = $this->pdo->prepare("SELECT COUNT(*) AS quantity, COALESCE(SUM(total), 0) AS total FROM ventas WHERE fecha BETWEEN :sales_from AND :sales_to AND estado = 'Presupuesto'");
        $sales->execute(['sales_from' => $from, 'sales_to' => $to]);
        $salesRow = $sales->fetch();
        $purchases = $this->pdo->prepare("SELECT COUNT(*) AS quantity, COALESCE(SUM(total), 0) AS total FROM compras WHERE fecha BETWEEN :purchases_from AND :purchases_to AND estado = 'Presupuesto'");
        $purchases->execute(['purchases_from' => $from, 'purchases_to' => $to]);
        $purchasesRow = $purchases->fetch();

        return [
            'sales' => ['quantity' => (int) $salesRow['quantity'], 'total' => (float) $salesRow['total']],
            'purchases' => ['quantity' => (int) $purchasesRow['quantity'], 'total' => (float) $purchasesRow['total']],
        ];
    }

    public function production(): array
    {
        $cycles = $this->pdo->query("SELECT estado, COUNT(*) AS quantity FROM ciclos_cultivo GROUP BY estado")->fetchAll();
        $tasks = $this->pdo->query("SELECT estado, COUNT(*) AS quantity FROM tareas_cultivo GROUP BY estado")->fetchAll();
        $provisional = (int) $this->pdo->query('SELECT COUNT(*) FROM lotes_produccion WHERE es_costo_provisional = 1 AND cantidad_disponible > 0')->fetchColumn();
        $harvested = (float) $this->pdo->query("SELECT COALESCE(SUM(cantidad), 0) FROM cosechas WHERE fecha >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AND fecha <= CURRENT_DATE")->fetchColumn();

        return [
            'cycles' => array_column($cycles, 'quantity', 'estado'),
            'tasks' => array_column($tasks, 'quantity', 'estado'),
            'provisional_lots' => $provisional,
            'harvested_current_month' => $harvested,
        ];
    }

    public function inventory(): array
    {
        $zero = (int) $this->pdo->query('SELECT COUNT(*) FROM insumos WHERE activo = 1 AND stock_actual = 0')->fetchColumn();
        $movements = (int) $this->pdo->query('SELECT COUNT(*) FROM movimientos_inventario WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
        return ['zero_stock_inputs' => $zero, 'movements_last_30_days' => $movements];
    }

    public function fiscal(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN estado_fiscal IN ('ErrorComunicacion', 'ResultadoIncierto', 'RequiereRevision') THEN 1 ELSE 0 END), 0) AS uncertain,
                COALESCE(SUM(CASE WHEN estado_fiscal = 'Rechazado' THEN 1 ELSE 0 END), 0) AS rejected
             FROM comprobantes_fiscales"
        )->fetch();

        return ['uncertain' => (int) $row['uncertain'], 'rejected' => (int) $row['rejected']];
    }

    private function cashFlowLabel(DateTimeImmutable $date, string $granularity): string
    {
        if ($granularity === 'month') {
            $months = [
                1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
                7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
            ];

            return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
        }

        if ($granularity === 'week') {
            return 'Sem ' . $date->format('d/m');
        }

        return $date->format('d/m');
    }

    public function recentSales(int $limit = 5): array
    {
        $query = $this->pdo->prepare("SELECT v.id_venta, v.fecha, v.total, v.estado, c.razon_social AS cliente FROM ventas v INNER JOIN clientes c ON c.id_cliente = v.id_cliente WHERE v.estado <> 'Cancelado' ORDER BY v.id_venta DESC LIMIT :limit");
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    public function recentPurchases(int $limit = 5): array
    {
        $query = $this->pdo->prepare("SELECT c.id_compra, c.fecha, c.total, c.estado, p.razon_social AS proveedor FROM compras c INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor WHERE c.estado <> 'Cancelado' ORDER BY c.id_compra DESC LIMIT :limit");
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }
}
