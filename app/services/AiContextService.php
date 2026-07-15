<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AiContextService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Return a deliberately small operational summary. The AI receives aggregate
     * business data, not customer message bodies, addresses, or uploaded files.
     *
     * @return array<string, int|float|string>
     */
    public function operationalSummary(): array
    {
        return [
            'generated_at' => date(DATE_ATOM),
            'jobs_today' => $this->scalar("SELECT COUNT(*) FROM jobs WHERE route_date = CURDATE()"),
            'jobs_completed_today' => $this->scalar("SELECT COUNT(*) FROM jobs WHERE route_date = CURDATE() AND status = 'completed'"),
            'jobs_late_today' => $this->scalar("SELECT COUNT(*) FROM jobs WHERE route_date = CURDATE() AND COALESCE(eta_late_minutes, 0) > 0"),
            'open_estimates' => $this->scalar("SELECT COUNT(*) FROM estimates WHERE status IN ('draft','sent','viewed')"),
            'open_invoices' => $this->scalar("SELECT COUNT(*) FROM invoices WHERE status NOT IN ('paid','void') AND COALESCE(balance_due, 0) > 0"),
            'overdue_balance' => $this->decimal("SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE due_date < CURDATE() AND status NOT IN ('paid','void')"),
            'payments_last_30_days' => $this->decimal("SELECT COALESCE(SUM(amount),0) FROM payments WHERE received_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'unread_customer_messages' => $this->scalar("SELECT COUNT(*) FROM conversations WHERE status IN ('open','pending')"),
            'active_crews' => $this->scalar("SELECT COUNT(*) FROM crews WHERE status = 'active'"),
        ];
    }

    private function scalar(string $sql): int
    {
        try {
            return (int) $this->pdo->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function decimal(string $sql): float
    {
        try {
            return round((float) $this->pdo->query($sql)->fetchColumn(), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }
}
