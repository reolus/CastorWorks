<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

final class RouteAnalyticsService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function buildDate(string $date): array
    {
        $date = $this->date($date);
        $routes = $this->routes($date);
        $routeCount = 0;
        $crewCount = 0;
        $technicianCount = 0;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM route_analytics WHERE route_date=?')->execute([$date]);
            $this->pdo->prepare('DELETE FROM crew_daily_statistics WHERE statistic_date=?')->execute([$date]);
            $this->pdo->prepare('DELETE FROM technician_daily_statistics WHERE statistic_date=?')->execute([$date]);

            foreach ($routes as $route) {
                $metrics = $this->metrics($route['jobs']);
                $this->pdo->prepare(
                    'INSERT INTO route_analytics(route_date,crew_id,user_id,vehicle_id,jobs_total,jobs_completed,planned_miles,actual_miles,planned_minutes,drive_minutes,work_minutes,idle_minutes,revenue,fuel_cost,efficiency_score,route_deviations,calculated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([
                    $date,$route['crew_id'] ?: null,$route['user_id'] ?: null,$route['vehicle_id'] ?: null,
                    $metrics['jobs_total'],$metrics['jobs_completed'],$metrics['planned_miles'],$metrics['actual_miles'],
                    $metrics['planned_minutes'],$metrics['drive_minutes'],$metrics['work_minutes'],$metrics['idle_minutes'],
                    $metrics['revenue'],$metrics['fuel_cost'],$metrics['efficiency_score'],$metrics['route_deviations']
                ]);
                $routeCount++;
            }

            $crewCount = $this->aggregateCrews($date);
            $technicianCount = $this->aggregateTechnicians($date);
            $this->pdo->prepare('INSERT INTO operational_analytics_runs(run_date,status,routes_built,crews_built,technicians_built,completed_at) VALUES(?,?,?,?,?,NOW())')
                ->execute([$date,'ok',$routeCount,$crewCount,$technicianCount]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->pdo->prepare('INSERT INTO operational_analytics_runs(run_date,status,detail,completed_at) VALUES(?,?,?,NOW())')
                ->execute([$date,'failed',substr($e->getMessage(),0,1000)]);
            throw $e;
        }
        return ['date'=>$date,'routes'=>$routeCount,'crews'=>$crewCount,'technicians'=>$technicianCount];
    }

    public function dashboard(string $from, string $to): array
    {
        $from=$this->date($from);$to=$this->date($to);if($from>$to)[$from,$to]=[$to,$from];
        $s=$this->pdo->prepare("SELECT COUNT(*) routes,COALESCE(SUM(jobs_total),0) jobs,COALESCE(SUM(jobs_completed),0) completed,COALESCE(SUM(planned_miles),0) planned_miles,COALESCE(SUM(actual_miles),0) actual_miles,COALESCE(SUM(drive_minutes),0) drive_minutes,COALESCE(SUM(work_minutes),0) work_minutes,COALESCE(SUM(idle_minutes),0) idle_minutes,COALESCE(SUM(revenue),0) revenue,COALESCE(SUM(fuel_cost),0) fuel_cost,COALESCE(AVG(efficiency_score),0) efficiency FROM route_analytics WHERE route_date BETWEEN ? AND ?");
        $s->execute([$from,$to]);$summary=$s->fetch()?:[];
        $q=$this->pdo->prepare("SELECT cs.*,c.name crew_name FROM crew_daily_statistics cs JOIN crews c ON c.id=cs.crew_id WHERE cs.statistic_date BETWEEN ? AND ? ORDER BY cs.statistic_date DESC,cs.efficiency_score DESC");$q->execute([$from,$to]);$crews=$q->fetchAll();
        $t=$this->pdo->prepare("SELECT ts.*,u.name technician_name FROM technician_daily_statistics ts JOIN users u ON u.id=ts.user_id WHERE ts.statistic_date BETWEEN ? AND ? ORDER BY ts.statistic_date DESC,ts.efficiency_score DESC");$t->execute([$from,$to]);$technicians=$t->fetchAll();
        $r=$this->pdo->prepare("SELECT ra.*,c.name crew_name,u.name technician_name,v.unit_number FROM route_analytics ra LEFT JOIN crews c ON c.id=ra.crew_id LEFT JOIN users u ON u.id=ra.user_id LEFT JOIN vehicles v ON v.id=ra.vehicle_id WHERE ra.route_date BETWEEN ? AND ? ORDER BY ra.route_date DESC,COALESCE(c.name,u.name)");$r->execute([$from,$to]);
        return ['summary'=>$summary,'crews'=>$crews,'technicians'=>$technicians,'routes'=>$r->fetchAll()];
    }

    private function routes(string $date): array
    {
        $q=$this->pdo->prepare("SELECT j.*,COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude,COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.job_id=j.id AND i.status<>'void'),0) revenue FROM jobs j LEFT JOIN properties p ON p.id=j.property_id WHERE DATE(COALESCE(j.route_date,j.scheduled_start))=? AND j.status<>'cancelled' ORDER BY COALESCE(j.crew_id,0),COALESCE(j.assigned_user_id,0),COALESCE(j.route_order,9999),j.scheduled_start,j.id");
        $q->execute([$date]);$groups=[];
        foreach($q->fetchAll() as $job){$key='c'.(int)($job['crew_id']??0).':u'.(int)($job['assigned_user_id']??0).':v'.(int)($job['assigned_vehicle_id']??0);if(!isset($groups[$key]))$groups[$key]=['crew_id'=>(int)($job['crew_id']??0),'user_id'=>(int)($job['assigned_user_id']??0),'vehicle_id'=>(int)($job['assigned_vehicle_id']??0),'jobs'=>[]];$groups[$key]['jobs'][]=$job;}
        return array_values($groups);
    }

    private function metrics(array $jobs): array
    {
        $planned=0.0;$actual=0.0;$drive=0;$work=0;$idle=0;$revenue=0.0;$completed=0;$deviations=0;$last=null;$lastEnd=null;
        foreach($jobs as $job){$revenue+=(float)$job['revenue'];if($job['status']==='completed')$completed++;
            if($last&&is_numeric($job['latitude'])&&is_numeric($job['longitude']))$planned+=$this->distance((float)$last[0],(float)$last[1],(float)$job['latitude'],(float)$job['longitude']);
            if(is_numeric($job['latitude'])&&is_numeric($job['longitude']))$last=[(float)$job['latitude'],(float)$job['longitude']];
            if(!empty($job['scheduled_start'])&&!empty($job['scheduled_end']))$work+=max(0,(int)round((strtotime($job['scheduled_end'])-strtotime($job['scheduled_start']))/60));
            if(!empty($job['actual_arrival'])&&!empty($job['actual_departure']))$work+=max(0,(int)round((strtotime($job['actual_departure'])-strtotime($job['actual_arrival']))/60));
            if($lastEnd&&!empty($job['actual_arrival']))$idle+=max(0,(int)round((strtotime($job['actual_arrival'])-$lastEnd)/60));
            if(!empty($job['actual_departure']))$lastEnd=strtotime($job['actual_departure']);
            if(($job['eta_status']??'')==='late')$deviations++;
        }
        $jobIds=array_column($jobs,'id');
        if($jobIds){$ph=implode(',',array_fill(0,count($jobIds),'?'));$q=$this->pdo->prepare("SELECT COALESCE(SUM(GREATEST(TIMESTAMPDIFF(MINUTE,clock_in,clock_out)-break_minutes,0)),0) FROM time_entries WHERE job_id IN ($ph) AND clock_out IS NOT NULL AND entry_type='travel'");$q->execute($jobIds);$drive=(int)$q->fetchColumn();$q=$this->pdo->prepare("SELECT COALESCE(SUM(GREATEST(TIMESTAMPDIFF(MINUTE,clock_in,clock_out)-break_minutes,0)),0) FROM time_entries WHERE job_id IN ($ph) AND clock_out IS NOT NULL AND entry_type='job'");$q->execute($jobIds);$actualWork=(int)$q->fetchColumn();if($actualWork>0)$work=$actualWork;}
        $actual=$planned; $plannedMinutes=(int)round(($planned/32)*60); if($drive===0)$drive=$plannedMinutes;
        $total=max(1,$drive+$work+$idle);$eff=max(0,min(100,round((($work/$total)*70)+(($completed/max(1,count($jobs)))*30),2)));
        $fuelCost=round(($actual/18)*3.25,2);
        return ['jobs_total'=>count($jobs),'jobs_completed'=>$completed,'planned_miles'=>round($planned,2),'actual_miles'=>round($actual,2),'planned_minutes'=>$plannedMinutes,'drive_minutes'=>$drive,'work_minutes'=>$work,'idle_minutes'=>$idle,'revenue'=>round($revenue,2),'fuel_cost'=>$fuelCost,'efficiency_score'=>$eff,'route_deviations'=>$deviations];
    }

    private function aggregateCrews(string $date): int
    {
        $sql="INSERT INTO crew_daily_statistics(statistic_date,crew_id,jobs_total,jobs_completed,drive_minutes,work_minutes,idle_minutes,miles,revenue,fuel_cost,efficiency_score) SELECT route_date,crew_id,SUM(jobs_total),SUM(jobs_completed),SUM(drive_minutes),SUM(work_minutes),SUM(idle_minutes),SUM(actual_miles),SUM(revenue),SUM(fuel_cost),AVG(efficiency_score) FROM route_analytics WHERE route_date=? AND crew_id IS NOT NULL GROUP BY route_date,crew_id";$s=$this->pdo->prepare($sql);$s->execute([$date]);return $s->rowCount();
    }
    private function aggregateTechnicians(string $date): int
    {
        $sql="INSERT INTO technician_daily_statistics(statistic_date,user_id,jobs_total,jobs_completed,drive_minutes,work_minutes,idle_minutes,miles,revenue,efficiency_score) SELECT route_date,user_id,SUM(jobs_total),SUM(jobs_completed),SUM(drive_minutes),SUM(work_minutes),SUM(idle_minutes),SUM(actual_miles),SUM(revenue),AVG(efficiency_score) FROM route_analytics WHERE route_date=? AND user_id IS NOT NULL GROUP BY route_date,user_id";$s=$this->pdo->prepare($sql);$s->execute([$date]);return $s->rowCount();
    }
    private function date(string $v):string{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v?$v:date('Y-m-d');}
    private function distance(float $a,float $b,float $c,float $d):float{$r=3958.8;$x=deg2rad($c-$a);$y=deg2rad($d-$b);$z=sin($x/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin($y/2)**2;return $r*2*atan2(sqrt($z),sqrt(max(0,1-$z)));}
}
