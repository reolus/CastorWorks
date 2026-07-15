<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use PDO;

final class CommunicationController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'office');
        $pdo = Database::connection();
        $rows = $pdo->query(
            "SELECT c.*,u.name creator,mc.name marketing_campaign_name,
                    (SELECT COUNT(*) FROM communication_campaign_recipients r WHERE r.campaign_id=c.id) recipients
             FROM communication_campaigns c
             LEFT JOIN users u ON u.id=c.created_by
             LEFT JOIN marketing_campaigns mc ON mc.id=c.marketing_campaign_id
             ORDER BY c.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $marketingCampaigns = $pdo->query(
            "SELECT id,name FROM marketing_campaigns
             WHERE status IN ('planned','active','paused') ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        View::render('portal/communications/index', [
            'title' => 'Communications',
            'rows' => $rows,
            'marketingCampaigns' => $marketingCampaigns,
        ], 'portal');
    }

    public function store(): void
    {
        Auth::requireRole('owner', 'office');
        verify_csrf();
        $pdo = Database::connection();
        $pdo->prepare(
            "INSERT INTO communication_campaigns
                (name,marketing_campaign_id,channel,template_key,audience_type,scheduled_at,status,created_by)
             VALUES(?,?,?,?,?,?,?,?)"
        )->execute([
            trim((string) $_POST['name']),
            ($_POST['marketing_campaign_id'] ?? '') !== '' ? (int) $_POST['marketing_campaign_id'] : null,
            $_POST['channel'],
            trim((string) $_POST['template_key']),
            $_POST['audience_type'],
            ($_POST['scheduled_at'] ?? '') ?: null,
            empty($_POST['scheduled_at']) ? 'draft' : 'scheduled',
            Auth::id(),
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->seedRecipients($id, $_POST['audience_type'], $_POST['channel']);
        AuditService::log('campaign.created', 'communication_campaign', $id);
        flash('success', 'Campaign created.');
        redirect('/portal/communications');
    }

    public function send(int $id): void
    {
        Auth::requireRole('owner', 'office');
        verify_csrf();
        Database::connection()->prepare(
            "UPDATE communication_campaigns SET status='scheduled',scheduled_at=NOW() WHERE id=?"
        )->execute([$id]);
        flash('success', 'Campaign queued for processing.');
        redirect('/portal/communications');
    }

    private function seedRecipients(int $id, string $audience, string $channel): void
    {
        $pdo = Database::connection();
        $sql = "SELECT DISTINCT c.id,c.email,c.phone FROM customers c";
        if ($audience === 'recurring_customers') {
            $sql .= " JOIN recurring_services r ON r.customer_id=c.id AND r.active=1";
        } elseif ($audience === 'overdue_invoices') {
            $sql .= " JOIN invoices i ON i.customer_id=c.id AND i.balance_due>0 AND i.due_date<CURDATE()";
        } elseif ($audience === 'upcoming_jobs') {
            $sql .= " JOIN jobs j ON j.customer_id=c.id AND DATE(j.scheduled_start) BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)";
        }
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO communication_campaign_recipients(campaign_id,customer_id,destination) VALUES(?,?,?)'
        );
        foreach ($rows as $row) {
            $destination = $channel === 'sms' ? ($row['phone'] ?? '') : ($row['email'] ?? '');
            if ($destination) {
                $insert->execute([$id, $row['id'], $destination]);
            }
        }
    }
}
