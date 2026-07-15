<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$changes = [];

function patch(string $path, callable $fn, array &$changes): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing ' . $path);
    }
    $old = file_get_contents($path);
    if ($old === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    $new = $fn($old);
    if ($new === $old) {
        return;
    }
    if (!is_file($path . '.pre-0.33.1')) {
        copy($path, $path . '.pre-0.33.1');
    }
    file_put_contents($path, $new);
    $changes[] = substr($path, strlen(dirname(__DIR__)) + 1);
}

patch($root . '/public/index.php', static function (string $content): string {
    if (str_contains($content, "'/portal/ai/daily-brief'")) {
        return $content;
    }
    $routes = <<<'ROUTES'
 $r->get('/portal/ai',[\App\Controllers\AiAssistantController::class,'index']);$r->post('/portal/ai/ask',[\App\Controllers\AiAssistantController::class,'ask']);$r->post('/portal/ai/daily-brief',[\App\Controllers\AiAssistantController::class,'dailyBrief']);$r->get('/portal/ai/search',[\App\Controllers\AiAssistantController::class,'search']);$r->post('/portal/ai/drafts',[\App\Controllers\AiAssistantController::class,'createDraft']);$r->post('/portal/ai/drafts/{id}/approve',[\App\Controllers\AiAssistantController::class,'approveDraft']);$r->post('/portal/ai/settings',[\App\Controllers\AiAssistantController::class,'updateSettings']);$r->post('/portal/ai/prompts',[\App\Controllers\AiAssistantController::class,'savePrompt']); 
ROUTES;
    $needle = '$r->dispatch($_SERVER[\'REQUEST_METHOD\'],$_SERVER[\'REQUEST_URI\']);';
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Router dispatch marker not found.');
    }
    return str_replace($needle, $routes . $needle, $content);
}, $changes);

patch($root . '/app/views/partials/portal-sidebar.php', static function (string $content): string {
    if (str_contains($content, "'/portal/ai'")) {
        return $content;
    }
    $needle = "['/portal/workflows','fa-solid fa-diagram-project','Workflow Automation']";
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Sidebar marker not found.');
    }
    return str_replace($needle, "['/portal/ai','fa-solid fa-wand-magic-sparkles','AI Assistant']," . $needle, $content);
}, $changes);

patch($root . '/app/controllers/HealthController.php', static function (string $content): string {
    if (str_contains($content, "'AI Assistant'")) {
        return $content;
    }
    $needle = '$checks[]=[\'name\'=>\'PHP\'';
    if (!str_contains($content, $needle)) {
        throw new RuntimeException('Health marker not found.');
    }
    return str_replace(
        $needle,
        '$checks[]=(new \\App\\Services\\AiProviderService(\\App\\Core\\Database::connection()))->healthCheck(); ' . $needle,
        $content
    );
}, $changes);

if (is_file($root . '/.env.example')) {
    patch($root . '/.env.example', static function (string $content): string {
        if (str_contains($content, 'OPENAI_API_KEY=')) {
            return $content;
        }
        return rtrim($content) . "\n\n# CastorWorks AI\nOPENAI_API_KEY=\nAZURE_OPENAI_ENDPOINT=\nAZURE_OPENAI_API_KEY=\nAZURE_OPENAI_DEPLOYMENT=\nAZURE_OPENAI_API_VERSION=2024-10-21\nOLLAMA_ENDPOINT=http://127.0.0.1:11434\n";
    }, $changes);
}

echo $changes
    ? "Patched:\n - " . implode("\n - ", $changes) . "\n"
    : "Release source patches already applied.\n";
