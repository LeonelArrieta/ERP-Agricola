<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\DashboardRepository;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardService
{
    private DateTimeZone $timezone;

    public function __construct(private readonly DashboardRepository $dashboard)
    {
        $this->timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
    }

    public function summary(string $period = 'month', ?string $from = null, ?string $to = null): array
    {
        [$fromDate, $toDate, $label] = $this->period($period, $from, $to);

        return [
            'period' => ['key' => $period, 'from' => $fromDate, 'to' => $toDate, 'label' => $label],
            'cash' => ['balance' => $this->dashboard->cashBalance()],
            'cash_flow' => $this->dashboard->cashFlow($fromDate, $toDate, $this->cashFlowGranularity($fromDate, $toDate)),
            'receivables' => $this->dashboard->receivables(),
            'payables' => $this->dashboard->payables(),
            'sales' => $this->dashboard->sales($fromDate, $toDate),
            'purchases' => $this->dashboard->purchases($fromDate, $toDate),
            'budgets' => $this->dashboard->budgets($fromDate, $toDate),
            'production' => $this->dashboard->production(),
            'inventory' => $this->dashboard->inventory(),
            'fiscal' => $this->dashboard->fiscal(),
            'recent_sales' => $this->dashboard->recentSales(),
            'recent_purchases' => $this->dashboard->recentPurchases(),
        ];
    }

    private function cashFlowGranularity(string $from, string $to): string
    {
        $days = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;

        if ($days <= 14) {
            return 'day';
        }

        if ($days <= 180) {
            return 'week';
        }

        return 'month';
    }

    private function period(string $period, ?string $from, ?string $to): array
    {
        $today = new DateTimeImmutable('today', $this->timezone);
        if ($period === 'today') {
            return [$today->format('Y-m-d'), $today->format('Y-m-d'), 'Hoy'];
        }
        if ($period === 'month' || $period === '') {
            return [$today->modify('first day of this month')->format('Y-m-d'), $today->format('Y-m-d'), 'Mes actual'];
        }
        if ($period === 'custom') {
            $fromDate = $this->date($from);
            $toDate = $this->date($to);
            if ($fromDate > $toDate) {
                throw new HttpException('VALIDATION_ERROR', 'El inicio del período no puede ser posterior al fin.', 422);
            }
            return [$fromDate, $toDate, $fromDate . ' a ' . $toDate];
        }

        throw new HttpException('VALIDATION_ERROR', 'El período seleccionado no es válido.', 422);
    }

    private function date(?string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value, $this->timezone);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException('VALIDATION_ERROR', 'Las fechas del período no son válidas.', 422);
        }

        return $date->format('Y-m-d');
    }
}
