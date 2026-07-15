<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AiContextService;
use App\Services\AiCostService;
use App\Services\AiGovernanceService;
use App\Services\AiDraftApplicationService;
use App\Services\AiPromptHistoryService;
use App\Services\AiUsageReportService;
use App\Services\AiOperationalService;
use App\Services\AiProviderService;
use App\Services\AuditService;
use Throwable;

final class AiAssistantController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $provider = new AiProviderService($pdo);
        $usage = $prompts = $drafts = $providerUsage = $userUsage = $promptHistory = [];
        $usageSummary = [];
        try {
            $usage = $pdo->query("SELECT l.*,u.name user_name FROM ai_usage_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 25")->fetchAll();
            $prompts = $pdo->query("SELECT * FROM ai_saved_prompts WHERE active=1 ORDER BY name")->fetchAll();
            $drafts = $pdo->query("SELECT d.*,u.name created_by_name,a.name approved_by_name,r.name rejected_by_name FROM ai_generated_drafts d LEFT JOIN users u ON u.id=d.created_by LEFT JOIN users a ON a.id=d.approved_by LEFT JOIN users r ON r.id=d.rejected_by ORDER BY d.created_at DESC LIMIT 30")->fetchAll();
            $reports = new AiUsageReportService($pdo);
            $usageSummary = $reports->summary();
            $providerUsage = $reports->byProvider();
            $userUsage = $reports->byUser();
            $promptHistory = $reports->promptHistory();
        } catch (Throwable) {
        }
        View::render('portal/ai/index', [
            'title' => 'AI Assistant',
            'settings' => $provider->settings(),
            'health' => $provider->healthCheck(),
            'usage' => $usage,
            'prompts' => $prompts,
            'drafts' => $drafts,
            'usageSummary' => $usageSummary,
            'providerUsage' => $providerUsage,
            'userUsage' => $userUsage,
            'promptHistory' => $promptHistory,
            'answer' => $_SESSION['ai_answer'] ?? null,
            'lastPrompt' => $_SESSION['ai_prompt'] ?? '',
        ], 'portal');
        unset($_SESSION['ai_answer'], $_SESSION['ai_prompt']);
    }

    public function ask(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($prompt === '' || mb_strlen($prompt) > 8000) {
            flash('danger', 'Enter a prompt between 1 and 8,000 characters.');
            redirect('/portal/ai');
        }
        $pdo = Database::connection();
        try {
            $governance = new AiGovernanceService($pdo);
            $governance->assertUserAllowed();
            $governance->assertMonthlyBudgetAvailable();
            $provider = new AiProviderService($pdo);
            $context = isset($_POST['include_context']) ? (new AiContextService($pdo))->operationalSummary() : [];
            $result = $provider->ask($prompt, 'operations_assistant', $context);
            $this->storeUsage($result, 'operations_assistant', $prompt);
            $_SESSION['ai_answer'] = $result['content'];
            $_SESSION['ai_prompt'] = $prompt;
        } catch (Throwable $e) {
            flash('danger', 'AI request failed: ' . $e->getMessage());
        }
        redirect('/portal/ai');
    }

    public function dailyBrief(): void
    {
        Auth::requireRole('owner', 'administrator', 'office');
        verify_csrf();
        $pdo = Database::connection();
        try {
            $governance = new AiGovernanceService($pdo);
            $governance->assertUserAllowed();
            $governance->assertMonthlyBudgetAvailable();
            $result = (new AiOperationalService($pdo, new AiProviderService($pdo)))->generateDailyBrief();
            $this->storeUsage($result, 'daily_operations_brief', 'daily brief');
            $_SESSION['ai_answer'] = $result['content'];
            $_SESSION['ai_prompt'] = 'Daily operations brief';
        } catch (Throwable $e) {
            flash('danger', 'AI request failed: ' . $e->getMessage());
        }
        redirect('/portal/ai');
    }

    public function search(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        header('Content-Type: application/json');
        try {
            (new AiGovernanceService($pdo))->assertUserAllowed();
            $query = trim((string) ($_GET['q'] ?? ''));
            $service = new AiOperationalService($pdo, new AiProviderService($pdo));
            echo json_encode(['query' => $query, 'results' => $service->operationalSearch($query)], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(403);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createDraft(): void
    {
        Auth::requireRole('owner', 'administrator', 'office', 'estimator');
        verify_csrf();
        $type = (string) ($_POST['draft_type'] ?? '');
        if (!in_array($type, ['estimate', 'customer_reply', 'route_recommendation', 'staffing_recommendation'], true)) {
            flash('danger', 'Unsupported AI draft type.');
            redirect('/portal/ai');
        }
        $record = [
            'reference' => trim((string) ($_POST['reference'] ?? '')),
            'details' => trim((string) ($_POST['details'] ?? '')),
            'target_type' => trim((string) ($_POST['target_type'] ?? '')),
            'target_id' => max(0, (int) ($_POST['target_id'] ?? 0)),
        ];
        $pdo = Database::connection();
        try {
            $governance = new AiGovernanceService($pdo);
            $governance->assertUserAllowed();
            $governance->assertMonthlyBudgetAvailable();
            $result = (new AiOperationalService($pdo, new AiProviderService($pdo)))->draft($type, $record, trim((string) ($_POST['instruction'] ?? '')));
            $requiresApproval = $governance->requiresApproval($type) ? 1 : 0;
            $status = $requiresApproval ? 'draft' : 'approved';
            $stmt = $pdo->prepare("INSERT INTO ai_generated_drafts(draft_type,reference_key,content,status,requires_approval,created_by,approved_by,approved_at,source_target_type,source_target_id,created_at) VALUES(?,?,?,?,?,?,IF(?=0,?,NULL),IF(?=0,NOW(),NULL),?,?,NOW())");
            $stmt->execute([$type, $record['reference'], $result['content'], $status, $requiresApproval, Auth::id(), $requiresApproval, Auth::id(), $requiresApproval, $record['target_type'] !== '' ? $record['target_type'] : null, $record['target_id'] > 0 ? $record['target_id'] : null]);
            $this->storeUsage($result, $type, $record['reference']);
            $_SESSION['ai_answer'] = $result['content'];
            $_SESSION['ai_prompt'] = 'Draft: ' . $type;
            AuditService::log('ai.draft_created', 'ai_generated_draft', (int) $pdo->lastInsertId());
            flash('success', $requiresApproval ? 'AI draft created and queued for approval.' : 'AI draft created and pre-approved by policy.');
        } catch (Throwable $e) {
            flash('danger', 'AI draft failed: ' . $e->getMessage());
        }
        $returnTo = (string) ($_POST['return_to'] ?? '/portal/ai');
        redirect(str_starts_with($returnTo, '/portal/') ? $returnTo : '/portal/ai');
    }

    public function approveDraft(int $id): void
    {
        Auth::requireRole('owner', 'administrator', 'office');
        verify_csrf();
        Database::connection()->prepare("UPDATE ai_generated_drafts SET status='approved',approved_by=?,approved_at=NOW(),rejected_by=NULL,rejected_at=NULL,rejection_reason=NULL WHERE id=? AND status='draft'")->execute([Auth::id(), $id]);
        AuditService::log('ai.draft_approved', 'ai_generated_draft', $id);
        flash('success', 'AI draft approved.');
        redirect('/portal/ai');
    }

    public function rejectDraft(int $id): void
    {
        Auth::requireRole('owner', 'administrator', 'office');
        verify_csrf();
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 1000);
        Database::connection()->prepare("UPDATE ai_generated_drafts SET status='rejected',rejected_by=?,rejected_at=NOW(),rejection_reason=? WHERE id=? AND status IN ('draft','approved')")->execute([Auth::id(), $reason, $id]);
        AuditService::log('ai.draft_rejected', 'ai_generated_draft', $id);
        flash('success', 'AI draft rejected.');
        redirect('/portal/ai');
    }

    public function useDraft(int $id): void
    {
        Auth::requireRole('owner', 'administrator', 'office', 'estimator');
        verify_csrf();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM ai_generated_drafts WHERE id=?');
        $stmt->execute([$id]);
        $draft = $stmt->fetch();
        if (!is_array($draft)) {
            flash('danger', 'AI draft not found.');
            redirect('/portal/ai');
        }
        if ((int) $draft['requires_approval'] === 1 && $draft['status'] !== 'approved') {
            flash('danger', 'This draft must be approved before it can be applied.');
            redirect('/portal/ai');
        }
        if (in_array($draft['status'], ['rejected', 'used'], true)) {
            flash('danger', 'This draft cannot be used in its current state.');
            redirect('/portal/ai');
        }
        $targetType = (string) ($_POST['target_type'] ?? '');
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $humanReviewed = isset($_POST['human_reviewed']);
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 1000);
        $pdo->beginTransaction();
        try {
            $applied = (new AiDraftApplicationService($pdo))->apply($draft, $targetType, $targetId, $humanReviewed);
            $pdo->prepare("UPDATE ai_generated_drafts SET status='used',used_by=?,used_at=NOW(),use_target_type=?,use_target_id=?,human_reviewed_by=?,human_reviewed_at=NOW() WHERE id=?")
                ->execute([Auth::id(), $targetType, $targetId, Auth::id(), $id]);
            $pdo->prepare('INSERT INTO ai_draft_use_events(draft_id,target_type,target_id,notes,used_by,used_at) VALUES(?,?,?,?,?,NOW())')
                ->execute([$id, $targetType, $targetId, $notes, Auth::id()]);
            $pdo->prepare('INSERT INTO ai_draft_application_events(draft_id,target_type,target_id,before_content,after_content,human_reviewed_by,applied_by,applied_at) VALUES(?,?,?,?,?,?,?,NOW())')
                ->execute([$id, $targetType, $targetId, $applied['before_content'], $applied['after_content'], Auth::id(), Auth::id()]);
            $pdo->commit();
            AuditService::log('ai.draft_applied', $targetType, $targetId, ['draft_id' => $id]);
            flash('success', 'Approved AI draft applied after human review.');
            redirect($targetType === 'estimate' ? '/portal/estimates/' . $targetId : '/portal/conversations/' . $targetId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Unable to apply AI draft: ' . $e->getMessage());
            redirect('/portal/ai');
        }
    }

    public function updateSettings(): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        $provider = (string) ($_POST['provider'] ?? 'disabled');
        if (!in_array($provider, ['disabled', 'openai', 'azure_openai', 'ollama'], true)) {
            $provider = 'disabled';
        }
        Database::connection()->prepare("UPDATE ai_provider_settings SET enabled=?,provider=?,model=?,endpoint=?,temperature=?,max_tokens=?,daily_request_limit=?,monthly_request_limit=?,monthly_cost_limit_usd=?,input_cost_per_million_tokens=?,output_cost_per_million_tokens=?,allowed_roles=?,redact_sensitive_data=?,approval_required_estimate=?,approval_required_customer_reply=?,approval_required_other=?,system_prompt=?,updated_by=?,updated_at=NOW() WHERE id=1")->execute([
            isset($_POST['enabled']) ? 1 : 0,
            $provider,
            trim((string) ($_POST['model'] ?? '')),
            trim((string) ($_POST['endpoint'] ?? '')),
            max(0, min(2, (float) ($_POST['temperature'] ?? 0.2))),
            max(64, min(8000, (int) ($_POST['max_tokens'] ?? 800))),
            max(0, (int) ($_POST['daily_request_limit'] ?? 0)),
            max(0, (int) ($_POST['monthly_request_limit'] ?? 0)),
            max(0, (float) ($_POST['monthly_cost_limit_usd'] ?? 0)),
            max(0, (float) ($_POST['input_cost_per_million_tokens'] ?? 0)),
            max(0, (float) ($_POST['output_cost_per_million_tokens'] ?? 0)),
            trim((string) ($_POST['allowed_roles'] ?? 'owner,administrator,office,estimator')),
            isset($_POST['redact_sensitive_data']) ? 1 : 0,
            isset($_POST['approval_required_estimate']) ? 1 : 0,
            isset($_POST['approval_required_customer_reply']) ? 1 : 0,
            isset($_POST['approval_required_other']) ? 1 : 0,
            trim((string) ($_POST['system_prompt'] ?? '')),
            Auth::id(),
        ]);
        flash('success', 'AI governance and provider settings updated.');
        redirect('/portal/ai');
    }

    public function savePrompt(): void
    {
        Auth::requireRole('owner', 'administrator', 'office');
        verify_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $prompt = trim((string) ($_POST['prompt_template'] ?? ''));
        if ($name === '' || $prompt === '') {
            flash('danger', 'Prompt name and template are required.');
            redirect('/portal/ai');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id,prompt_template,version FROM ai_saved_prompts WHERE name=?');
        $stmt->execute([$name]);
        $existing = $stmt->fetch();
        if (is_array($existing)) {
            (new AiPromptHistoryService($pdo))->saveVersion((int) $existing['id'], (string) $existing['prompt_template'], (int) $existing['version']);
            $pdo->prepare('UPDATE ai_saved_prompts SET prompt_template=?,version=version+1,updated_at=NOW() WHERE id=?')->execute([$prompt, (int) $existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO ai_saved_prompts(name,prompt_template,version,created_by,created_at,updated_at) VALUES(?,?,1,?,NOW(),NOW())')->execute([$name, $prompt, Auth::id()]);
        }
        flash('success', 'Prompt saved with version history.');
        redirect('/portal/ai');
    }

    public function rollbackPrompt(int $id): void
    {
        Auth::requireRole('owner', 'administrator', 'office');
        verify_csrf();
        $version = max(1, (int) ($_POST['version'] ?? 0));
        try {
            (new AiPromptHistoryService(Database::connection()))->rollback($id, $version);
            AuditService::log('ai.prompt_rollback', 'ai_saved_prompt', $id, ['version' => $version]);
            flash('success', 'Prompt restored from version ' . $version . '.');
        } catch (Throwable $e) {
            flash('danger', 'Prompt rollback failed: ' . $e->getMessage());
        }
        redirect('/portal/ai');
    }

    public function updateBudget(int $id): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        $pdo = Database::connection();
        $pdo->prepare("INSERT INTO ai_user_budgets(user_id,daily_request_limit,monthly_request_limit,monthly_cost_limit_usd,updated_by,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE daily_request_limit=VALUES(daily_request_limit),monthly_request_limit=VALUES(monthly_request_limit),monthly_cost_limit_usd=VALUES(monthly_cost_limit_usd),updated_by=VALUES(updated_by),updated_at=NOW()")
            ->execute([
                $id,
                max(0, (int) ($_POST['daily_request_limit'] ?? 0)),
                max(0, (int) ($_POST['monthly_request_limit'] ?? 0)),
                max(0, (float) ($_POST['monthly_cost_limit_usd'] ?? 0)),
                Auth::id(),
            ]);
        AuditService::log('ai.user_budget_updated', 'user', $id);
        flash('success', 'AI budget updated.');
        redirect('/portal/ai');
    }

    private function storeUsage(array $result, string $purpose, string $prompt): void
    {
        $pdo = Database::connection();
        $settings = (new AiGovernanceService($pdo))->settings();
        $cost = (new AiCostService())->estimate((int) $result['input_chars'], (int) $result['output_chars'], $settings);
        $pdo->prepare("INSERT INTO ai_usage_logs(user_id,provider,model,purpose,prompt_hash,input_chars,output_chars,estimated_cost_usd,status,latency_ms,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([Auth::id(), $result['provider'], $result['model'], $purpose, hash('sha256', $prompt), $result['input_chars'], $result['output_chars'], $cost, 'success', $result['latency_ms']]);
    }
}
