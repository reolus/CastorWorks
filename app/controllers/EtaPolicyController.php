<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\RouteProgressService;

final class EtaPolicyController
{
    public function index(): void
    {
        Auth::requireRole('owner','administrator');
        $service = new RouteProgressService();
        View::render('portal/eta-policies/index', ['title'=>'ETA & Route Progress','settings'=>$service->settings()], 'portal');
    }

    public function update(): void
    {
        Auth::requireRole('owner','administrator'); verify_csrf();
        $pdo=Database::connection();
        $pdo->prepare("INSERT INTO eta_notification_settings(id,enabled,send_on_the_way,send_delay_notices,on_the_way_minutes,late_threshold_minutes,minimum_recalculation_minutes,average_speed_mph,on_the_way_template,delay_template,updated_by,updated_at)
            VALUES(1,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),send_on_the_way=VALUES(send_on_the_way),send_delay_notices=VALUES(send_delay_notices),on_the_way_minutes=VALUES(on_the_way_minutes),late_threshold_minutes=VALUES(late_threshold_minutes),minimum_recalculation_minutes=VALUES(minimum_recalculation_minutes),average_speed_mph=VALUES(average_speed_mph),on_the_way_template=VALUES(on_the_way_template),delay_template=VALUES(delay_template),updated_by=VALUES(updated_by),updated_at=NOW()")
            ->execute([isset($_POST['enabled'])?1:0,isset($_POST['send_on_the_way'])?1:0,isset($_POST['send_delay_notices'])?1:0,max(5,(int)($_POST['on_the_way_minutes']??30)),max(5,(int)($_POST['late_threshold_minutes']??15)),max(1,(int)($_POST['minimum_recalculation_minutes']??5)),max(10,(int)($_POST['average_speed_mph']??32)),trim((string)($_POST['on_the_way_template']??'')),trim((string)($_POST['delay_template']??'')),Auth::id()]);
        AuditService::log('eta.policy_updated','eta_policy',1); flash('success','ETA and route progress settings saved.'); redirect('/portal/eta-policies');
    }

    public function recalculate(): void
    {
        Auth::requireRole('owner','administrator','office','crew_leader'); verify_csrf();
        $result=(new RouteProgressService())->updateDate((string)($_POST['date']??date('Y-m-d')));
        flash('success','ETA refresh complete: '.$result['jobs_updated'].' jobs updated, '.$result['notifications_sent'].' notifications sent.');
        redirect('/portal/routes?date='.rawurlencode($result['date']));
    }
}
