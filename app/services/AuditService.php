<?php
namespace App\Services;
use App\Core\Database;
use Throwable;
final class AuditService
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
    {
        try {
            $stmt = Database::connection()->prepare('INSERT INTO audit_logs(user_id, action, entity_type, entity_id, metadata, ip_address) VALUES(?,?,?,?,?,?)');
            $stmt->execute([$_SESSION['user']['id'] ?? null, $action, $entityType, $entityId, $metadata ? json_encode($metadata) : null, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable) {}
    }
}
