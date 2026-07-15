<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$requiredFiles = [
    'app/controllers/MarketingController.php',
    'app/views/portal/marketing/index.php',
    'database/migrate_phase29.sql',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing {$file}";
    }
}

$index = file_get_contents($root . '/public/index.php') ?: '';
$sidebar = file_get_contents($root . '/app/views/partials/portal-sidebar.php') ?: '';
$leadController = file_get_contents($root . '/app/controllers/LeadController.php') ?: '';
$processor = file_get_contents($root . '/scripts/process-campaigns.php') ?: '';
$migration = file_get_contents($root . '/database/migrate_phase29.sql') ?: '';

$checks = [
    'marketing route' => str_contains($index, "'/portal/marketing'"),
    'marketing controller import' => str_contains($index, 'MarketingController'),
    'marketing sidebar entry' => str_contains($sidebar, "'/portal/marketing'"),
    'lead attribution support' => str_contains($leadController, 'acquisition_campaign_id'),
    'campaign migration' => str_contains($migration, 'CREATE TABLE IF NOT EXISTS marketing_campaigns'),
    'coupon migration' => str_contains($migration, 'CREATE TABLE IF NOT EXISTS marketing_coupons'),
    'suppression enforcement' => str_contains($processor, 'marketing_suppressions'),
    'no MariaDB-only add column syntax' => !str_contains($migration, 'ADD COLUMN IF NOT EXISTS'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failures[] = "Failed: {$name}";
    }
}

if ($failures) {
    fwrite(STDERR, "ServiceOS 0.29.0 regression failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "ServiceOS 0.29.0 regression passed.\n";
