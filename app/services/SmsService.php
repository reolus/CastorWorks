<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class SmsService
{
    public static function send(?int $customerId, ?int $jobId, string $phone, string $body): bool
    {
        $db = Database::connection();
        $q = $db->prepare("INSERT INTO sms_messages(customer_id,job_id,phone,body,status) VALUES(?,?,?,?,'queued')");
        $q->execute([$customerId, $jobId, $phone, $body]);
        $id = (int) $db->lastInsertId();

        try {
            $result = (new CommunicationManager())->sendSms($phone, $body, [
                'customer_id' => $customerId,
                'job_id' => $jobId,
                'sms_message_id' => $id,
            ]);
            $db->prepare("UPDATE sms_messages SET status='sent',provider_key=?,provider_message_id=?,sent_at=NOW() WHERE id=?")
                ->execute([$result['provider'], $result['message_id'], $id]);
            return true;
        } catch (Throwable $e) {
            $db->prepare("UPDATE sms_messages SET status='failed',error_message=? WHERE id=?")
                ->execute([$e->getMessage(), $id]);
            return false;
        }
    }
}
