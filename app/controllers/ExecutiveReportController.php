<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class ExecutiveReportController{
 public function index():void{Auth::requireRole('owner','administrator');$pdo=Database::connection();View::render('portal/executive-reports/index',['title'=>'Scheduled Executive Reports','schedules'=>$pdo->query('SELECT s.*,u.name creator FROM executive_report_schedules s LEFT JOIN users u ON u.id=s.created_by ORDER BY s.name')->fetchAll(),'runs'=>$pdo->query('SELECT * FROM executive_report_runs ORDER BY created_at DESC LIMIT 50')->fetchAll()],'portal');}
 public function store():void{Auth::requireRole('owner','administrator');verify_csrf();$pdo=Database::connection();$next=date('Y-m-d H:i:s',strtotime('+1 day'));$pdo->prepare('INSERT INTO executive_report_schedules(name,report_key,frequency,day_of_week,day_of_month,send_time,recipients,include_pdf,active,next_run_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([trim($_POST['name']),$_POST['report_key'],$_POST['frequency'],($_POST['day_of_week']??'')?:null,($_POST['day_of_month']??'')?:null,$_POST['send_time'],trim($_POST['recipients']),isset($_POST['include_pdf'])?1:0,isset($_POST['active'])?1:0,$next,Auth::id()]);flash('success','Report schedule created.');redirect('/portal/executive-reports');}
}
