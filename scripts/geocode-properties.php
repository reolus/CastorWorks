<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Services\GeocodingService;

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/app/core/Env.php'; Env::load($root . '/.env');
require_once $root . '/app/core/Database.php';
require_once $root . '/app/services/GeocodingService.php';

$limit = 25;
foreach ($argv as $arg) if (str_starts_with($arg, '--limit=')) $limit = max(1, min(500, (int)substr($arg, 8)));
$force = in_array('--force', $argv, true);
$pdo = Database::connection();
$where = $force ? '1=1' : "(geocode_status IS NULL OR geocode_status IN ('pending','failed') OR latitude IS NULL OR longitude IS NULL)";
$stmt = $pdo->query("SELECT id,label,address1,city,state,postal_code FROM properties WHERE {$where} ORDER BY id LIMIT {$limit}");
$rows = $stmt->fetchAll();
$service = new GeocodingService($pdo); $ok = 0; $failed = 0;
foreach ($rows as $row) {
    echo sprintf('[RUN ] #%d %s - %s, %s %s', $row['id'], $row['label'], $row['city'], $row['state'], $row['postal_code']) . PHP_EOL;
    try { $result = $service->geocodeProperty((int)$row['id'], $force); echo sprintf("[ OK ] %.6f, %.6f\n", $result['latitude'], $result['longitude']); $ok++; }
    catch (Throwable $e) { echo '[FAIL] ' . $e->getMessage() . PHP_EOL; $failed++; }
    usleep(1100000);
}
echo "Completed. Success: {$ok}; failed: {$failed}; selected: " . count($rows) . PHP_EOL;
exit($failed > 0 ? 2 : 0);
