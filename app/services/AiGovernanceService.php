<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

final class AiGovernanceService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        $row = $this->pdo->query("SELECT * FROM ai_provider_settings WHERE id=1")->fetch();
        return is_array($row) ? $row : [];
    }

    public function assertUserAllowed(): void
    {
        $settings = $this->settings();
        $roles = array_values(array_filter(array_map('trim', explode(',', (string) ($settings['allowed_roles'] ?? 'owner,administrator,office,estimator')))));
        if ($roles === [] || !Auth::can(...$roles)) {
            throw new RuntimeException('Your portal role is not authorized to use the AI Assistant.');
        }
    }

    public function requiresApproval(string $draftType): bool
    {
        $settings = $this->settings();
        return match ($draftType) {
            'estimate' => (bool) ($settings['approval_required_estimate'] ?? true),
            'customer_reply' => (bool) ($settings['approval_required_customer_reply'] ?? true),
            default => (bool) ($settings['approval_required_other'] ?? false),
        };
    }

    public function assertMonthlyBudgetAvailable(): void
    {
        $settings = $this->settings();
        $this->assertLimits(
            (int) ($settings['daily_request_limit'] ?? 0),
            (int) ($settings['monthly_request_limit'] ?? 0),
            (float) ($settings['monthly_cost_limit_usd'] ?? 0),
            null
        );

        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('An authenticated user is required.');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM ai_user_budgets WHERE user_id=?');
        $stmt->execute([$userId]);
        $budget = $stmt->fetch();
        if (is_array($budget)) {
            $this->assertLimits(
                (int) $budget['daily_request_limit'],
                (int) $budget['monthly_request_limit'],
                (float) $budget['monthly_cost_limit_usd'],
                $userId
            );
        }
    }

    private function assertLimits(int $dailyLimit, int $monthlyLimit, float $costLimit, ?int $userId): void
    {
        $where = $userId === null ? '' : ' AND user_id=' . $userId;

        if ($dailyLimit > 0) {
            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE created_at>=CURDATE(){$where}")->fetchColumn();
            if ($count >= $dailyLimit) {
                throw new RuntimeException($userId === null ? 'The daily AI request limit has been reached.' : 'Your daily AI request limit has been reached.');
            }
        }

        if ($monthlyLimit > 0) {
            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01'){$where}")->fetchColumn();
            if ($count >= $monthlyLimit) {
                throw new RuntimeException($userId === null ? 'The monthly AI request limit has been reached.' : 'Your monthly AI request limit has been reached.');
            }
        }

        if ($costLimit > 0) {
            $spent = (float) $this->pdo->query("SELECT COALESCE(SUM(estimated_cost_usd),0) FROM ai_usage_logs WHERE status='success' AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01'){$where}")->fetchColumn();
            if ($spent >= $costLimit) {
                throw new RuntimeException($userId === null ? 'The monthly AI cost limit has been reached.' : 'Your monthly AI cost limit has been reached.');
            }
        }
    }
}
