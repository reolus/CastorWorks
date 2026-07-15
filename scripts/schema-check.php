<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script may only be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/app/core/Env.php';
Env::load($root . '/.env');
require_once $root . '/app/core/Database.php';

$requirements = [
    'documents' => ['reference_type', 'reference_id', 'original_name'],
    'webhook_events' => ['received_at'],
    'invoices' => ['customer_id', 'job_id'],
    'schema_migrations' => ['migration', 'checksum', 'applied_at'],
    'properties' => ['latitude','longitude','geocode_source','geocode_status','geocoded_at'],
    'map_provider_settings' => ['provider','fallback_provider','cache_days','active'],
    'geocoding_cache' => ['address_hash','latitude','longitude','expires_at'],
    'gps_location_history' => ['user_id','job_id','latitude','longitude','captured_at'],
    'gps_tracking_policies' => ['enabled','clocked_in_only','update_interval_seconds','arrival_radius_meters','stale_after_minutes','retention_days'],
    'route_plans' => ['route_date','crew_id','status','version_no','current_distance_miles','optimized_distance_miles','distance_savings_miles','duration_savings_minutes'],
    'route_plan_stops' => ['route_plan_id','job_id','stop_order','original_order','is_locked'],
    'route_plan_history' => ['route_plan_id','action','created_at'],
    'users' => ['last_location_update'],
    'jobs' => ['actual_arrival','actual_departure','estimated_arrival','eta_calculated_at','eta_status','eta_late_minutes','eta_notified_at','delay_notified_at'],
    'eta_notification_settings' => ['enabled','on_the_way_minutes','late_threshold_minutes','average_speed_mph'],
    'route_progress_events' => ['job_id','event_type','created_at'],
    'eta_worker_runs' => ['run_date','status','jobs_updated','completed_at'],
];

try {
    $pdo = Database::connection();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($database === '') {
        throw new RuntimeException('No database is selected.');
    }

    $tableStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $columnStmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );

    $issues = [];

    echo "CastorWorks schema check\n";
    echo "Database: {$database}\n\n";

    foreach ($requirements as $table => $columns) {
        $tableStmt->execute([$database, $table]);

        if ((int) $tableStmt->fetchColumn() === 0) {
            $issues[] = "Missing table: {$table}";
            echo "[FAIL] {$table}: table is missing\n";
            continue;
        }

        $columnStmt->execute([$database, $table]);
        $existing = array_map(
            static fn(array $row): string => (string) $row['COLUMN_NAME'],
            $columnStmt->fetchAll(\PDO::FETCH_ASSOC)
        );

        $missing = array_values(array_diff($columns, $existing));

        if ($missing !== []) {
            $issues[] = "{$table}: missing " . implode(', ', $missing);
            echo "[FAIL] {$table}: missing " . implode(', ', $missing) . "\n";
            continue;
        }

        echo "[ OK ] {$table}: required columns present\n";
    }

    echo "\n";

    if ($issues !== []) {
        echo "Schema alignment failed with " . count($issues) . " issue(s).\n";
        foreach ($issues as $issue) {
            echo " - {$issue}\n";
        }
        exit(2);
    }

    echo "Schema alignment passed.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Schema check failed: {$e->getMessage()}\n");
    exit(1);
}
