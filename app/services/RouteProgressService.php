<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use DateTimeImmutable;
use PDO;
use Throwable;

final class RouteProgressService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function settings(): array
    {
        $row = $this->pdo->query('SELECT * FROM eta_notification_settings WHERE id=1')->fetch();
        return $row ?: [
            'enabled' => 1,
            'send_on_the_way' => 1,
            'send_delay_notices' => 1,
            'on_the_way_minutes' => 30,
            'late_threshold_minutes' => 15,
            'minimum_recalculation_minutes' => 5,
            'average_speed_mph' => 32,
            'on_the_way_template' => 'Rock Bluffs Exterior Services is approximately {{eta_minutes}} minutes away from {{customer_name}}.',
            'delay_template' => 'Rock Bluffs Exterior Services is running about {{late_minutes}} minutes late. Updated arrival: {{eta_time}}.',
        ];
    }

    public function updateDate(string $date): array
    {
        $date = $this->normalizeDate($date);
        $settings = $this->settings();
        $jobs = $this->jobs($date);
        $groups = [];
        foreach ($jobs as $job) {
            $key = !empty($job['crew_id']) ? 'crew:' . (int)$job['crew_id'] : 'user:' . (int)($job['assigned_user_id'] ?? 0);
            $groups[$key][] = $job;
        }

        $updated = 0;
        $notifications = 0;
        $late = 0;
        foreach ($groups as $groupJobs) {
            $origin = $this->latestOrigin($groupJobs);
            $cursor = $this->startingTime($date, $groupJobs);
            foreach ($groupJobs as $job) {
                if (in_array((string)$job['status'], ['completed', 'cancelled'], true)) {
                    continue;
                }
                $distance = 0.0;
                if ($origin && $this->hasCoordinates($job)) {
                    $distance = $this->distance((float)$origin['latitude'], (float)$origin['longitude'], (float)$job['latitude'], (float)$job['longitude']);
                }
                $travelMinutes = (int)round(($distance / max(10.0, (float)$settings['average_speed_mph'])) * 60);
                $candidate = $cursor->modify('+' . max(0, $travelMinutes) . ' minutes');
                if (!empty($job['scheduled_start'])) {
                    $scheduled = new DateTimeImmutable((string)$job['scheduled_start']);
                    if ($candidate < $scheduled && $date !== date('Y-m-d')) {
                        $candidate = $scheduled;
                    }
                }
                $lateMinutes = 0;
                if (!empty($job['scheduled_start'])) {
                    $scheduled = new DateTimeImmutable((string)$job['scheduled_start']);
                    $lateMinutes = max(0, (int)floor(($candidate->getTimestamp() - $scheduled->getTimestamp()) / 60));
                }
                $status = $lateMinutes >= (int)$settings['late_threshold_minutes'] ? 'late' : 'on_time';
                if ($lateMinutes > 0) $late++;
                $this->pdo->prepare('UPDATE jobs SET estimated_arrival=?,eta_calculated_at=NOW(),eta_status=?,eta_late_minutes=?,travel_time_minutes=?,travel_distance_miles=? WHERE id=?')
                    ->execute([$candidate->format('Y-m-d H:i:s'), $status, $lateMinutes, $travelMinutes, round($distance, 2), (int)$job['id']]);
                $updated++;

                if ($date === date('Y-m-d') && (bool)$settings['enabled']) {
                    $notifications += $this->notifyIfNeeded($job, $candidate, $lateMinutes, $settings);
                }

                $origin = $this->hasCoordinates($job) ? ['latitude' => $job['latitude'], 'longitude' => $job['longitude']] : $origin;
                $duration = $this->jobDurationMinutes($job);
                $cursor = $candidate->modify('+' . $duration . ' minutes');
            }
        }

        $this->pdo->prepare('INSERT INTO eta_worker_runs(run_date,status,jobs_updated,notifications_sent,late_jobs,detail,completed_at) VALUES(?,?,?,?,?,?,NOW())')
            ->execute([$date, 'ok', $updated, $notifications, $late, 'ETA recalculation completed']);

        return ['date' => $date, 'jobs_updated' => $updated, 'notifications_sent' => $notifications, 'late_jobs' => $late];
    }

    public function snapshot(string $date, int $crewId = 0): array
    {
        $sql = "SELECT j.id,j.job_number,j.status,j.route_order,j.scheduled_start,j.estimated_arrival,j.actual_arrival,j.actual_departure,j.eta_status,j.eta_late_minutes,j.eta_calculated_at,
                       c.display_name,c.phone,cr.name crew_name,u.name technician_name
                FROM jobs j JOIN customers c ON c.id=j.customer_id
                LEFT JOIN crews cr ON cr.id=j.crew_id LEFT JOIN users u ON u.id=j.assigned_user_id
                WHERE DATE(COALESCE(j.scheduled_start,j.route_date))=? AND j.status<>'cancelled'";
        $args = [$this->normalizeDate($date)];
        if ($crewId > 0) { $sql .= ' AND j.crew_id=?'; $args[] = $crewId; }
        $sql .= ' ORDER BY COALESCE(j.route_order,9999),j.scheduled_start,j.id';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($args); $rows = $stmt->fetchAll();
        $summary = ['total' => count($rows), 'completed' => 0, 'in_progress' => 0, 'late' => 0, 'not_started' => 0];
        foreach ($rows as $row) {
            if ($row['status'] === 'completed') $summary['completed']++;
            elseif ($row['status'] === 'in_progress') $summary['in_progress']++;
            else $summary['not_started']++;
            if (($row['eta_status'] ?? '') === 'late') $summary['late']++;
        }
        return ['rows' => $rows, 'summary' => $summary];
    }

    private function notifyIfNeeded(array $job, DateTimeImmutable $eta, int $lateMinutes, array $settings): int
    {
        $phone = trim((string)($job['phone'] ?? ''));
        if ($phone === '') return 0;
        $minutesAway = max(0, (int)ceil(($eta->getTimestamp() - time()) / 60));
        $sent = 0;
        try {
            if ((bool)$settings['send_delay_notices'] && $lateMinutes >= (int)$settings['late_threshold_minutes'] && empty($job['delay_notified_at'])) {
                $text = $this->render((string)$settings['delay_template'], $job, $eta, $minutesAway, $lateMinutes);
                (new CommunicationManager())->sendSms($phone, $text, ['message_class' => 'transactional', 'category' => 'route_delay', 'job_id' => (int)$job['id']]);
                $this->pdo->prepare('UPDATE jobs SET delay_notified_at=NOW() WHERE id=?')->execute([(int)$job['id']]);
                $sent++;
            } elseif ((bool)$settings['send_on_the_way'] && $minutesAway <= (int)$settings['on_the_way_minutes'] && $minutesAway >= 0 && empty($job['eta_notified_at'])) {
                $text = $this->render((string)$settings['on_the_way_template'], $job, $eta, $minutesAway, $lateMinutes);
                (new CommunicationManager())->sendSms($phone, $text, ['message_class' => 'transactional', 'category' => 'technician_eta', 'job_id' => (int)$job['id']]);
                $this->pdo->prepare('UPDATE jobs SET eta_notified_at=NOW() WHERE id=?')->execute([(int)$job['id']]);
                $sent++;
            }
        } catch (Throwable $e) {
            $this->pdo->prepare('INSERT INTO route_progress_events(job_id,event_type,detail,created_at) VALUES(?,?,?,NOW())')
                ->execute([(int)$job['id'], 'notification_failed', substr($e->getMessage(), 0, 1000)]);
        }
        return $sent;
    }

    private function render(string $template, array $job, DateTimeImmutable $eta, int $minutesAway, int $lateMinutes): string
    {
        return strtr($template, [
            '{{customer_name}}' => (string)($job['display_name'] ?? 'your location'),
            '{{job_number}}' => (string)($job['job_number'] ?? ''),
            '{{eta_minutes}}' => (string)$minutesAway,
            '{{late_minutes}}' => (string)$lateMinutes,
            '{{eta_time}}' => $eta->format('g:i A'),
        ]);
    }

    private function jobs(string $date): array
    {
        $stmt = $this->pdo->prepare("SELECT j.*,c.display_name,c.phone,COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude
            FROM jobs j JOIN customers c ON c.id=j.customer_id LEFT JOIN properties p ON p.id=j.property_id
            WHERE DATE(COALESCE(j.scheduled_start,j.route_date))=? AND j.status NOT IN ('cancelled','completed')
            ORDER BY COALESCE(j.crew_id,0),COALESCE(j.assigned_user_id,0),COALESCE(j.route_order,9999),j.scheduled_start,j.id");
        $stmt->execute([$date]); return $stmt->fetchAll();
    }

    private function latestOrigin(array $jobs): ?array
    {
        $crewId = (int)($jobs[0]['crew_id'] ?? 0); $userId = (int)($jobs[0]['assigned_user_id'] ?? 0);
        if ($crewId > 0) { $stmt=$this->pdo->prepare('SELECT latitude,longitude FROM gps_location_history WHERE crew_id=? ORDER BY captured_at DESC LIMIT 1'); $stmt->execute([$crewId]); }
        else { $stmt=$this->pdo->prepare('SELECT latitude,longitude FROM gps_location_history WHERE user_id=? ORDER BY captured_at DESC LIMIT 1'); $stmt->execute([$userId]); }
        $row=$stmt->fetch(); return $row ?: null;
    }

    private function startingTime(string $date, array $jobs): DateTimeImmutable
    {
        if ($date === date('Y-m-d')) return new DateTimeImmutable('now');
        $first = $jobs[0]['scheduled_start'] ?? ($date . ' 08:00:00');
        return new DateTimeImmutable((string)$first);
    }

    private function jobDurationMinutes(array $job): int
    {
        if (!empty($job['scheduled_start']) && !empty($job['scheduled_end'])) {
            $a = strtotime((string)$job['scheduled_start']); $b = strtotime((string)$job['scheduled_end']);
            if ($a && $b && $b > $a) return max(15, (int)round(($b - $a) / 60));
        }
        return max(15, (int)Env::get('ROUTE_DEFAULT_JOB_MINUTES', 90));
    }

    private function hasCoordinates(array $job): bool { return is_numeric($job['latitude'] ?? null) && is_numeric($job['longitude'] ?? null); }
    private function normalizeDate(string $date): string { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d&&$d->format('Y-m-d')===$date?$date:date('Y-m-d'); }
    private function distance(float $a,float $b,float $c,float $d):float{$r=3958.8;$x=deg2rad($c-$a);$y=deg2rad($d-$b);$z=sin($x/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin($y/2)**2;return $r*2*atan2(sqrt($z),sqrt(max(0,1-$z)));}
}
