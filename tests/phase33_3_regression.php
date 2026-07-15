<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$required = [
    'app/controllers/AiAssistantController.php',
    'app/services/AiDraftApplicationService.php',
    'app/services/AiPromptHistoryService.php',
    'database/migrate_phase33_3.sql',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = 'Missing ' . $file;
    }
}
$controller = file_get_contents($root . '/app/controllers/AiAssistantController.php') ?: '';
foreach (['human_reviewed', 'AiDraftApplicationService', 'ai_draft_application_events'] as $needle) {
    if (!str_contains($controller, $needle)) {
        $errors[] = 'AI controller missing ' . $needle;
    }
}
$migration = file_get_contents($root . '/database/migrate_phase33_3.sql') ?: '';
foreach (['ai_draft_application_events', 'ai_prompt_versions', 'ai_user_budgets'] as $needle) {
    if (!str_contains($migration, $needle)) {
        $errors[] = 'Migration missing ' . $needle;
    }
}
if ($errors) {
    fwrite(STDERR, "CastorWorks 0.33.3 regression failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "CastorWorks 0.33.3 regression passed.\n";
