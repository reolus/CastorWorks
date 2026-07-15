<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;use App\Services\GraphService;use App\Services\PaymentService;use App\Services\SharePointService;use App\Services\TeamsService;use App\Services\IntegrationHealthService;use Throwable;
final class PortalController
{
    public function dashboard(): void
    {
        Auth::requireLogin();$pdo=Database::connection();$stats=['jobs_today'=>0,'quotes_pending'=>0,'revenue_week'=>0,'unpaid_invoices'=>0,'approval_pending'=>0,'workflow_failed'=>0];$schedule=[];$activity=[];$chartLabels=[];$chartRevenue=[];$statusData=[];
        try{
            $stats['jobs_today']=(int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE DATE(scheduled_start)=CURDATE() AND status<>'cancelled'")->fetchColumn();
            $stats['quotes_pending']=(int)$pdo->query("SELECT COUNT(*) FROM estimates WHERE status IN ('draft','sent')")->fetchColumn();
            $stats['revenue_week']=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEARWEEK(received_at,1)=YEARWEEK(CURDATE(),1)")->fetchColumn();
            $stats['unpaid_invoices']=(int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','overdue') AND balance_due>0")->fetchColumn();$stats['approval_pending']=(int)$pdo->query("SELECT COUNT(*) FROM entity_approvals WHERE status='pending'")->fetchColumn();$stats['workflow_failed']=(int)$pdo->query("SELECT COUNT(*) FROM workflow_runs WHERE status='failed' AND queued_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
            $schedule=$pdo->query("SELECT j.id,j.job_number,j.scheduled_start,j.service_summary,j.status,c.display_name FROM jobs j JOIN customers c ON c.id=j.customer_id WHERE DATE(j.scheduled_start)=CURDATE() ORDER BY COALESCE(j.route_order,9999),j.scheduled_start LIMIT 8")->fetchAll();
            $activity=$pdo->query('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8')->fetchAll();
            $rev=$pdo->query("SELECT DATE_FORMAT(d.day,'%b %e') label,COALESCE(SUM(p.amount),0) total FROM (SELECT CURDATE()-INTERVAL 6 DAY day UNION ALL SELECT CURDATE()-INTERVAL 5 DAY UNION ALL SELECT CURDATE()-INTERVAL 4 DAY UNION ALL SELECT CURDATE()-INTERVAL 3 DAY UNION ALL SELECT CURDATE()-INTERVAL 2 DAY UNION ALL SELECT CURDATE()-INTERVAL 1 DAY UNION ALL SELECT CURDATE()) d LEFT JOIN payments p ON DATE(p.received_at)=d.day GROUP BY d.day ORDER BY d.day")->fetchAll();
            foreach($rev as $r){$chartLabels[]=$r['label'];$chartRevenue[]=(float)$r['total'];}
            $statusData=$pdo->query("SELECT status,COUNT(*) total FROM jobs GROUP BY status ORDER BY total DESC")->fetchAll();
        }catch(Throwable){}
        View::render('portal/dashboard',compact('stats','schedule','activity','chartLabels','chartRevenue','statusData')+['title'=>'Dashboard'],'portal');
    }
    public function scheduling(): void { Auth::requireLogin();$start=$_GET['start']??date('Y-m-01');$end=$_GET['end']??date('Y-m-t');$s=Database::connection()->prepare('SELECT j.*,c.display_name FROM jobs j JOIN customers c ON c.id=j.customer_id WHERE DATE(j.scheduled_start) BETWEEN ? AND ? ORDER BY j.scheduled_start');$s->execute([$start,$end]);View::render('portal/scheduling',['title'=>'Scheduling','jobs'=>$s->fetchAll(),'start'=>$start,'end'=>$end],'portal'); }
    public function integrations(): void { Auth::requireLogin();$integrations=(new IntegrationHealthService())->all(true);View::render('portal/integrations',['title'=>'Integrations','integrations'=>$integrations],'portal'); }
}
