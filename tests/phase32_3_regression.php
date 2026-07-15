<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$mustContain = [
    'app/services/RoutePlanningService.php' => ['buildProposal', 'distance_savings_miles', 'optimizeWithLocks'],
    'app/controllers/RouteOptimizationController.php' => ['function accept', 'function reject', 'toggleLock', 'route_plan_history'],
    'app/controllers/RouteController.php' => ['activePlan', 'route_plan_stops', 'version_no'],
    'app/views/portal/routes/index.php' => ['Optimization ready for review', 'Accept and Apply', 'Plan history'],
    'database/migrate_phase32_3.sql' => ['route_plan_history', 'distance_savings_miles', 'is_locked'],
    'public/index.php' => ['/portal/routes/plans/{id}/accept', '/portal/routes/plans/{id}/reject'],
];
foreach ($mustContain as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) { $errors[] = "Missing {$file}"; continue; }
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) if (!str_contains($content, $needle)) $errors[] = "{$file} missing {$needle}";
}
if ($errors) { fwrite(STDERR, "CastorWorks 0.32.3 regression failed:\n- ".implode("\n- ",$errors)."\n"); exit(1); }
echo "CastorWorks 0.32.3 regression passed.\n";
