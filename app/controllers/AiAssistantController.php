<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AiContextService;
use App\Services\AiOperationalService;
use App\Services\AiProviderService;
use App\Services\AiRedactionService;
use Throwable;

final class AiAssistantController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $service = new AiProviderService($pdo);
        $usage = $prompts = $drafts = [];
        try {
            $usage = $pdo->query("SELECT l.*,u.name user_name FROM ai_usage_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 25")->fetchAll();
            $prompts = $pdo->query("SELECT * FROM ai_saved_prompts WHERE active=1 ORDER BY name")->fetchAll();
            $drafts = $pdo->query("SELECT d.*,u.name created_by_name FROM ai_generated_drafts d LEFT JOIN users u ON u.id=d.created_by ORDER BY d.created_at DESC LIMIT 20")->fetchAll();
        } catch (Throwable) {
        }
        View::render('portal/ai/index', [
            'title' => 'AI Assistant', 'settings' => $service->settings(), 'health' => $service->healthCheck(),
            'usage' => $usage, 'prompts' => $prompts, 'drafts' => $drafts,
            'answer' => $_SESSION['ai_answer'] ?? null, 'lastPrompt' => $_SESSION['ai_prompt'] ?? '',
        ], 'portal');
        unset($_SESSION['ai_answer'], $_SESSION['ai_prompt']);
    }

    public function ask(): void
    {
        Auth::requireLogin(); verify_csrf();
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($prompt === '' || mb_strlen($prompt) > 8000) { flash('danger','Enter a prompt between 1 and 8,000 characters.'); redirect('/portal/ai'); }
        $pdo = Database::connection(); $service = new AiProviderService($pdo);
        $context = isset($_POST['include_context']) ? (new AiContextService($pdo))->operationalSummary() : [];
        $this->executeAndLog($service, $prompt, 'operations_assistant', $context);
        redirect('/portal/ai');
    }

    public function dailyBrief(): void
    {
        Auth::requireRole('owner','administrator','office'); verify_csrf();
        $pdo = Database::connection(); $provider = new AiProviderService($pdo);
        try {
            $result = (new AiOperationalService($pdo, $provider))->generateDailyBrief();
            $this->storeUsage($result, 'daily_operations_brief', 'daily brief');
            $_SESSION['ai_answer'] = $result['content']; $_SESSION['ai_prompt'] = 'Daily operations brief';
        } catch (Throwable $e) { flash('danger','AI request failed: '.$e->getMessage()); }
        redirect('/portal/ai');
    }

    public function search(): void
    {
        Auth::requireLogin();
        $query = trim((string) ($_GET['q'] ?? ''));
        $service = new AiOperationalService(Database::connection(), new AiProviderService(Database::connection()));
        header('Content-Type: application/json');
        echo json_encode(['query'=>$query,'results'=>$service->operationalSearch($query)], JSON_UNESCAPED_SLASHES);
    }

    public function createDraft(): void
    {
        Auth::requireRole('owner','administrator','office','estimator'); verify_csrf();
        $type = (string) ($_POST['draft_type'] ?? '');
        $record = ['reference' => trim((string) ($_POST['reference'] ?? '')), 'details' => trim((string) ($_POST['details'] ?? ''))];
        $pdo = Database::connection(); $provider = new AiProviderService($pdo);
        try {
            $result = (new AiOperationalService($pdo, $provider))->draft($type, $record, trim((string) ($_POST['instruction'] ?? '')));
            $requiresApproval = in_array($type, ['estimate','customer_reply'], true) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO ai_generated_drafts(draft_type,reference_key,content,status,requires_approval,created_by,created_at) VALUES(?,?,?,'draft',?,?,NOW())");
            $stmt->execute([$type,$record['reference'],$result['content'],$requiresApproval,Auth::id()]);
            $this->storeUsage($result, $type, $record['reference']);
            $_SESSION['ai_answer'] = $result['content']; $_SESSION['ai_prompt'] = 'Draft: '.$type;
            flash('success','AI draft created. Review it before use.');
        } catch (Throwable $e) { flash('danger','AI draft failed: '.$e->getMessage()); }
        redirect('/portal/ai');
    }

    public function approveDraft(int $id): void
    {
        Auth::requireRole('owner','administrator','office'); verify_csrf();
        Database::connection()->prepare("UPDATE ai_generated_drafts SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='draft'")->execute([Auth::id(),$id]);
        flash('success','AI draft approved.'); redirect('/portal/ai');
    }

    public function updateSettings(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        $provider = (string) ($_POST['provider'] ?? 'disabled');
        if (!in_array($provider,['disabled','openai','azure_openai','ollama'],true)) $provider='disabled';
        Database::connection()->prepare("UPDATE ai_provider_settings SET enabled=?,provider=?,model=?,endpoint=?,temperature=?,max_tokens=?,daily_request_limit=?,monthly_request_limit=?,allowed_roles=?,redact_sensitive_data=?,system_prompt=?,updated_by=?,updated_at=NOW() WHERE id=1")->execute([
            isset($_POST['enabled'])?1:0,$provider,trim((string)($_POST['model']??'')),trim((string)($_POST['endpoint']??'')),max(0,min(2,(float)($_POST['temperature']??0.2))),max(64,min(8000,(int)($_POST['max_tokens']??800))),max(0,(int)($_POST['daily_request_limit']??0)),max(0,(int)($_POST['monthly_request_limit']??0)),trim((string)($_POST['allowed_roles']??'owner,administrator,office,estimator')),isset($_POST['redact_sensitive_data'])?1:0,trim((string)($_POST['system_prompt']??'')),Auth::id()
        ]);
        flash('success','AI provider settings updated.'); redirect('/portal/ai');
    }

    public function savePrompt(): void
    {
        Auth::requireRole('owner','administrator','office'); verify_csrf();
        $name=trim((string)($_POST['name']??'')); $prompt=trim((string)($_POST['prompt_template']??''));
        if($name===''||$prompt===''){flash('danger','Prompt name and template are required.');redirect('/portal/ai');}
        Database::connection()->prepare("INSERT INTO ai_saved_prompts(name,prompt_template,version,created_by,created_at,updated_at) VALUES(?,?,1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE prompt_template=VALUES(prompt_template),version=version+1,updated_at=NOW()")->execute([$name,$prompt,Auth::id()]);
        flash('success','Prompt saved.'); redirect('/portal/ai');
    }

    private function executeAndLog(AiProviderService $service,string $prompt,string $purpose,array $context): void
    {
        try { $result=$service->ask($prompt,$purpose,$context); $this->storeUsage($result,$purpose,$prompt); $_SESSION['ai_answer']=$result['content']; $_SESSION['ai_prompt']=$prompt; }
        catch(Throwable $e){ flash('danger','AI request failed: '.$e->getMessage()); }
    }

    private function storeUsage(array $result,string $purpose,string $prompt): void
    {
        Database::connection()->prepare("INSERT INTO ai_usage_logs(user_id,provider,model,purpose,prompt_hash,input_chars,output_chars,status,latency_ms,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())")->execute([Auth::id(),$result['provider'],$result['model'],$purpose,hash('sha256',$prompt),$result['input_chars'],$result['output_chars'],'success',$result['latency_ms']]);
    }
}
