<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$required = [
    'app/controllers/AiAssistantController.php',
    'app/services/AiGovernanceService.php',
    'app/services/AiUsageReportService.php',
    'app/services/IntegrationHealthService.php',
    'app/views/portal/ai/index.php',
    'app/views/portal/estimates/show.php',
    'app/views/portal/conversations/show.php',
    'database/migrate_phase33_4.sql',
];

foreach ($required as $relative) {
    if (!is_file($root . '/' . $relative)) {
        $errors[] = 'Missing ' . $relative;
    }
}

$checks = [
    'public/index.php' => [
        "/portal/ai/prompts/{id}/rollback",
        "/portal/ai/budgets/{id}",
        "->post('/portal/ai/drafts/{id}/reject'",
        "->post('/portal/ai/drafts/{id}/use'",
    ],
    'app/controllers/AiAssistantController.php' => [
        'rollbackPrompt',
        'updateBudget',
        'source_target_type',
        'AiUsageReportService',
    ],
    'app/services/AiGovernanceService.php' => [
        'ai_user_budgets',
        'daily_request_limit',
        'monthly_cost_limit_usd',
    ],
    'app/services/IntegrationHealthService.php' => [
        "'ai_assistant'",
        'private function aiAssistant',
    ],
    'app/views/portal/estimates/show.php' => ['AI estimate narrative'],
    'app/views/portal/conversations/show.php' => ['AI reply draft'],
    'database/migrate_phase33_4.sql' => ['source_target_type', 'idx_ai_usage_provider_created'],
];

foreach ($checks as $relative => $needles) {
    $content = @file_get_contents($root . '/' . $relative);
    if ($content === false) {
        $errors[] = 'Unable to read ' . $relative;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = $relative . ' missing ' . $needle;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "CastorWorks 0.33.4 regression failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "CastorWorks 0.33.4 regression passed.\n";
