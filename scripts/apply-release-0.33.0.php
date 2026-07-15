<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$changes = [];

function patchFile(string $path, callable $patcher, array &$changes): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Required file not found: ' . $path);
    }
    $original = file_get_contents($path);
    if ($original === false) {
        throw new RuntimeException('Unable to read: ' . $path);
    }
    $updated = $patcher($original);
    if ($updated === $original) {
        return;
    }
    $backup = $path . '.pre-0.33.0';
    if (!is_file($backup)) {
        copy($path, $backup);
    }
    if (file_put_contents($path, $updated) === false) {
        throw new RuntimeException('Unable to write: ' . $path);
    }
    $changes[] = substr($path, strlen(dirname(__DIR__)) + 1);
}

patchFile($root . '/public/index.php', static function (string $content): string {
    if (str_contains($content, "'/portal/ai'")) {
        return $content;
    }
    $routes = <<<'ROUTES'
 $r->get('/portal/ai',[\App\Controllers\AiAssistantController::class,'index']);$r->post('/portal/ai/ask',[\App\Controllers\AiAssistantController::class,'ask']);$r->post('/portal/ai/settings',[\App\Controllers\AiAssistantController::class,'updateSettings']);$r->post('/portal/ai/prompts',[\App\Controllers\AiAssistantController::class,'savePrompt']); 
ROUTES;
    $needle = '$r->dispatch($_SERVER[\'REQUEST_METHOD\'],$_SERVER[\'REQUEST_URI\']);';
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Could not locate Router dispatch statement in public/index.php.');
    }
    return str_replace($needle, $routes . $needle, $content);
}, $changes);

patchFile($root . '/app/views/partials/portal-sidebar.php', static function (string $content): string {
    if (str_contains($content, "'/portal/ai'")) {
        return $content;
    }
    $needle = "['/portal/workflows','fa-solid fa-diagram-project','Workflow Automation']";
    $replacement = "['/portal/ai','fa-solid fa-wand-magic-sparkles','AI Assistant']," . $needle;
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Could not locate System menu insertion point.');
    }
    return str_replace($needle, $replacement, $content);
}, $changes);

patchFile($root . '/app/controllers/HealthController.php', static function (string $content): string {
    if (str_contains($content, "'AI Assistant'")) {
        return $content;
    }
    $needle = '$checks[]=[\'name\'=>\'PHP\'';
    $replacement = '$checks[]=(new \\App\\Services\\AiProviderService(\\App\\Core\\Database::connection()))->healthCheck(); ' . $needle;
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Could not locate PHP health check insertion point.');
    }
    return str_replace($needle, $replacement, $content);
}, $changes);

$envExample = $root . '/.env.example';
if (is_file($envExample)) {
    patchFile($envExample, static function (string $content): string {
        if (str_contains($content, 'OPENAI_API_KEY=')) {
            return $content;
        }
        return rtrim($content) . "\n\n# CastorWorks AI Assistant\nOPENAI_API_KEY=\nAZURE_OPENAI_ENDPOINT=\nAZURE_OPENAI_API_KEY=\nAZURE_OPENAI_DEPLOYMENT=\nAZURE_OPENAI_API_VERSION=2024-10-21\nOLLAMA_ENDPOINT=http://127.0.0.1:11434\n";
    }, $changes);
}

$changelog = $root . '/CHANGELOG.md';
if (is_file($changelog)) {
    patchFile($changelog, static function (string $content): string {
        if (str_contains($content, '## 0.33.0')) {
            return $content;
        }
        $entry = "## 0.33.0 - AI Assistant Foundation\n\n- Added OpenAI, Azure OpenAI, and Ollama provider support.\n- Added aggregate operational context, saved prompts, usage audit records, and AI health monitoring.\n- AI is disabled by default and stores prompt hashes rather than prompt bodies in usage logs.\n\n";
        return $entry . $content;
    }, $changes);
}

echo $changes === [] ? "Release source patches were already applied.\n" : "Patched:\n - " . implode("\n - ", $changes) . "\n";
