<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'Controller exists' => is_file($root . '/app/controllers/BusinessIntelligenceController.php'),
    'View exists' => is_file($root . '/app/views/portal/business-intelligence/index.php'),
    'Stylesheet exists' => is_file($root . '/public/assets/css/business-intelligence.css'),
    'Migration exists' => is_file($root . '/database/migrate_phase27.sql'),
];

$index = file_get_contents($root . '/public/index.php') ?: '';
$sidebar = file_get_contents($root . '/app/views/partials/portal-sidebar.php') ?: '';
$controller = file_get_contents($root . '/app/controllers/BusinessIntelligenceController.php') ?: '';
$migration = file_get_contents($root . '/database/migrate_phase27.sql') ?: '';

$checks['Dashboard route registered'] = str_contains($index, "/portal/business-intelligence'");
$checks['Export route registered'] = str_contains($index, "/portal/business-intelligence/export'");
$checks['Sidebar link registered'] = str_contains($sidebar, 'Business Intelligence');
$checks['Controller includes conversion KPI'] = str_contains($controller, 'estimate_conversion');
$checks['Controller includes receivables aging'] = str_contains($controller, 'receivablesAging');
$checks['Migration is MySQL conditional'] = str_contains($migration, 'information_schema.STATISTICS');
$checks['No MariaDB ADD COLUMN IF NOT EXISTS'] = !str_contains($migration, 'ADD COLUMN IF NOT EXISTS');

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Phase 27 regression failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "ServiceOS 0.27.0 regression checks passed.\n";
