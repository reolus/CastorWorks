<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$changes = [];

/**
 * Apply an idempotent text transformation and retain a one-time backup.
 */
function patch0332(string $path, callable $fn, array &$changes): void
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

    $backup = $path . '.pre-0.33.2';

    if (!is_file($backup) && !copy($path, $backup)) {
        throw new RuntimeException('Unable to create backup ' . $backup);
    }

    if (file_put_contents($path, $new) === false) {
        throw new RuntimeException('Unable to write ' . $path);
    }

    $changes[] = substr($path, strlen(dirname(__DIR__)) + 1);
}

patch0332(
    $root . '/public/index.php',
    static function (string $content): string {
        if (str_contains($content, "'/portal/ai/drafts/{id}/reject'")) {
            return $content;
        }

        // Single-quoted PHP strings prevent interpolation of $r.
        $needle = '$r->post(\'/portal/ai/drafts/{id}/approve\',[\\App\\Controllers\\AiAssistantController::class,\'approveDraft\']);';

        if (!str_contains($content, $needle)) {
            throw new RuntimeException(
                'AI approval route marker not found. Apply 0.33.1 first.'
            );
        }

        $replacement =
            $needle .
            '$r->post(\'/portal/ai/drafts/{id}/reject\',[\\App\\Controllers\\AiAssistantController::class,\'rejectDraft\']);' .
            '$r->post(\'/portal/ai/drafts/{id}/use\',[\\App\\Controllers\\AiAssistantController::class,\'useDraft\']);';

        return str_replace($needle, $replacement, $content);
    },
    $changes
);

if (is_file($root . '/.env.example')) {
    patch0332(
        $root . '/.env.example',
        static function (string $content): string {
            $note = '# AI governance costs are configured in the portal; provider secrets remain here.';

            if (str_contains($content, $note)) {
                return $content;
            }

            return rtrim($content) . "\n" . $note . "\n";
        },
        $changes
    );
}

echo $changes
    ? "Patched:\n - " . implode("\n - ", $changes) . "\n"
    : "Release source patches already applied.\n";
