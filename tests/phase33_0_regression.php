<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$required = [
    'app/services/AiProviderService.php',
    'app/services/AiContextService.php',
    'app/controllers/AiAssistantController.php',
    'app/views/portal/ai/index.php',
    'database/migrate_phase33_0.sql',
    'scripts/apply-release-0.33.0.php',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = 'Missing ' . $file;
    }
}
$provider = @file_get_contents($root . '/app/services/AiProviderService.php') ?: '';
foreach (['openai', 'azure_openai', 'ollama', 'prompt_hash'] as $needle) {
    if (!str_contains($provider, $needle)) {
        $errors[] = 'AI provider service is missing ' . $needle;
    }
}
$migration = @file_get_contents($root . '/database/migrate_phase33_0.sql') ?: '';
foreach (['ai_provider_settings', 'ai_usage_logs', 'ai_saved_prompts'] as $needle) {
    if (!str_contains($migration, $needle)) {
        $errors[] = 'Migration is missing ' . $needle;
    }
}
if ($errors !== []) {
    fwrite(STDERR, "CastorWorks 0.33.0 regression failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "CastorWorks 0.33.0 regression passed.\n";
