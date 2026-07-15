<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use Throwable;
use App\Services\RouteProgressService;

final class RouteController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $date = $this->date((string)($_GET['date'] ?? date('Y-m-d')));
        $crewId = max(0, (int)($_GET['crew_id'] ?? 0));

        $sql = "SELECT j.*,c.display_name,CONCAT_WS(', ',p.address1,p.city,p.state,p.postal_code) full_address,
                       u.name technician_name,cr.name crew_name,
                       COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude
                FROM jobs j
                JOIN customers c ON c.id=j.customer_id
                LEFT JOIN properties p ON p.id=j.property_id
                LEFT JOIN users u ON u.id=j.assigned_user_id
                LEFT JOIN crews cr ON cr.id=j.crew_id
                WHERE DATE(COALESCE(j.scheduled_start,j.route_date))=? AND j.status<>'cancelled'";
        $args = [$date];
        if ($crewId > 0) {
            $sql .= ' AND j.crew_id=?';
            $args[] = $crewId;
        }
        $sql .= ' ORDER BY COALESCE(j.route_order,9999),COALESCE(j.scheduled_start,j.route_date),j.id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $jobs = $stmt->fetchAll();

        $planSql = "SELECT rp.*,u.name created_by_name,a.name accepted_by_name
                    FROM route_plans rp
                    LEFT JOIN users u ON u.id=rp.created_by
                    LEFT JOIN users a ON a.id=rp.accepted_by
                    WHERE rp.route_date=? AND COALESCE(rp.crew_id,0)=?
                    ORDER BY rp.version_no DESC,rp.id DESC LIMIT 10";
        $planStmt = $pdo->prepare($planSql);
        $planStmt->execute([$date, $crewId]);
        $plans = $planStmt->fetchAll();
        $activePlan = null;
        foreach ($plans as $candidate) {
            if ($candidate['status'] === 'proposed') {
                $activePlan = $candidate;
                break;
            }
        }
        $planStops = [];
        if ($activePlan) {
            $stops = $pdo->prepare(
                "SELECT rps.*,j.job_number,j.service_summary,j.scheduled_start,c.display_name,
                        CONCAT_WS(', ',p.address1,p.city,p.state,p.postal_code) full_address,
                        COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude
                 FROM route_plan_stops rps
                 JOIN jobs j ON j.id=rps.job_id
                 JOIN customers c ON c.id=j.customer_id
                 LEFT JOIN properties p ON p.id=j.property_id
                 WHERE rps.route_plan_id=? ORDER BY rps.stop_order"
            );
            $stops->execute([(int)$activePlan['id']]);
            $planStops = $stops->fetchAll();
        }

        $crews = $pdo->query("SELECT id,name FROM crews WHERE active=1 ORDER BY name")->fetchAll();
        $addresses = array_values(array_filter(array_map(static fn(array $job): ?string => $job['full_address'] ?: null, $jobs)));
        $mapUrl = '';
        if ($addresses) {
            $mapUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($addresses[0])
                . '&destination=' . rawurlencode((string)end($addresses))
                . '&waypoints=' . rawurlencode(implode('|', array_slice($addresses, 1, -1)));
        }

        $progress = (new RouteProgressService($pdo))->snapshot($date, $crewId);

        View::render('portal/routes/index', [
            'title' => 'Route Planning',
            'date' => $date,
            'crewId' => $crewId,
            'crews' => $crews,
            'jobs' => $jobs,
            'mapUrl' => $mapUrl,
            'plans' => $plans,
            'activePlan' => $activePlan,
            'planStops' => $planStops,
            'progress' => $progress,
        ], 'portal');
    }

    public function save(): void
    {
        Auth::requireRole('office', 'crew_leader', 'owner');
        verify_csrf();
        $date = $this->date((string)($_POST['date'] ?? date('Y-m-d')));
        $crewId = max(0, (int)($_POST['crew_id'] ?? 0));
        $ids = array_values(array_filter(array_map('intval', $_POST['job_ids'] ?? [])));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('UPDATE jobs SET route_order=?,route_date=? WHERE id=?');
            foreach ($ids as $index => $id) {
                $query->execute([$index + 1, $date, $id]);
            }
            $pdo->commit();
            flash('success', 'Route order saved.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
        }
        redirect('/portal/routes?date=' . rawurlencode($date) . ($crewId ? '&crew_id=' . $crewId : ''));
    }

    private function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : date('Y-m-d');
    }
}
