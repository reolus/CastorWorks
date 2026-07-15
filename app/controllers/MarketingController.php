<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use PDO;

final class MarketingController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'office', 'estimator');

        $pdo = Database::connection();
        $from = $this->date($_GET['from'] ?? date('Y-01-01'), date('Y-01-01'));
        $to = $this->date($_GET['to'] ?? date('Y-m-d'), date('Y-m-d'));

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $campaigns = $pdo->prepare(
            "SELECT mc.*,
                    u.name creator,
                    (SELECT COUNT(*) FROM leads l WHERE l.campaign_id=mc.id) lead_count,
                    (SELECT COUNT(*) FROM leads l WHERE l.campaign_id=mc.id AND l.status='won') won_leads,
                    (SELECT COUNT(*) FROM estimates e JOIN customers c ON c.id=e.customer_id WHERE c.acquisition_campaign_id=mc.id) estimate_count,
                    (SELECT COALESCE(SUM(i.total),0) FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE c.acquisition_campaign_id=mc.id) attributed_revenue
             FROM marketing_campaigns mc
             LEFT JOIN users u ON u.id=mc.created_by
             WHERE DATE(mc.created_at) BETWEEN ? AND ?
             ORDER BY mc.created_at DESC"
        );
        $campaigns->execute([$from, $to]);

        $summary = $pdo->prepare(
            "SELECT
                COUNT(*) campaign_count,
                COALESCE(SUM(budget),0) budget,
                COALESCE(SUM(actual_cost),0) actual_cost
             FROM marketing_campaigns
             WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $summary->execute([$from, $to]);
        $summaryRow = $summary->fetch(PDO::FETCH_ASSOC) ?: [];

        $leadSummary = $pdo->prepare(
            "SELECT COUNT(*) leads,
                    SUM(status='won') won,
                    SUM(status='lost') lost
             FROM leads
             WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $leadSummary->execute([$from, $to]);
        $leadRow = $leadSummary->fetch(PDO::FETCH_ASSOC) ?: [];

        $sourceRows = $pdo->prepare(
            "SELECT COALESCE(NULLIF(lead_source,''),'Unspecified') source,
                    COUNT(*) leads,
                    SUM(status='won') won
             FROM leads
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY COALESCE(NULLIF(lead_source,''),'Unspecified')
             ORDER BY leads DESC, source"
        );
        $sourceRows->execute([$from, $to]);

        $coupons = $pdo->query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM marketing_coupon_redemptions r WHERE r.coupon_id=c.id) redemption_count,
                    (SELECT COALESCE(SUM(r.discount_amount),0) FROM marketing_coupon_redemptions r WHERE r.coupon_id=c.id) discount_total
             FROM marketing_coupons c
             ORDER BY c.created_at DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        $suppressions = $pdo->query(
            "SELECT * FROM marketing_suppressions ORDER BY created_at DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        $referrals = $pdo->query(
            "SELECT r.*, c.display_name referrer
             FROM customer_referrals r
             JOIN customers c ON c.id=r.referring_customer_id
             ORDER BY r.created_at DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        $revenue = $pdo->prepare(
            "SELECT COALESCE(SUM(i.total),0)
             FROM invoices i
             JOIN customers c ON c.id=i.customer_id
             WHERE c.acquisition_campaign_id IS NOT NULL
               AND DATE(i.issue_date) BETWEEN ? AND ?"
        );
        $revenue->execute([$from, $to]);
        $attributedRevenue = (float) $revenue->fetchColumn();

        $cost = (float) ($summaryRow['actual_cost'] ?? 0);
        $roi = $cost > 0 ? (($attributedRevenue - $cost) / $cost) * 100 : null;

        View::render('portal/marketing/index', [
            'title' => 'Marketing',
            'campaigns' => $campaigns->fetchAll(PDO::FETCH_ASSOC),
            'coupons' => $coupons,
            'referrals' => $referrals,
            'suppressions' => $suppressions,
            'sources' => $sourceRows->fetchAll(PDO::FETCH_ASSOC),
            'from' => $from,
            'to' => $to,
            'summary' => [
                'campaigns' => (int) ($summaryRow['campaign_count'] ?? 0),
                'budget' => (float) ($summaryRow['budget'] ?? 0),
                'cost' => $cost,
                'leads' => (int) ($leadRow['leads'] ?? 0),
                'won' => (int) ($leadRow['won'] ?? 0),
                'attributed_revenue' => $attributedRevenue,
                'roi' => $roi,
            ],
        ], 'portal');
    }

    public function storeCampaign(): void
    {
        Auth::requireRole('owner', 'office');
        verify_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('danger', 'Campaign name is required.');
            redirect('/portal/marketing');
        }

        $types = ['digital', 'email', 'sms', 'flyer', 'door_hanger', 'referral', 'seasonal', 'other'];
        $statuses = ['draft', 'planned', 'active', 'paused', 'completed', 'cancelled'];
        $type = in_array($_POST['campaign_type'] ?? '', $types, true) ? $_POST['campaign_type'] : 'other';
        $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'planned';

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO marketing_campaigns
                (name,campaign_type,channel,status,start_date,end_date,budget,actual_cost,target_postal_codes,tracking_code,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $name,
            $type,
            trim((string) ($_POST['channel'] ?? '')) ?: null,
            $status,
            ($_POST['start_date'] ?? '') ?: null,
            ($_POST['end_date'] ?? '') ?: null,
            max(0, (float) ($_POST['budget'] ?? 0)),
            max(0, (float) ($_POST['actual_cost'] ?? 0)),
            trim((string) ($_POST['target_postal_codes'] ?? '')) ?: null,
            trim((string) ($_POST['tracking_code'] ?? '')) ?: null,
            trim((string) ($_POST['notes'] ?? '')) ?: null,
            Auth::id(),
        ]);

        $id = (int) $pdo->lastInsertId();
        AuditService::log('marketing.campaign_created', 'marketing_campaign', $id);
        flash('success', 'Marketing campaign created.');
        redirect('/portal/marketing');
    }

    public function updateCampaign(int $id): void
    {
        Auth::requireRole('owner', 'office');
        verify_csrf();

        $statuses = ['draft', 'planned', 'active', 'paused', 'completed', 'cancelled'];
        $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'planned';

        Database::connection()->prepare(
            "UPDATE marketing_campaigns
             SET status=?, actual_cost=?, updated_at=NOW()
             WHERE id=?"
        )->execute([
            $status,
            max(0, (float) ($_POST['actual_cost'] ?? 0)),
            $id,
        ]);

        AuditService::log('marketing.campaign_updated', 'marketing_campaign', $id, ['status' => $status]);
        flash('success', 'Campaign updated.');
        redirect('/portal/marketing');
    }

    public function storeCoupon(): void
    {
        Auth::requireRole('owner', 'office');
        verify_csrf();

        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($code === '' || $name === '') {
            flash('danger', 'Coupon code and name are required.');
            redirect('/portal/marketing');
        }

        $type = ($_POST['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value = max(0, (float) ($_POST['discount_value'] ?? 0));

        try {
            Database::connection()->prepare(
                "INSERT INTO marketing_coupons
                    (code,name,discount_type,discount_value,start_date,end_date,max_redemptions,active,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                $code,
                $name,
                $type,
                $value,
                ($_POST['start_date'] ?? '') ?: null,
                ($_POST['end_date'] ?? '') ?: null,
                ($_POST['max_redemptions'] ?? '') !== '' ? max(1, (int) $_POST['max_redemptions']) : null,
                isset($_POST['active']) ? 1 : 0,
                Auth::id(),
            ]);
            flash('success', 'Coupon created.');
        } catch (\Throwable $e) {
            flash('danger', 'Coupon could not be created: ' . $e->getMessage());
        }

        redirect('/portal/marketing');
    }

    public function storeSuppression(): void
    {
        Auth::requireRole('owner', 'office');
        verify_csrf();

        $destination = strtolower(trim((string) ($_POST['destination'] ?? '')));
        $channels = ['email', 'sms', 'all'];
        $channel = in_array($_POST['channel'] ?? '', $channels, true) ? $_POST['channel'] : 'all';

        if ($destination === '') {
            flash('danger', 'Email address or phone number is required.');
            redirect('/portal/marketing');
        }

        Database::connection()->prepare(
            "INSERT INTO marketing_suppressions(destination,channel,reason)
             VALUES(?,?,?)
             ON DUPLICATE KEY UPDATE reason=VALUES(reason)"
        )->execute([
            $destination,
            $channel,
            trim((string) ($_POST['reason'] ?? '')) ?: null,
        ]);

        flash('success', 'Marketing suppression saved.');
        redirect('/portal/marketing');
    }

    private function date(string $value, string $default): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : $default;
    }
}
