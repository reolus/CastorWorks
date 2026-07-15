<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

final class AiDraftApplicationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{target_type:string,target_id:int,before_content:string,after_content:string} */
    public function apply(array $draft, string $targetType, int $targetId, bool $humanReviewed): array
    {
        if (!$humanReviewed) {
            throw new RuntimeException('A human reviewer must certify the draft before application.');
        }

        $content = trim((string) ($draft['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeException('The AI draft is empty.');
        }

        return match ($targetType) {
            'estimate' => $this->applyToEstimate($draft, $targetId, $content),
            'conversation' => $this->applyToConversation($draft, $targetId, $content),
            default => throw new RuntimeException('Unsupported AI draft target.'),
        };
    }

    /** @return array{target_type:string,target_id:int,before_content:string,after_content:string} */
    private function applyToEstimate(array $draft, int $estimateId, string $content): array
    {
        $stmt = $this->pdo->prepare('SELECT notes FROM estimates WHERE id=? FOR UPDATE');
        $stmt->execute([$estimateId]);
        $before = $stmt->fetchColumn();
        if ($before === false) {
            throw new RuntimeException('Estimate not found.');
        }

        $before = (string) $before;
        $after = $before === '' ? $content : rtrim($before) . "\n\n" . $content;
        $this->pdo->prepare('UPDATE estimates SET notes=? WHERE id=?')->execute([$after, $estimateId]);

        return ['target_type' => 'estimate', 'target_id' => $estimateId, 'before_content' => $before, 'after_content' => $after];
    }

    /** @return array{target_type:string,target_id:int,before_content:string,after_content:string} */
    private function applyToConversation(array $draft, int $threadId, string $content): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM conversation_threads WHERE id=? FOR UPDATE');
        $stmt->execute([$threadId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Conversation not found.');
        }

        $this->pdo->prepare("INSERT INTO conversation_messages(conversation_thread_id,sender_type,sender_user_id,channel,body,is_internal,created_at) VALUES(?,'staff',?,'draft',?,1,NOW())")
            ->execute([$threadId, Auth::id(), $content]);
        $this->pdo->prepare("UPDATE conversation_threads SET last_message_at=NOW(),status='waiting_staff' WHERE id=?")->execute([$threadId]);

        return ['target_type' => 'conversation', 'target_id' => $threadId, 'before_content' => '', 'after_content' => $content];
    }
}
