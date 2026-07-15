<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AiAuditReportService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->pdo->query(
            "SELECT a.id,a.action,a.entity_type,a.entity_id,a.metadata,a.created_at,u.name user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id=a.user_id
             WHERE a.action LIKE 'ai.%'
             ORDER BY a.created_at DESC,a.id DESC
             LIMIT {$limit}"
        )->fetchAll();
    }
}
