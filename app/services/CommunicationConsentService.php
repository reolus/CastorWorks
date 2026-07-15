<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class CommunicationConsentService
{
    public function assertAllowed(string $channel, string $destination, string $messageClass): void
    {
        if ($messageClass !== 'marketing') {
            return;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM marketing_suppressions WHERE LOWER(destination)=LOWER(?) AND channel IN (?, 'all')"
            );
            $stmt->execute([$destination, $channel]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new RuntimeException('The recipient is suppressed from marketing communications.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            // Older installations without the marketing table continue to work.
        }
    }

    public function suppress(string $channel, string $destination, string $reason): void
    {
        Database::connection()->prepare(
            "INSERT INTO marketing_suppressions(destination,channel,reason,created_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE reason=VALUES(reason)"
        )->execute([$destination, $channel]);
    }
}
