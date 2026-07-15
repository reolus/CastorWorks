<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;use App\Services\CrewOperationsService;
final class CrewCalendarController{public function index():void{Auth::requireRole('owner','office','crew_leader','administrator');$start=$_GET['start']??date('Y-m-d');$days=max(1,min(31,(int)($_GET['days']??14)));$db=Database::connection();$crews=CrewOperationsService::crews($db);$s=$db->prepare("SELECT j.id,j.job_number,j.service_summary,j.status,j.scheduled_start,j.scheduled_end,j.crew_id,c.display_name customer_name,cr.name crew_name FROM jobs j JOIN customers c ON c.id=j.customer_id LEFT JOIN crews cr ON cr.id=j.crew_id WHERE DATE(j.scheduled_start) BETWEEN ? AND DATE_ADD(?,INTERVAL ? DAY) ORDER BY j.scheduled_start");$s->execute([$start,$start,$days-1]);$jobs=$s->fetchAll();$dates=[];for($i=0;$i<$days;$i++)$dates[]=date('Y-m-d',strtotime($start." +{$i} days"));View::render('portal/workforce/calendar',['title'=>'Crew Calendar','crews'=>$crews,'jobs'=>$jobs,'dates'=>$dates,'start'=>$start,'days'=>$days],'portal');}}
