<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

final class GpsPolicyController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'administrator');
        $pdo = Database::connection();
        $policy = $pdo->query('SELECT * FROM gps_tracking_policies WHERE id=1')->fetch() ?: [];
        $recent = $pdo->query(
            "SELECT g.*,u.name user_name,c.name crew_name,v.unit_number,j.job_number
             FROM gps_location_history g
             LEFT JOIN users u ON u.id=g.user_id
             LEFT JOIN crews c ON c.id=g.crew_id
             LEFT JOIN vehicles v ON v.id=g.vehicle_id
             LEFT JOIN jobs j ON j.id=g.job_id
             ORDER BY g.captured_at DESC LIMIT 100"
        )->fetchAll();
        View::render('portal/gps-policies/index', ['title' => 'GPS Tracking', 'policy' => $policy, 'recent' => $recent], 'portal');
    }

    public function update(): void
    {
        Auth::requireRole('owner', 'administrator');
        verify_csrf();
        $values = [
            isset($_POST['enabled']) ? 1 : 0,
            isset($_POST['clocked_in_only']) ? 1 : 0,
            max(15, min(900, (int)($_POST['update_interval_seconds'] ?? 60))),
            max(25, min(1000, (int)($_POST['arrival_radius_meters'] ?? 125))),
            max(2, min(120, (int)($_POST['stale_after_minutes'] ?? 10))),
            max(1, min(3650, (int)($_POST['retention_days'] ?? 90))),
            Auth::id(),
        ];
        Database::connection()->prepare(
            'INSERT INTO gps_tracking_policies
             (id,enabled,clocked_in_only,update_interval_seconds,arrival_radius_meters,stale_after_minutes,retention_days,updated_by)
             VALUES(1,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),clocked_in_only=VALUES(clocked_in_only),
             update_interval_seconds=VALUES(update_interval_seconds),arrival_radius_meters=VALUES(arrival_radius_meters),
             stale_after_minutes=VALUES(stale_after_minutes),retention_days=VALUES(retention_days),updated_by=VALUES(updated_by)'
        )->execute($values);
        AuditService::log('gps.policy_updated', 'gps_policy', 1);
        flash('success', 'GPS tracking policy updated.');
        redirect('/portal/gps-policies');
    }
}
