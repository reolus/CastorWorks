<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use PDO;

final class BusinessIntelligenceController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'administrator', 'office');

        $pdo = Database::connection();
        [$from, $to] = $this->dateRange();

        $summary = $this->summary($pdo, $from, $to);
        $monthly = $this->monthlyTrend($pdo, $from, $to);
        $services = $this->servicePerformance($pdo, $from, $to);
        $crews = $this->crewPerformance($pdo, $from, $to);
        $territories = $this->territoryPerformance($pdo, $from, $to);
        $aging = $this->receivablesAging($pdo);
        $requests = $this->requestPerformance($pdo, $from, $to);
        $referrals = $this->referralPerformance($pdo, $from, $to);
        $satisfaction = $this->satisfaction($pdo, $from, $to);

        View::render('portal/business-intelligence/index', [
            'title' => 'Business Intelligence',
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'monthly' => $monthly,
            'services' => $services,
            'crews' => $crews,
            'territories' => $territories,
            'aging' => $aging,
            'requests' => $requests,
            'referrals' => $referrals,
            'satisfaction' => $satisfaction,
        ], 'portal');
    }

    public function export(): void
    {
        Auth::requireRole('owner', 'administrator', 'office');

        $pdo = Database::connection();
        [$from, $to] = $this->dateRange();
        $report = (string) ($_GET['report'] ?? 'service-performance');

        $definitions = [
            'service-performance' => [
                'filename' => 'service-performance',
                'headers' => ['Service', 'Jobs', 'Revenue', 'Average Ticket'],
                'rows' => $this->servicePerformance($pdo, $from, $to),
                'fields' => ['service', 'jobs', 'revenue', 'average_ticket'],
            ],
            'crew-performance' => [
                'filename' => 'crew-performance',
                'headers' => ['Crew', 'Jobs', 'Revenue', 'Labor Hours', 'Labor Cost', 'Material Cost', 'Contribution'],
                'rows' => $this->crewPerformance($pdo, $from, $to),
                'fields' => ['crew', 'jobs', 'revenue', 'labor_hours', 'labor_cost', 'material_cost', 'contribution'],
            ],
            'territory-performance' => [
                'filename' => 'territory-performance',
                'headers' => ['ZIP Code', 'Customers', 'Jobs', 'Revenue'],
                'rows' => $this->territoryPerformance($pdo, $from, $to),
                'fields' => ['postal_code', 'customers', 'jobs', 'revenue'],
            ],
            'receivables-aging' => [
                'filename' => 'receivables-aging',
                'headers' => ['Bucket', 'Invoices', 'Balance'],
                'rows' => $this->receivablesAging($pdo),
                'fields' => ['bucket', 'invoices', 'balance'],
            ],
        ];

        if (!isset($definitions[$report])) {
            http_response_code(404);
            exit('Unknown report.');
        }

        $definition = $definitions[$report];
        $filename = $definition['filename'] . '-' . $from . '-to-' . $to . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'wb');
        fputcsv($output, $definition['headers']);

        foreach ($definition['rows'] as $row) {
            $values = [];
            foreach ($definition['fields'] as $field) {
                $values[] = $row[$field] ?? '';
            }
            fputcsv($output, $values);
        }

        fclose($output);
    }

    private function dateRange(): array
    {
        $from = (string) ($_GET['from'] ?? date('Y-01-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));

        if (!$this->validDate($from)) {
            $from = date('Y-01-01');
        }
        if (!$this->validDate($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function summary(PDO $pdo, string $from, string $to): array
    {
        $invoice = $pdo->prepare(
            "SELECT COUNT(*) invoice_count,
                    COALESCE(SUM(total),0) invoiced,
                    COALESCE(AVG(total),0) average_ticket,
                    COALESCE(SUM(balance_due),0) outstanding
             FROM invoices
             WHERE issue_date BETWEEN ? AND ? AND status <> 'void'"
        );
        $invoice->execute([$from, $to]);
        $summary = $invoice->fetch() ?: [];

        $payments = $pdo->prepare(
            "SELECT COALESCE(SUM(p.amount),0)
             FROM payments p
             WHERE DATE(p.received_at) BETWEEN ? AND ?"
        );
        $payments->execute([$from, $to]);
        $summary['collected'] = (float) $payments->fetchColumn();

        $estimate = $pdo->prepare(
            "SELECT COUNT(*) total,
                    SUM(status='accepted') accepted,
                    COALESCE(SUM(total),0) quoted_value,
                    COALESCE(SUM(CASE WHEN status='accepted' THEN total ELSE 0 END),0) accepted_value
             FROM estimates
             WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $estimate->execute([$from, $to]);
        $estimateData = $estimate->fetch() ?: [];
        $estimateTotal = (int) ($estimateData['total'] ?? 0);
        $summary['estimate_count'] = $estimateTotal;
        $summary['estimate_conversion'] = $estimateTotal > 0
            ? round(((int) $estimateData['accepted'] / $estimateTotal) * 100, 1)
            : 0.0;
        $summary['quoted_value'] = (float) ($estimateData['quoted_value'] ?? 0);
        $summary['accepted_value'] = (float) ($estimateData['accepted_value'] ?? 0);

        $repeat = $pdo->prepare(
            "SELECT COUNT(*) total_customers,
                    SUM(job_count > 1) repeat_customers
             FROM (
                 SELECT c.id, COUNT(j.id) job_count
                 FROM customers c
                 LEFT JOIN jobs j ON j.customer_id=c.id AND j.status='completed'
                 WHERE c.created_at <= CONCAT(?, ' 23:59:59')
                 GROUP BY c.id
             ) customer_jobs"
        );
        $repeat->execute([$to]);
        $repeatData = $repeat->fetch() ?: [];
        $totalCustomers = (int) ($repeatData['total_customers'] ?? 0);
        $summary['repeat_customer_rate'] = $totalCustomers > 0
            ? round(((int) $repeatData['repeat_customers'] / $totalCustomers) * 100, 1)
            : 0.0;

        $recurring = $pdo->query("SELECT COUNT(*) FROM recurring_services WHERE active=1");
        $summary['active_recurring'] = (int) $recurring->fetchColumn();

        $cost = $pdo->prepare(
            "SELECT COALESCE(SUM(labor_cost),0) labor_cost,
                    COALESCE(SUM(material_cost),0) material_cost
             FROM (
                 SELECT j.id,
                        COALESCE((SELECT SUM(GREATEST(TIMESTAMPDIFF(MINUTE,t.clock_in,t.clock_out)-t.break_minutes,0)/60 * COALESCE(t.hourly_cost,0)) FROM time_entries t WHERE t.job_id=j.id AND t.clock_out IS NOT NULL),0) labor_cost,
                        COALESCE((SELECT SUM(ju.quantity*ju.unit_cost) FROM job_inventory_usage ju WHERE ju.job_id=j.id),0) material_cost
                 FROM jobs j
                 WHERE DATE(COALESCE(j.scheduled_start,j.created_at)) BETWEEN ? AND ?
             ) costs"
        );
        $cost->execute([$from, $to]);
        $costData = $cost->fetch() ?: [];
        $summary['labor_cost'] = (float) ($costData['labor_cost'] ?? 0);
        $summary['material_cost'] = (float) ($costData['material_cost'] ?? 0);
        $summary['contribution'] = (float) ($summary['invoiced'] ?? 0) - $summary['labor_cost'] - $summary['material_cost'];
        $summary['contribution_margin'] = (float) ($summary['invoiced'] ?? 0) > 0
            ? round(($summary['contribution'] / (float) $summary['invoiced']) * 100, 1)
            : 0.0;

        return $summary;
    }

    private function monthlyTrend(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT months.month,
                    COALESCE(inv.invoiced,0) invoiced,
                    COALESCE(pay.collected,0) collected
             FROM (
                 SELECT DATE_FORMAT(DATE_ADD(?, INTERVAL seq.n MONTH), '%Y-%m') month
                 FROM (
                     SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
                     UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11
                     UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17
                     UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23
                 ) seq
                 WHERE DATE_ADD(?, INTERVAL seq.n MONTH) <= ?
             ) months
             LEFT JOIN (
                 SELECT DATE_FORMAT(issue_date,'%Y-%m') month, SUM(total) invoiced
                 FROM invoices
                 WHERE issue_date BETWEEN ? AND ? AND status <> 'void'
                 GROUP BY DATE_FORMAT(issue_date,'%Y-%m')
             ) inv ON inv.month=months.month
             LEFT JOIN (
                 SELECT DATE_FORMAT(received_at,'%Y-%m') month, SUM(amount) collected
                 FROM payments
                 WHERE DATE(received_at) BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(received_at,'%Y-%m')
             ) pay ON pay.month=months.month
             ORDER BY months.month"
        );
        $monthStart = substr($from, 0, 7) . '-01';
        $stmt->execute([$monthStart, $monthStart, $to, $from, $to, $from, $to]);
        return $stmt->fetchAll();
    }

    private function servicePerformance(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(j.service_summary,''),'Other') service,
                    COUNT(*) jobs,
                    COALESCE(SUM(inv.revenue),0) revenue,
                    CASE WHEN COUNT(*)>0 THEN COALESCE(SUM(inv.revenue),0)/COUNT(*) ELSE 0 END average_ticket
             FROM jobs j
             LEFT JOIN (
                 SELECT job_id, SUM(total) revenue
                 FROM invoices
                 WHERE status <> 'void'
                 GROUP BY job_id
             ) inv ON inv.job_id=j.id
             WHERE DATE(COALESCE(j.scheduled_start,j.created_at)) BETWEEN ? AND ?
             GROUP BY COALESCE(NULLIF(j.service_summary,''),'Other')
             ORDER BY revenue DESC, jobs DESC
             LIMIT 20"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    private function crewPerformance(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(c.name,u.name,'Unassigned') crew,
                    COUNT(*) jobs,
                    COALESCE(SUM(inv.revenue),0) revenue,
                    COALESCE(SUM(te.labor_hours),0) labor_hours,
                    COALESCE(SUM(te.labor_cost),0) labor_cost,
                    COALESCE(SUM(mat.material_cost),0) material_cost
             FROM jobs j
             LEFT JOIN crews c ON c.id=j.crew_id
             LEFT JOIN users u ON u.id=j.assigned_user_id
             LEFT JOIN (
                 SELECT job_id, SUM(total) revenue
                 FROM invoices
                 WHERE status <> 'void'
                 GROUP BY job_id
             ) inv ON inv.job_id=j.id
             LEFT JOIN (
                 SELECT job_id,
                        SUM(GREATEST(TIMESTAMPDIFF(MINUTE,clock_in,clock_out)-break_minutes,0))/60 labor_hours,
                        SUM(GREATEST(TIMESTAMPDIFF(MINUTE,clock_in,clock_out)-break_minutes,0)/60*COALESCE(hourly_cost,0)) labor_cost
                 FROM time_entries
                 WHERE clock_out IS NOT NULL
                 GROUP BY job_id
             ) te ON te.job_id=j.id
             LEFT JOIN (
                 SELECT job_id, SUM(quantity*unit_cost) material_cost
                 FROM job_inventory_usage
                 GROUP BY job_id
             ) mat ON mat.job_id=j.id
             WHERE DATE(COALESCE(j.scheduled_start,j.created_at)) BETWEEN ? AND ?
             GROUP BY COALESCE(c.name,u.name,'Unassigned')
             ORDER BY revenue DESC
             LIMIT 20"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['contribution'] = (float) $row['revenue'] - (float) $row['labor_cost'] - (float) $row['material_cost'];
        }
        unset($row);
        return $rows;
    }

    private function territoryPerformance(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(p.postal_code,''),'Unknown') postal_code,
                    COUNT(DISTINCT p.customer_id) customers,
                    COUNT(DISTINCT j.id) jobs,
                    COALESCE(SUM(i.total),0) revenue
             FROM properties p
             LEFT JOIN jobs j ON j.property_id=p.id AND DATE(COALESCE(j.scheduled_start,j.created_at)) BETWEEN ? AND ?
             LEFT JOIN invoices i ON i.job_id=j.id AND i.status <> 'void'
             GROUP BY COALESCE(NULLIF(p.postal_code,''),'Unknown')
             HAVING jobs > 0 OR revenue > 0
             ORDER BY revenue DESC, jobs DESC
             LIMIT 25"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    private function receivablesAging(PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT CASE
                        WHEN due_date IS NULL OR due_date >= CURDATE() THEN 'Current'
                        WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 1 AND 30 THEN '1-30 days'
                        WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 31 AND 60 THEN '31-60 days'
                        WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 61 AND 90 THEN '61-90 days'
                        ELSE '90+ days'
                    END bucket,
                    COUNT(*) invoices,
                    COALESCE(SUM(balance_due),0) balance
             FROM invoices
             WHERE balance_due > 0 AND status <> 'void'
             GROUP BY bucket"
        )->fetchAll();

        $order = ['Current' => 0, '1-30 days' => 1, '31-60 days' => 2, '61-90 days' => 3, '90+ days' => 4];
        usort($rows, static fn(array $a, array $b): int => ($order[$a['bucket']] ?? 99) <=> ($order[$b['bucket']] ?? 99));
        return $rows;
    }

    private function requestPerformance(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) total,
                    SUM(status IN ('quoted','scheduled','completed')) converted,
                    SUM(status='new') new_requests,
                    SUM(status='completed') completed
             FROM customer_service_requests
             WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        $row = $stmt->fetch() ?: [];
        $total = (int) ($row['total'] ?? 0);
        $row['conversion_rate'] = $total > 0 ? round(((int) $row['converted'] / $total) * 100, 1) : 0.0;
        return $row;
    }

    private function referralPerformance(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) total,
                    SUM(status IN ('converted','credited')) converted,
                    SUM(status='credited') credited
             FROM customer_referrals
             WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        $row = $stmt->fetch() ?: [];
        $total = (int) ($row['total'] ?? 0);
        $row['conversion_rate'] = $total > 0 ? round(((int) $row['converted'] / $total) * 100, 1) : 0.0;
        return $row;
    }

    private function satisfaction(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) responses,
                    COALESCE(AVG(rating),0) average_rating,
                    SUM(recommend_score >= 9) promoters,
                    SUM(recommend_score BETWEEN 7 AND 8) passives,
                    SUM(recommend_score <= 6) detractors,
                    SUM(recommend_score IS NOT NULL) nps_responses
             FROM customer_satisfaction_surveys
             WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        $row = $stmt->fetch() ?: [];
        $npsResponses = (int) ($row['nps_responses'] ?? 0);
        $row['nps'] = $npsResponses > 0
            ? round((((int) $row['promoters'] - (int) $row['detractors']) / $npsResponses) * 100)
            : null;
        return $row;
    }
}
