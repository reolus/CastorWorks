<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Services\AuditService;
use App\Services\Communication\ProviderRegistry;
use App\Services\CommunicationManager;
use Throwable;

final class CommunicationProviderController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'administrator');
        $pdo = Database::connection();
        $attempts = $pdo->query('SELECT * FROM communication_delivery_attempts ORDER BY created_at DESC,id DESC LIMIT 100')->fetchAll();
        $receipts = $pdo->query('SELECT * FROM communication_receipts ORDER BY received_at DESC,id DESC LIMIT 50')->fetchAll();
        $inbound = $pdo->query('SELECT * FROM communication_inbound_messages ORDER BY received_at DESC,id DESC LIMIT 50')->fetchAll();
        $suppressions = $pdo->query("SELECT * FROM marketing_suppressions ORDER BY created_at DESC LIMIT 100")->fetchAll();
        $summary = $pdo->query(
            "SELECT provider_key,COUNT(*) attempts,SUM(status='sent') sent,SUM(status='failed') failed,COALESCE(SUM(sms_parts),0) sms_parts FROM communication_delivery_attempts WHERE created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY provider_key ORDER BY attempts DESC"
        )->fetchAll();

        View::render('portal/communication-providers/index', [
            'title' => 'Communication Providers',
            'providers' => (new CommunicationManager())->status(),
            'attempts' => $attempts,
            'receipts' => $receipts,
            'inbound' => $inbound,
            'suppressions' => $suppressions,
            'summary' => $summary,
            'fallbackEnabled' => Env::bool('COMMUNICATION_FALLBACK_ENABLED', true),
        ], 'portal');
    }

    public function update(): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        $pdo = Database::connection();
        $allowed = array_keys((new ProviderRegistry())->all());
        $update = $pdo->prepare(
            'UPDATE communication_providers SET enabled=?,priority=?,allow_fallback=?,allow_transactional=?,allow_marketing=?,daily_limit=?,monthly_limit=?,notes=?,updated_at=NOW() WHERE provider_key=?'
        );

        foreach ((array) ($_POST['providers'] ?? []) as $key => $values) {
            if (!in_array($key, $allowed, true) || !is_array($values)) continue;
            $update->execute([
                isset($values['enabled']) ? 1 : 0,
                max(1, min(999, (int) ($values['priority'] ?? 100))),
                isset($values['allow_fallback']) ? 1 : 0,
                isset($values['allow_transactional']) ? 1 : 0,
                isset($values['allow_marketing']) ? 1 : 0,
                max(0, (int) ($values['daily_limit'] ?? 0)),
                max(0, (int) ($values['monthly_limit'] ?? 0)),
                trim((string) ($values['notes'] ?? '')) ?: null,
                $key,
            ]);
        }
        AuditService::log('communication.providers_updated', 'communication_provider', 0);
        flash('success', 'Communication routing, limits, and message classes were updated.');
        redirect('/portal/communication-providers');
    }

    public function suppress(): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        $destination = trim((string) ($_POST['destination'] ?? ''));
        $channel = in_array($_POST['channel'] ?? '', ['email','sms','all'], true) ? (string) $_POST['channel'] : 'all';
        if ($destination === '') {
            flash('danger', 'A destination is required.');
            redirect('/portal/communication-providers');
        }
        Database::connection()->prepare(
            'INSERT INTO marketing_suppressions(destination,channel,reason,created_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE reason=VALUES(reason)'
        )->execute([$destination, $channel, trim((string) ($_POST['reason'] ?? 'Manual suppression'))]);
        flash('success', 'Suppression added.');
        redirect('/portal/communication-providers');
    }

    public function unsuppress(int $id): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        Database::connection()->prepare('DELETE FROM marketing_suppressions WHERE id=?')->execute([$id]);
        flash('success', 'Suppression removed.');
        redirect('/portal/communication-providers');
    }

    public function test(): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        $key = (string) ($_POST['provider_key'] ?? '');
        $provider = (new ProviderRegistry())->get($key);
        if ($provider === null || !$provider->configured()) {
            flash('danger', $provider?->label() . ' is not configured.');
            redirect('/portal/communication-providers');
        }
        $recipient = trim((string) ($_POST['recipient'] ?? ''));
        try {
            $message = match ($provider->channel()) {
                'email' => ['to'=>$recipient,'subject'=>'ServiceOS communication-provider test','html'=>'<p>This is a ServiceOS transactional test through <strong>'.htmlspecialchars($provider->label(),ENT_QUOTES,'UTF-8').'</strong>.</p>','attachments'=>[],'context'=>['test'=>true,'message_class'=>'transactional']],
                'topic' => ['subject'=>'ServiceOS provider test','text'=>'This is a ServiceOS Amazon SNS topic test.','topic_arn'=>$recipient !== '' ? $recipient : null,'context'=>['test'=>true,'message_class'=>'transactional']],
                default => ['to'=>$recipient,'text'=>'ServiceOS transactional test through '.$provider->label().'.','context'=>['test'=>true,'message_class'=>'transactional']],
            };
            $result = $provider->send($message);
            flash('success', $provider->label().' accepted the test message'.($result->messageId ? ' ('.$result->messageId.')' : '').'.');
        } catch (Throwable $e) {
            flash('danger', $provider->label().' test failed: '.$e->getMessage());
        }
        redirect('/portal/communication-providers');
    }
}
