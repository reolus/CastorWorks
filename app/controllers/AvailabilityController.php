<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View; use App\Services\AuditService;
final class AvailabilityController {
 public function index():void{Auth::requireRole('owner','office','crew_leader');$pdo=Database::connection();$from=$_GET['from']??date('Y-m-d');$to=$_GET['to']??date('Y-m-d',strtotime('+30 days'));$q=$pdo->prepare("SELECT a.*,u.name user_name FROM employee_availability a JOIN users u ON u.id=a.user_id WHERE a.availability_date BETWEEN ? AND ? ORDER BY a.availability_date,u.name");$q->execute([$from,$to]);View::render('portal/availability/index',['title'=>'Employee Availability','rows'=>$q->fetchAll(),'users'=>$pdo->query("SELECT id,name FROM users WHERE active=1 ORDER BY name")->fetchAll(),'from'=>$from,'to'=>$to],'portal');}
 public function store():void{Auth::requireRole('owner','office','crew_leader');verify_csrf();$pdo=Database::connection();$pdo->prepare("INSERT INTO employee_availability(user_id,availability_date,start_time,end_time,status,notes,created_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE start_time=VALUES(start_time),end_time=VALUES(end_time),status=VALUES(status),notes=VALUES(notes),updated_at=NOW()")
 ->execute([(int)$_POST['user_id'],$_POST['availability_date'],($_POST['start_time']??'')?:null,($_POST['end_time']??'')?:null,$_POST['status']??'available',trim($_POST['notes']??''),Auth::id()]);AuditService::log('availability.saved','user',(int)$_POST['user_id']);flash('success','Availability saved.');redirect('/portal/availability');}
}
