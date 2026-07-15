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
        $budget = (float) ($settings['monthly_cost_limit_usd'] ?? 0);
        if ($budget <= 0) {
            return;
        }
        $stmt = $this->pdo->query("SELECT COALESCE(SUM(estimated_cost_usd),0) FROM ai_usage_logs WHERE status='success' AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
        $spent = (float) $stmt->fetchColumn();
        if ($spent >= $budget) {
            throw new RuntimeException('The monthly AI cost limit has been reached.');
        }
    }
}
