<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View; use App\Services\CalendarConflictService;
final class CalendarConflictController
{
 public function index():void{Auth::requireLogin();$rows=Database::connection()->query("SELECT cc.*,j.job_number,cj.job_number conflicting_number,u.name resolved_by_name FROM calendar_conflicts cc JOIN jobs j ON j.id=cc.job_id LEFT JOIN jobs cj ON cj.id=cc.conflicting_job_id LEFT JOIN users u ON u.id=cc.resolved_by ORDER BY cc.resolved_at IS NULL DESC,cc.detected_at DESC")->fetchAll();View::render('portal/calendar-conflicts/index',['title'=>'Calendar conflicts','conflicts'=>$rows],'portal');}
 public function scan(string $id):void{Auth::requireRole('owner','office','crew_leader');verify_csrf();$rows=CalendarConflictService::detect((int)$id);flash($rows?'warning':'success',$rows?count($rows).' conflict(s) detected.':'No conflicts detected.');redirect('/portal/calendar-conflicts');}
 public function resolve(string $id):void{Auth::requireRole('owner','office','crew_leader');verify_csrf();Database::connection()->prepare('UPDATE calendar_conflicts SET resolved_at=NOW(),resolved_by=? WHERE id=?')->execute([Auth::id(),(int)$id]);flash('success','Conflict marked resolved.');redirect('/portal/calendar-conflicts');}
}
