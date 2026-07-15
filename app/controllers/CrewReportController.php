<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class CrewReportController{public function index():void{Auth::requireRole('owner','office','administrator');$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-t');$db=Database::connection();$s=$db->prepare("SELECT c.id,c.name,COUNT(DISTINCT j.id) jobs,COALESCE(SUM(TIMESTAMPDIFF(MINUTE,te.clock_in,COALESCE(te.clock_out,NOW()))-te.break_minutes),0)/60 labor_hours,COALESCE(SUM(i.total),0) revenue FROM crews c LEFT JOIN jobs j ON j.crew_id=c.id AND DATE(COALESCE(j.completed_at,j.scheduled_start)) BETWEEN ? AND ? LEFT JOIN time_entries te ON te.job_id=j.id LEFT JOIN invoices i ON i.job_id=j.id AND i.status<>'void' GROUP BY c.id,c.name ORDER BY revenue DESC,c.name");$s->execute([$from,$to]);View::render('portal/workforce/report',['title'=>'Crew Performance','rows'=>$s->fetchAll(),'from'=>$from,'to'=>$to],'portal');}}
