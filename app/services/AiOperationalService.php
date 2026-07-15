<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class AiOperationalService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AiProviderService $provider,
        private readonly AiRedactionService $redactor = new AiRedactionService()
    ) {
    }

    /** @return array<string,mixed> */
    public function dailyBriefContext(): array
    {
        $context = (new AiContextService($this->pdo))->operationalSummary();
        $context['routes_today'] = $this->rows("SELECT c.name crew_name,COUNT(j.id) jobs,COALESCE(SUM(j.eta_late_minutes),0) late_minutes FROM crews c LEFT JOIN jobs j ON j.crew_id=c.id AND j.route_date=CURDATE() WHERE c.status='active' GROUP BY c.id,c.name ORDER BY c.name");
        $context['receivables_aging'] = $this->rows("SELECT CASE WHEN due_date>=CURDATE() THEN 'current' WHEN due_date>=CURDATE()-INTERVAL 30 DAY THEN '1-30' WHEN due_date>=CURDATE()-INTERVAL 60 DAY THEN '31-60' ELSE '61+' END bucket,ROUND(SUM(balance_due),2) amount FROM invoices WHERE status NOT IN ('paid','void') AND balance_due>0 GROUP BY bucket");
        return $this->redactor->redactArray($context);
    }

    /** @return list<array<string,mixed>> */
    public function operationalSearch(string $query, int $limit = 25): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $like = '%' . $query . '%';
        $limit = max(1, min(50, $limit));
        $results = [];
        $definitions = [
            ['customer', "SELECT id,display_name label,email detail FROM customers WHERE display_name LIKE ? OR contact_name LIKE ? OR email LIKE ? ORDER BY display_name LIMIT {$limit}", 3],
            ['job', "SELECT id,job_number label,CONCAT(status,' · ',COALESCE(route_date,'')) detail FROM jobs WHERE job_number LIKE ? OR service_summary LIKE ? ORDER BY id DESC LIMIT {$limit}", 2],
            ['estimate', "SELECT id,estimate_number label,status detail FROM estimates WHERE estimate_number LIKE ? OR status LIKE ? ORDER BY id DESC LIMIT {$limit}", 2],
            ['invoice', "SELECT id,invoice_number label,CONCAT(status,' · $',FORMAT(balance_due,2)) detail FROM invoices WHERE invoice_number LIKE ? OR status LIKE ? ORDER BY id DESC LIMIT {$limit}", 2],
        ];
        foreach ($definitions as [$type,$sql,$count]) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_fill(0, $count, $like));
                foreach ($stmt->fetchAll() as $row) {
                    $row['type'] = $type;
                    $results[] = $row;
                }
            } catch (Throwable) {
            }
        }
        return array_slice($results, 0, $limit);
    }

    public function generateDailyBrief(): array
    {
        return $this->provider->ask(
            'Create a concise daily operations brief. Use headings for Current Position, Risks, Priorities, and Recommended Actions. Do not invent facts.',
            'daily_operations_brief',
            $this->dailyBriefContext()
        );
    }

    /** @param array<string,mixed> $record */
    public function draft(string $type, array $record, string $instruction = ''): array
    {
        $allowed = ['estimate','customer_reply','route_recommendation','staffing_recommendation'];
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException('Unsupported AI drafting type.');
        }
        $prompts = [
            'estimate' => 'Draft a professional estimate narrative using only the supplied data. Do not invent prices or scope.',
            'customer_reply' => 'Draft a courteous customer response. Do not promise dates, prices, or outcomes not supplied.',
            'route_recommendation' => 'Recommend practical route improvements using only the supplied aggregate route information.',
            'staffing_recommendation' => 'Recommend staffing adjustments using only the supplied workload and availability data.',
        ];
        $context = $this->redactor->redactArray($record);
        $prompt = $prompts[$type] . ($instruction !== '' ? "\nAdditional instruction: " . $this->redactor->redactText($instruction) : '');
        return $this->provider->ask($prompt, $type, $context);
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql): array
    {
        try {
            $rows = $this->pdo->query($sql)->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }
}
