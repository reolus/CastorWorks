<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use PDO;

final class LeadController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'office', 'estimator');
        $rows = Database::connection()->query(
            "SELECT l.*, mc.name campaign_name
             FROM leads l
             LEFT JOIN marketing_campaigns mc ON mc.id=l.campaign_id
             ORDER BY l.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        View::render('portal/leads/index', ['title' => 'Leads', 'leads' => $rows], 'portal');
    }

    public function show(string $id): void
    {
        Auth::requireRole('owner', 'office', 'estimator');
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT l.*, mc.name campaign_name
             FROM leads l
             LEFT JOIN marketing_campaigns mc ON mc.id=l.campaign_id
             WHERE l.id=?"
        );
        $stmt->execute([(int) $id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        $campaigns = $pdo->query(
            "SELECT id,name,status
             FROM marketing_campaigns
             WHERE status IN ('planned','active','paused')
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);

        View::render('portal/leads/show', [
            'title' => 'Lead',
            'lead' => $lead,
            'campaigns' => $campaigns,
        ], 'portal');
    }

    public function status(string $id): void
    {
        Auth::requireRole('owner', 'office', 'estimator');
        verify_csrf();
        $allowed = ['new', 'contacted', 'quoted', 'won', 'lost'];
        $status = in_array($_POST['status'] ?? '', $allowed, true) ? $_POST['status'] : 'new';
        Database::connection()->prepare('UPDATE leads SET status=? WHERE id=?')->execute([$status, (int) $id]);
        AuditService::log('lead.status', 'lead', (int) $id, ['status' => $status]);
        flash('success', 'Lead updated.');
        redirect('/portal/leads/' . $id);
    }

    public function marketing(string $id): void
    {
        Auth::requireRole('owner', 'office', 'estimator');
        verify_csrf();

        $campaignId = ($_POST['campaign_id'] ?? '') !== '' ? (int) $_POST['campaign_id'] : null;
        $source = trim((string) ($_POST['lead_source'] ?? '')) ?: null;

        Database::connection()->prepare(
            "UPDATE leads
             SET lead_source=?, campaign_id=?, referral_code=?, utm_source=?, utm_medium=?, utm_campaign=?
             WHERE id=?"
        )->execute([
            $source,
            $campaignId,
            trim((string) ($_POST['referral_code'] ?? '')) ?: null,
            trim((string) ($_POST['utm_source'] ?? '')) ?: null,
            trim((string) ($_POST['utm_medium'] ?? '')) ?: null,
            trim((string) ($_POST['utm_campaign'] ?? '')) ?: null,
            (int) $id,
        ]);

        AuditService::log('lead.marketing_attribution', 'lead', (int) $id, [
            'lead_source' => $source,
            'campaign_id' => $campaignId,
        ]);
        flash('success', 'Lead attribution updated.');
        redirect('/portal/leads/' . $id);
    }

    public function convert(string $id): void
    {
        Auth::requireRole('owner', 'office', 'estimator');
        verify_csrf();
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE');
            $stmt->execute([(int) $id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lead) {
                throw new \RuntimeException('Lead not found.');
            }

            $customer = $pdo->prepare(
                "INSERT INTO customers
                    (customer_type,display_name,contact_name,email,phone,billing_address,status,notes,acquisition_source,acquisition_campaign_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $customer->execute([
                'residential',
                $lead['name'],
                $lead['name'],
                $lead['email'],
                $lead['phone'],
                $lead['address'],
                'active',
                $lead['details'],
                $lead['lead_source'] ?: null,
                $lead['campaign_id'] ?: null,
            ]);
            $customerId = (int) $pdo->lastInsertId();

            $pdo->prepare("UPDATE leads SET status='won' WHERE id=?")->execute([(int) $id]);
            $pdo->commit();

            AuditService::log('lead.converted', 'lead', (int) $id, ['customer_id' => $customerId]);
            flash('success', 'Lead converted to customer.');
            redirect('/portal/customers/' . $customerId);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', $e->getMessage());
            redirect('/portal/leads/' . $id);
        }
    }
}
