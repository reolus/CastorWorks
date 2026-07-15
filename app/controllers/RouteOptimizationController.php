<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AuditService;
use App\Services\RoutePlanningService;
use RuntimeException;
use Throwable;

final class RouteOptimizationController
{
    public function optimize(): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader');
        verify_csrf();
        $date = $this->date((string)($_POST['date'] ?? date('Y-m-d')));
        $crewId = max(0, (int)($_POST['crew_id'] ?? 0));
        $pdo = Database::connection();
        $jobs = $this->jobs($date, $crewId);
        $locked = array_values(array_filter(array_map('intval', $_POST['locked_job_ids'] ?? [])));

        try {
            $proposal = RoutePlanningService::buildProposal($jobs, $locked);
            $pdo->beginTransaction();
            $version = $this->nextVersion($date, $crewId);
            $stmt = $pdo->prepare(
                "INSERT INTO route_plans
                 (route_date,crew_id,status,version_no,optimization_method,provider,current_distance_miles,
                  optimized_distance_miles,distance_savings_miles,current_duration_minutes,
                  optimized_duration_minutes,duration_savings_minutes,created_by)
                 VALUES(?,NULLIF(?,0),'proposed',?,'nearest_neighbor',?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $date, $crewId, $version, $proposal['provider'], $proposal['current_distance_miles'],
                $proposal['optimized_distance_miles'], $proposal['distance_savings_miles'],
                $proposal['current_duration_minutes'], $proposal['optimized_duration_minutes'],
                $proposal['duration_savings_minutes'], Auth::id(),
            ]);
            $planId = (int)$pdo->lastInsertId();
            $currentOrder = [];
            foreach ($proposal['current'] as $index => $job) {
                $currentOrder[(int)$job['id']] = $index + 1;
            }
            $stop = $pdo->prepare(
                'INSERT INTO route_plan_stops(route_plan_id,job_id,stop_order,original_order,is_locked) VALUES(?,?,?,?,?)'
            );
            foreach ($proposal['optimized'] as $index => $job) {
                $jobId = (int)$job['id'];
                $stop->execute([$planId, $jobId, $index + 1, $currentOrder[$jobId] ?? null, in_array($jobId, $locked, true) ? 1 : 0]);
            }
            $this->history($planId, 'created', ['version' => $version, 'crew_id' => $crewId]);
            $pdo->commit();
            AuditService::log('route.plan_created', 'route_plan', $planId, ['date' => $date, 'crew_id' => $crewId]);
            flash('success', 'Route proposal created. Review it before applying changes to live jobs.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', $e->getMessage());
        }
        redirect('/portal/routes?date=' . rawurlencode($date) . ($crewId ? '&crew_id=' . $crewId : ''));
    }

    public function accept(string $id): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader');
        verify_csrf();
        $pdo = Database::connection();
        $plan = $this->plan((int)$id);
        if ($plan['status'] !== 'proposed') {
            flash('warning', 'Only proposed route plans can be accepted.');
            redirect('/portal/routes?date=' . rawurlencode($plan['route_date']));
        }
        $stops = $pdo->prepare('SELECT * FROM route_plan_stops WHERE route_plan_id=? ORDER BY stop_order');
        $stops->execute([(int)$id]);
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE jobs SET route_order=?,route_date=? WHERE id=?');
            foreach ($stops->fetchAll() as $stop) {
                $update->execute([(int)$stop['stop_order'], $plan['route_date'], (int)$stop['job_id']]);
            }
            $pdo->prepare("UPDATE route_plans SET status='accepted',accepted_at=NOW(),accepted_by=? WHERE id=?")
                ->execute([Auth::id(), (int)$id]);
            $this->history((int)$id, 'accepted');
            $pdo->commit();
            AuditService::log('route.plan_accepted', 'route_plan', (int)$id);
            flash('success', 'Optimized route applied to the live schedule.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
        }
        redirect('/portal/routes?date=' . rawurlencode($plan['route_date']) . (!empty($plan['crew_id']) ? '&crew_id=' . (int)$plan['crew_id'] : ''));
    }

    public function reject(string $id): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader');
        verify_csrf();
        $plan = $this->plan((int)$id);
        Database::connection()->prepare("UPDATE route_plans SET status='rejected',rejected_at=NOW(),rejected_by=? WHERE id=? AND status='proposed'")
            ->execute([Auth::id(), (int)$id]);
        $this->history((int)$id, 'rejected');
        AuditService::log('route.plan_rejected', 'route_plan', (int)$id);
        flash('success', 'Route proposal rejected. Live job order was not changed.');
        redirect('/portal/routes?date=' . rawurlencode($plan['route_date']));
    }

    public function toggleLock(string $id, string $stopId): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader');
        verify_csrf();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE route_plan_stops SET is_locked=IF(is_locked=1,0,1) WHERE id=? AND route_plan_id=?');
        $stmt->execute([(int)$stopId, (int)$id]);
        $this->history((int)$id, 'stop_lock_toggled', ['stop_id' => (int)$stopId]);
        $plan = $this->plan((int)$id);
        redirect('/portal/routes?date=' . rawurlencode($plan['route_date']) . (!empty($plan['crew_id']) ? '&crew_id=' . (int)$plan['crew_id'] : ''));
    }

    /** @return array<int,array<string,mixed>> */
    private function jobs(string $date, int $crewId): array
    {
        $sql = "SELECT j.id,j.job_number,j.route_order,j.scheduled_start,j.scheduled_end,j.crew_id,j.assigned_user_id,
                       j.service_summary,COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude,
                       c.display_name,p.address1,p.city,p.state,p.postal_code
                FROM jobs j
                JOIN customers c ON c.id=j.customer_id
                LEFT JOIN properties p ON p.id=j.property_id
                WHERE DATE(COALESCE(j.scheduled_start,j.route_date))=? AND j.status<>'cancelled'";
        $args = [$date];
        if ($crewId > 0) {
            $sql .= ' AND j.crew_id=?';
            $args[] = $crewId;
        }
        $sql .= ' ORDER BY COALESCE(j.route_order,9999),COALESCE(j.scheduled_start,j.route_date),j.id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed> */
    private function plan(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM route_plans WHERE id=?');
        $stmt->execute([$id]);
        $plan = $stmt->fetch();
        if (!$plan) {
            throw new RuntimeException('Route plan not found.');
        }
        return $plan;
    }

    private function nextVersion(string $date, int $crewId): int
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM route_plans WHERE route_date=? AND COALESCE(crew_id,0)=?');
        $stmt->execute([$date, $crewId]);
        return (int)$stmt->fetchColumn();
    }

    /** @param array<string,mixed> $details */
    private function history(int $planId, string $action, array $details = []): void
    {
        Database::connection()->prepare('INSERT INTO route_plan_history(route_plan_id,action,details,created_by) VALUES(?,?,?,?)')
            ->execute([$planId, $action, $details ? json_encode($details) : null, Auth::id()]);
    }

    private function date(string $value): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $parsed && $parsed->format('Y-m-d') === $value ? $value : date('Y-m-d');
    }
}
