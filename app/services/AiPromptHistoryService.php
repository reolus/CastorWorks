<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

final class AiPromptHistoryService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saveVersion(int $promptId, string $template, int $version): void
    {
        $this->pdo->prepare('INSERT INTO ai_prompt_versions(prompt_id,version,prompt_template,created_by,created_at) VALUES(?,?,?,?,NOW())')
            ->execute([$promptId, $version, $template, Auth::id()]);
    }

    public function rollback(int $promptId, int $version): void
    {
        $stmt = $this->pdo->prepare('SELECT prompt_template FROM ai_prompt_versions WHERE prompt_id=? AND version=?');
        $stmt->execute([$promptId, $version]);
        $template = $stmt->fetchColumn();
        if ($template === false) {
            throw new RuntimeException('Prompt version not found.');
        }
        $this->pdo->prepare('UPDATE ai_saved_prompts SET prompt_template=?,version=version+1,updated_at=NOW() WHERE id=?')
            ->execute([(string) $template, $promptId]);
    }
}
