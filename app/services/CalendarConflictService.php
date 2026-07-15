<?php
namespace App\Services;
use App\Core\Database;
final class CalendarConflictService
{
 public static function detect(int $jobId):array{$pdo=Database::connection();$q=$pdo->prepare('SELECT * FROM jobs WHERE id=?');$q->execute([$jobId]);$j=$q->fetch();if(!$j||!$j['scheduled_start']||!$j['scheduled_end'])return[];$sql="SELECT id,job_number,assigned_user_id,assigned_vehicle_id FROM jobs WHERE id<>? AND status NOT IN ('cancelled','completed') AND scheduled_start < ? AND scheduled_end > ? AND ((assigned_user_id IS NOT NULL AND assigned_user_id=?) OR (assigned_vehicle_id IS NOT NULL AND assigned_vehicle_id=?))";$s=$pdo->prepare($sql);$s->execute([$jobId,$j['scheduled_end'],$j['scheduled_start'],$j['assigned_user_id'],$j['assigned_vehicle_id']]);$rows=$s->fetchAll();$pdo->prepare('DELETE FROM calendar_conflicts WHERE job_id=? AND resolved_at IS NULL')->execute([$jobId]);foreach($rows as $r){$type=(int)$r['assigned_user_id']===(int)$j['assigned_user_id']?'technician':'vehicle';$pdo->prepare('INSERT INTO calendar_conflicts(job_id,conflicting_job_id,conflict_type,detail) VALUES(?,?,?,?)')->execute([$jobId,$r['id'],$type,'Overlaps job '.$r['job_number']]);}return $rows;}
}
