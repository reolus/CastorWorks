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
        $row = $this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch();
        return is_array($row) ? $row : [];
    }

    public function assertUserAllowed(): void
    {
        $roles = array_values(array_filter(array_map('trim', explode(',', (string) ($this->settings()['allowed_roles'] ?? 'owner,administrator,office,estimator')))));
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
        $this->assertLimits((int) ($settings['daily_request_limit'] ?? 0), (int) ($settings['monthly_request_limit'] ?? 0), (float) ($settings['monthly_cost_limit_usd'] ?? 0), null);

        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('An authenticated user is required.');
        }

        $user = $this->pdo->prepare('SELECT daily_request_limit,monthly_request_limit,monthly_cost_limit_usd FROM ai_user_budgets WHERE user_id=?');
        $user->execute([$userId]);
        $budget = $user->fetch();
        if (is_array($budget)) {
            $this->assertLimits((int) $budget['daily_request_limit'], (int) $budget['monthly_request_limit'], (float) $budget['monthly_cost_limit_usd'], $userId);
        }

        $role = Auth::role();
        $roleStmt = $this->pdo->prepare('SELECT daily_request_limit,monthly_request_limit,monthly_cost_limit_usd FROM ai_role_budgets WHERE role_name=? AND active=1');
        $roleStmt->execute([$role]);
        $roleBudget = $roleStmt->fetch();
        if (is_array($roleBudget)) {
            $this->assertLimits((int) $roleBudget['daily_request_limit'], (int) $roleBudget['monthly_request_limit'], (float) $roleBudget['monthly_cost_limit_usd'], null, $role);
        }
    }

    private function assertLimits(int $dailyLimit, int $monthlyLimit, float $costLimit, ?int $userId, ?string $role = null): void
    {
        $where = '';
        $params = [];
        if ($userId !== null) {
            $where = ' AND user_id=?';
            $params[] = $userId;
        } elseif ($role !== null) {
            $where = ' AND user_id IN (SELECT id FROM users WHERE role=?)';
            $params[] = $role;
        }

        if ($dailyLimit > 0) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ai_usage_logs WHERE created_at>=CURDATE(){$where}");
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() >= $dailyLimit) {
                throw new RuntimeException($role !== null ? 'The AI daily request limit for your role has been reached.' : ($userId === null ? 'The daily AI request limit has been reached.' : 'Your daily AI request limit has been reached.'));
            }
        }
        if ($monthlyLimit > 0) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ai_usage_logs WHERE created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01'){$where}");
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() >= $monthlyLimit) {
                throw new RuntimeException($role !== null ? 'The AI monthly request limit for your role has been reached.' : ($userId === null ? 'The monthly AI request limit has been reached.' : 'Your monthly AI request limit has been reached.'));
            }
        }
        if ($costLimit > 0) {
            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(estimated_cost_usd),0) FROM ai_usage_logs WHERE status='success' AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01'){$where}");
            $stmt->execute($params);
            if ((float) $stmt->fetchColumn() >= $costLimit) {
                throw new RuntimeException($role !== null ? 'The AI monthly cost limit for your role has been reached.' : ($userId === null ? 'The monthly AI cost limit has been reached.' : 'Your monthly AI cost limit has been reached.'));
            }
        }
    }
}
