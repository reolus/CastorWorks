<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class CommunicationReceiptService
{
    public function record(string $provider, ?string $messageId, string $status, array $payload, ?string $destination = null): void
    {
        $status = $this->normalizeStatus($status);
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO communication_receipts(provider_key,provider_message_id,normalized_status,destination,payload_json,received_at) VALUES(?,?,?,?,?,NOW())'
        )->execute([$provider, $messageId, $status, $destination, json_encode($payload, JSON_UNESCAPED_SLASHES)]);

        if ($messageId !== null && $messageId !== '') {
            try {
                $pdo->prepare(
                    'UPDATE communication_delivery_attempts SET delivery_status=?,status_updated_at=NOW() WHERE provider_key=? AND provider_message_id=?'
                )->execute([$status, $provider, $messageId]);
            } catch (Throwable) {
            }
        }
    }

    public function inbound(string $provider, string $from, string $to, string $body, array $payload): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO communication_inbound_messages(provider_key,sender,recipient,body,payload_json,received_at) VALUES(?,?,?,?,?,NOW())'
        )->execute([$provider, $from, $to, $body, json_encode($payload, JSON_UNESCAPED_SLASHES)]);

        $keyword = strtoupper(trim(preg_split('/\s+/', $body)[0] ?? ''));
        if (in_array($keyword, ['STOP','STOPALL','UNSUBSCRIBE','CANCEL','END','QUIT'], true)) {
            (new CommunicationConsentService())->suppress('sms', $from, 'Inbound opt-out keyword: ' . $keyword);
        }
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtolower(trim($status));
        return match (true) {
            in_array($value, ['delivered','delivery_success','success','sent'], true) => 'delivered',
            in_array($value, ['queued','accepted','submitted','sending'], true) => 'accepted',
            in_array($value, ['rejected','blocked'], true) => 'rejected',
            in_array($value, ['undelivered','failed','delivery_failure','error'], true) => 'failed',
            in_array($value, ['opted_out','optout','unsubscribe'], true) => 'opted_out',
            default => 'unknown',
        };
    }
}
