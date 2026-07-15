<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class LocationTrackingService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function policy(): array
    {
        $row = $this->pdo->query('SELECT * FROM gps_tracking_policies WHERE id=1')->fetch();
        return $row ?: [
            'enabled' => 0,
            'clocked_in_only' => 1,
            'update_interval_seconds' => 60,
            'arrival_radius_meters' => 125,
            'stale_after_minutes' => 10,
            'retention_days' => 90,
        ];
    }

    public function record(int $userId, array $payload): array
    {
        $policy = $this->policy();
        if (!(bool)($policy['enabled'] ?? false)) {
            throw new RuntimeException('GPS tracking is disabled by policy.');
        }

        $latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('Invalid GPS coordinates.');
        }

        $active = $this->activeAssignment($userId);
        if ((bool)($policy['clocked_in_only'] ?? true) && !$active) {
            throw new RuntimeException('Location updates are accepted only while clocked in.');
        }

        $jobId = (int)($payload['job_id'] ?? ($active['job_id'] ?? 0));
        $crewId = (int)($active['crew_id'] ?? 0);
        $vehicleId = (int)($active['vehicle_id'] ?? 0);

        if ($jobId > 0 && !$this->canAccessJob($userId, $jobId)) {
            throw new RuntimeException('The selected job is not assigned to this technician.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO gps_location_history
             (user_id,crew_id,vehicle_id,job_id,latitude,longitude,accuracy_meters,heading_degrees,speed_mph,source,captured_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $stmt->execute([
            $userId,
            $crewId ?: null,
            $vehicleId ?: null,
            $jobId ?: null,
            $latitude,
            $longitude,
            $this->nullableFloat($payload['accuracy'] ?? null),
            $this->nullableFloat($payload['heading'] ?? null),
            $this->metersPerSecondToMph($payload['speed'] ?? null),
            'browser',
        ]);

        if ($crewId > 0) {
            $this->pdo->prepare('UPDATE crews SET current_latitude=?,current_longitude=?,last_location_update=NOW() WHERE id=?')
                ->execute([$latitude, $longitude, $crewId]);
        }
        if ($vehicleId > 0) {
            $this->pdo->prepare('UPDATE vehicles SET current_latitude=?,current_longitude=?,last_location_update=NOW() WHERE id=?')
                ->execute([$latitude, $longitude, $vehicleId]);
        }
        $this->pdo->prepare('UPDATE users SET last_location_update=NOW() WHERE id=?')->execute([$userId]);

        $proximity = null;
        if ($jobId > 0) {
            $proximity = $this->updateArrivalState($jobId, $latitude, $longitude, (int)($policy['arrival_radius_meters'] ?? 125));
        }

        return [
            'captured_at' => date('Y-m-d H:i:s'),
            'job_id' => $jobId ?: null,
            'crew_id' => $crewId ?: null,
            'vehicle_id' => $vehicleId ?: null,
            'proximity' => $proximity,
        ];
    }

    private function activeAssignment(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.job_id,t.vehicle_id,j.crew_id
             FROM time_entries t
             LEFT JOIN jobs j ON j.id=t.job_id
             WHERE t.user_id=? AND t.clock_out IS NULL
             ORDER BY t.clock_in DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    private function canAccessJob(int $userId, int $jobId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM jobs j
             LEFT JOIN crew_members cm ON cm.crew_id=j.crew_id AND cm.active=1
             WHERE j.id=? AND (j.assigned_user_id=? OR cm.user_id=?)'
        );
        $stmt->execute([$jobId, $userId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function updateArrivalState(int $jobId, float $lat, float $lng, int $radius): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT j.actual_arrival,j.actual_departure,j.status,
                    COALESCE(j.latitude,p.latitude) latitude,COALESCE(j.longitude,p.longitude) longitude
             FROM jobs j LEFT JOIN properties p ON p.id=j.property_id WHERE j.id=?'
        );
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job || $job['latitude'] === null || $job['longitude'] === null) {
            return ['distance_meters' => null, 'state' => 'unmapped'];
        }

        $distance = $this->distanceMeters($lat, $lng, (float)$job['latitude'], (float)$job['longitude']);
        if ($distance <= $radius && empty($job['actual_arrival'])) {
            $this->pdo->prepare('UPDATE jobs SET actual_arrival=NOW() WHERE id=?')->execute([$jobId]);
            return ['distance_meters' => round($distance), 'state' => 'arrived'];
        }
        if ($distance > ($radius * 2) && !empty($job['actual_arrival']) && empty($job['actual_departure']) && $job['status'] === 'completed') {
            $this->pdo->prepare('UPDATE jobs SET actual_departure=NOW() WHERE id=?')->execute([$jobId]);
            return ['distance_meters' => round($distance), 'state' => 'departed'];
        }
        return ['distance_meters' => round($distance), 'state' => $distance <= $radius ? 'on_site' : 'en_route'];
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1); $dLambda = deg2rad($lng2 - $lng1);
        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    private function metersPerSecondToMph(mixed $value): ?float
    {
        return is_numeric($value) ? round((float)$value * 2.236936, 2) : null;
    }
}
