<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AiUsageReportService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function summary(): array
    {
        $month = $this->pdo->query(
            "SELECT COUNT(*) requests,
                    COALESCE(SUM(status='success'),0) successful,
                    COALESCE(SUM(status<>'success'),0) failed,
                    COALESCE(SUM(estimated_cost_usd),0) estimated_cost,
                    COALESCE(AVG(latency_ms),0) average_latency_ms
             FROM ai_usage_logs
             WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')"
        )->fetch();

        return is_array($month) ? $month : [];
    }

    /** @return list<array<string,mixed>> */
    public function byProvider(): array
    {
        return $this->pdo->query(
            "SELECT provider, model,
                    COUNT(*) requests,
                    COALESCE(SUM(status='success'),0) successful,
                    COALESCE(SUM(status<>'success'),0) failed,
                    COALESCE(SUM(input_chars),0) input_chars,
                    COALESCE(SUM(output_chars),0) output_chars,
                    COALESCE(SUM(estimated_cost_usd),0) estimated_cost,
                    ROUND(AVG(latency_ms)) average_latency_ms
             FROM ai_usage_logs
             WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
             GROUP BY provider, model
             ORDER BY requests DESC, provider, model"
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function byUser(): array
    {
        return $this->pdo->query(
            "SELECT u.id user_id, u.name, u.email, u.role,
                    COUNT(l.id) requests,
                    COALESCE(SUM(l.status='success'),0) successful,
                    COALESCE(SUM(l.estimated_cost_usd),0) estimated_cost,
                    b.daily_request_limit,
                    b.monthly_request_limit,
                    b.monthly_cost_limit_usd
             FROM users u
             LEFT JOIN ai_usage_logs l
               ON l.user_id=u.id
              AND l.created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
             LEFT JOIN ai_user_budgets b ON b.user_id=u.id
             WHERE u.status='active'
             GROUP BY u.id,u.name,u.email,u.role,
                      b.daily_request_limit,b.monthly_request_limit,b.monthly_cost_limit_usd
             ORDER BY requests DESC,u.name"
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function promptHistory(): array
    {
        return $this->pdo->query(
            "SELECT v.*,p.name current_name,u.name created_by_name
             FROM ai_prompt_versions v
             JOIN ai_saved_prompts p ON p.id=v.prompt_id
             LEFT JOIN users u ON u.id=v.created_by
             ORDER BY v.created_at DESC,v.id DESC
             LIMIT 100"
        )->fetchAll();
    }
}
