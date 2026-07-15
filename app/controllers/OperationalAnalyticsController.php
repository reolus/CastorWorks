<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\RouteAnalyticsService;

final class OperationalAnalyticsController
{
    public function index(): void
    {
        Auth::requireRole('owner','administrator','office');
        $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
        $data=(new RouteAnalyticsService(Database::connection()))->dashboard($from,$to);
        View::render('portal/operational-analytics/index',['title'=>'Operational Analytics','from'=>$from,'to'=>$to]+$data,'portal');
    }
    public function rebuild(): void
    {
        Auth::requireRole('owner','administrator','office');verify_csrf();$date=(string)($_POST['date']??date('Y-m-d'));
        try{$result=(new RouteAnalyticsService(Database::connection()))->buildDate($date);flash('success','Analytics rebuilt for '.$result['date'].'.');}catch(\Throwable $e){flash('danger','Analytics build failed: '.$e->getMessage());}
        redirect('/portal/operational-analytics?from='.rawurlencode($date).'&to='.rawurlencode($date));
    }
}
