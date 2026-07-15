<?php
namespace App\Services;
use App\Core\Database;
use App\Core\Env;
use Throwable;

final class CalendarSyncService
{
    public static function pushJob(int $jobId): void
    {
        $db=Database::connection();
        $s=$db->prepare("SELECT j.*,c.display_name,p.address1,p.address2,p.city,p.state,p.postal_code,u.email technician_email FROM jobs j JOIN customers c ON c.id=j.customer_id LEFT JOIN properties p ON p.id=j.property_id LEFT JOIN users u ON u.id=j.assigned_user_id WHERE j.id=?");
        $s->execute([$jobId]);$job=$s->fetch();if(!$job||!$job['scheduled_start']||!$job['scheduled_end'])return;
        $job['full_address']=trim(implode(', ',array_filter([$job['address1']??null,$job['address2']??null,$job['city']??null,$job['state']??null,$job['postal_code']??null])));
        $graph=new GraphService();if(!$graph->configured())return;
        try{
            if(!empty($job['graph_event_id'])) $event=$graph->updateEvent((string)$job['graph_event_id'],$job);
            else $event=$graph->createEvent($job);
            $db->prepare('UPDATE jobs SET graph_event_id=?,graph_change_key=?,graph_last_synced_at=NOW() WHERE id=?')->execute([$event['id']??$job['graph_event_id'], $event['changeKey']??null,$jobId]);
        }catch(Throwable $e){
            $db->prepare("INSERT INTO communication_queue(channel,action,payload,status,last_error) VALUES('calendar','push_job',?,'pending',?)")->execute([json_encode(['job_id'=>$jobId]),$e->getMessage()]);
        }
    }

    public static function pullEvent(array $notification): void
    {
        $resource=(string)($notification['resource']??'');
        if(!preg_match('~/events/([^/]+)$~',$resource,$m))return;
        $eventId=rawurldecode($m[1]);$graph=new GraphService();if(!$graph->configured())return;
        $event=$graph->getEvent($eventId);if(!$event)return;
        $db=Database::connection();$s=$db->prepare('SELECT id,graph_change_key FROM jobs WHERE graph_event_id=?');$s->execute([$eventId]);$job=$s->fetch();if(!$job)return;
        $tz=(string)Env::get('APP_TIMEZONE','America/Chicago');
        $start=$event['start']['dateTime']??null;$end=$event['end']['dateTime']??null;
        if($start){$start=(new \DateTime($start,new \DateTimeZone($event['start']['timeZone']??$tz)))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d H:i:s');}
        if($end){$end=(new \DateTime($end,new \DateTimeZone($event['end']['timeZone']??$tz)))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d H:i:s');}
        $status=!empty($event['isCancelled'])?'cancelled':null;
        $sql='UPDATE jobs SET scheduled_start=COALESCE(?,scheduled_start),scheduled_end=COALESCE(?,scheduled_end),graph_change_key=?,graph_last_synced_at=NOW()'.($status?',status=?':'').' WHERE id=?';
        $args=[$start,$end,$event['changeKey']??null];if($status)$args[]=$status;$args[]=(int)$job['id'];$db->prepare($sql)->execute($args);
    }
}
