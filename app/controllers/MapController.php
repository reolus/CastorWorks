<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use DateTimeImmutable;

final class MapController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $date = $this->validDate((string)($_GET['date'] ?? date('Y-m-d')));
        $crewId = max(0, (int)($_GET['crew_id'] ?? 0));
        $status = trim((string)($_GET['status'] ?? ''));

        $properties = $pdo->query(
            "SELECT p.id,p.customer_id,p.label,p.address1,p.address2,p.city,p.state,p.postal_code,
                    p.latitude,p.longitude,p.geocode_status,p.geocode_source,c.display_name
             FROM properties p
             JOIN customers c ON c.id=p.customer_id
             WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL
             ORDER BY c.display_name,p.label"
        )->fetchAll();

        $sql = "SELECT j.id,j.job_number,j.status,j.scheduled_start,j.scheduled_end,j.route_order,
                       j.crew_id,j.assigned_user_id,j.assigned_vehicle_id,j.service_summary,
                       COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude,
                       p.address1,p.city,p.state,p.postal_code,c.display_name,
                       cr.name crew_name,u.name technician_name,v.unit_number
                FROM jobs j
                JOIN customers c ON c.id=j.customer_id
                LEFT JOIN properties p ON p.id=j.property_id
                LEFT JOIN crews cr ON cr.id=j.crew_id
                LEFT JOIN users u ON u.id=j.assigned_user_id
                LEFT JOIN vehicles v ON v.id=j.assigned_vehicle_id
                WHERE DATE(COALESCE(j.scheduled_start,j.route_date))=?
                  AND COALESCE(j.latitude,p.latitude) IS NOT NULL
                  AND COALESCE(j.longitude,p.longitude) IS NOT NULL";
        $args = [$date];
        if ($crewId > 0) { $sql .= ' AND j.crew_id=?'; $args[] = $crewId; }
        if ($status !== '') { $sql .= ' AND j.status=?'; $args[] = $status; }
        $sql .= ' ORDER BY COALESCE(j.crew_id,0),COALESCE(j.route_order,999),COALESCE(j.scheduled_start,j.route_date),j.id';
        $stmt = $pdo->prepare($sql); $stmt->execute($args); $jobs = $stmt->fetchAll();

        $crews = $pdo->query(
            "SELECT id,name,current_latitude,current_longitude,last_location_update
             FROM crews WHERE active=1 ORDER BY name"
        )->fetchAll();
        $vehicles = $pdo->query(
            "SELECT id,unit_number,make,model,status,current_latitude,current_longitude,last_location_update
             FROM vehicles WHERE status IN ('active','maintenance') ORDER BY unit_number"
        )->fetchAll();
        $statuses = $pdo->query("SELECT DISTINCT status FROM jobs WHERE status IS NOT NULL ORDER BY status")->fetchAll();
        $geocodeStats = $pdo->query(
            "SELECT COUNT(*) total,
                    SUM(latitude IS NOT NULL AND longitude IS NOT NULL) mapped,
                    SUM(latitude IS NULL OR longitude IS NULL) unmapped,
                    SUM(geocode_status='failed') failed
             FROM properties"
        )->fetch() ?: [];
        $planStmt=$pdo->prepare("SELECT id,status,version_no,crew_id,current_distance_miles,optimized_distance_miles,distance_savings_miles,duration_savings_minutes FROM route_plans WHERE route_date=? AND COALESCE(crew_id,0)=? AND status='proposed' ORDER BY version_no DESC,id DESC LIMIT 1");
        $planStmt->execute([$date,$crewId]);$routePlan=$planStmt->fetch()?:null;$routePlanStops=[];
        if($routePlan){$stopStmt=$pdo->prepare("SELECT rps.stop_order,rps.is_locked,j.id job_id,COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude,c.display_name FROM route_plan_stops rps JOIN jobs j ON j.id=rps.job_id JOIN customers c ON c.id=j.customer_id LEFT JOIN properties p ON p.id=j.property_id WHERE rps.route_plan_id=? ORDER BY rps.stop_order");$stopStmt->execute([(int)$routePlan['id']]);$routePlanStops=$stopStmt->fetchAll();}

        View::render('portal/map/index', [
            'title' => 'Dispatch Map', 'date' => $date, 'crewId' => $crewId, 'statusFilter' => $status,
            'properties' => $properties, 'jobs' => $jobs, 'crews' => $crews, 'vehicles' => $vehicles,
            'statuses' => $statuses, 'geocodeStats' => $geocodeStats,'routePlan'=>$routePlan,'routePlanStops'=>$routePlanStops,
        ], 'portal');
    }

    public function updatePropertyCoordinates(string $id): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader'); verify_csrf();
        $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $this->json(['ok' => false, 'error' => 'Invalid coordinates.'], 422); return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "UPDATE properties SET latitude=?,longitude=?,geocode_source='manual',geocode_status='verified',
             geocode_accuracy='manual_map',geocode_verified_at=NOW(),geocode_error=NULL WHERE id=?"
        );
        $stmt->execute([$latitude, $longitude, (int)$id]);
        AuditService::log('property.coordinates_updated', 'property', (int)$id, ['latitude' => $latitude, 'longitude' => $longitude]);
        $this->json(['ok' => true, 'latitude' => $latitude, 'longitude' => $longitude]);
    }

    private function validDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : date('Y-m-d');
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status); header('Content-Type: application/json'); echo json_encode($payload);
    }
}
