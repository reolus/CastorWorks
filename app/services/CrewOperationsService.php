<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class CrewOperationsService
{
    public static function crews(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT c.*, leader.name AS leader_name, v.unit_number AS vehicle_unit,
                    t.name AS territory_name, COUNT(cm.user_id) AS member_count
             FROM crews c
             LEFT JOIN users leader ON leader.id=c.crew_leader_id
             LEFT JOIN vehicles v ON v.id=c.default_vehicle_id
             LEFT JOIN service_territories t ON t.id=c.service_territory_id
             LEFT JOIN crew_members cm ON cm.crew_id=c.id AND cm.active=1
             WHERE c.active=1
             GROUP BY c.id ORDER BY c.name"
        )->fetchAll();
    }

    public static function crewMembers(PDO $pdo, int $crewId): array
    {
        $s=$pdo->prepare("SELECT u.id,u.name,u.email,u.role FROM crew_members cm JOIN users u ON u.id=cm.user_id WHERE cm.crew_id=? AND cm.active=1 AND u.status='active' ORDER BY cm.is_primary DESC,u.name");
        $s->execute([$crewId]);
        return $s->fetchAll();
    }

    public static function conflicts(PDO $pdo, int $crewId, string $start, string $end, ?int $ignoreJobId=null): array
    {
        $conflicts=[];
        $date=date('Y-m-d',strtotime($start));
        $members=self::crewMembers($pdo,$crewId);
        foreach($members as $member){
            $a=$pdo->prepare("SELECT status,start_time,end_time,notes FROM employee_availability WHERE user_id=? AND availability_date=? LIMIT 1");
            $a->execute([(int)$member['id'],$date]);
            $availability=$a->fetch();
            if($availability && in_array($availability['status'],['unavailable','time_off'],true)){
                $conflicts[]=$member['name'].' is '.str_replace('_',' ',$availability['status']).'.';
            } elseif($availability && $availability['status']==='limited'){
                $jobStart=date('H:i:s',strtotime($start));$jobEnd=date('H:i:s',strtotime($end));
                if(($availability['start_time'] && $jobStart<$availability['start_time'])||($availability['end_time'] && $jobEnd>$availability['end_time'])){
                    $conflicts[]=$member['name'].' is only available '.($availability['start_time']?:'?').'–'.($availability['end_time']?:'?').'.';
                }
            }
            $sql="SELECT COUNT(*) FROM jobs WHERE assigned_user_id=? AND status NOT IN ('cancelled','completed') AND scheduled_start < ? AND scheduled_end > ?";
            $args=[(int)$member['id'],$end,$start];
            if($ignoreJobId){$sql.=' AND id<>?';$args[]=$ignoreJobId;}
            $s=$pdo->prepare($sql);$s->execute($args);
            if((int)$s->fetchColumn()>0){$conflicts[]=$member['name'].' has an overlapping job.';}
        }
        return array_values(array_unique($conflicts));
    }

    public static function suggestions(PDO $pdo, int $jobId): array
    {
        $s=$pdo->prepare("SELECT j.*,p.postal_code,p.city FROM jobs j LEFT JOIN properties p ON p.id=j.property_id WHERE j.id=?");$s->execute([$jobId]);$job=$s->fetch();if(!$job)return [];
        $crews=self::crews($pdo);$out=[];
        foreach($crews as $crew){
            $score=50;$reasons=[];
            if(!empty($job['scheduled_start'])&&!empty($job['scheduled_end'])){
                $conflicts=self::conflicts($pdo,(int)$crew['id'],$job['scheduled_start'],$job['scheduled_end'],$jobId);
                $score-=count($conflicts)*25;
                if($conflicts)$reasons=array_merge($reasons,$conflicts);
                else{$score+=20;$reasons[]='All active members appear available.';}
            }
            if(!empty($crew['default_vehicle_id'])){$score+=10;$reasons[]='Default vehicle available for assignment.';}
            if(!empty($crew['service_territory_id'])){$score+=5;$reasons[]='Crew has a default territory.';}
            $load=$pdo->prepare("SELECT COUNT(*) FROM jobs WHERE crew_id=? AND DATE(COALESCE(scheduled_start,route_date))=DATE(COALESCE(?,CURDATE())) AND status NOT IN ('cancelled','completed')");
            $load->execute([(int)$crew['id'],$job['scheduled_start']]);$count=(int)$load->fetchColumn();
            $score-=min(25,$count*5);$reasons[]=$count.' other job(s) on that date.';
            $out[]=['crew'=>$crew,'score'=>max(0,$score),'reasons'=>$reasons,'job_count'=>$count];
        }
        usort($out,fn($a,$b)=>$b['score']<=>$a['score']);return $out;
    }
}
