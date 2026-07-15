<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Services\Communication\ProviderInterface;
use App\Services\Communication\ProviderRegistry;
use RuntimeException;
use Throwable;

final class CommunicationManager
{
    public function sendSms(string $to, string $text, array $context = []): array
    {
        $normalized = PhoneNumberService::normalize($to, Env::string('DEFAULT_COUNTRY_CODE', '1'));
        $parts = PhoneNumberService::smsParts($text);
        return $this->send('sms', [
            'to' => $normalized,
            'text' => $text,
            'context' => $context,
            'sms_parts' => $parts,
        ]);
    }

    public function sendEmail(string $to, string $subject, string $html, array $attachments = [], array $context = []): array
    {
        if (isset($attachments['path'])) {
            $attachments = [$attachments];
        }
        return $this->send('email', [
            'to' => trim($to),
            'subject' => $subject,
            'html' => $html,
            'attachments' => $attachments,
            'context' => $context,
        ]);
    }

    public function publishTopic(string $subject, string $text, array $context = [], ?string $topicArn = null): array
    {
        return $this->send('topic', [
            'subject' => $subject,
            'text' => $text,
            'context' => $context,
            'topic_arn' => $topicArn,
        ]);
    }

    public function send(string $channel, array $message): array
    {
        $messageClass = strtolower((string) ($message['context']['message_class'] ?? 'transactional'));
        if (!in_array($messageClass, ['transactional', 'marketing'], true)) {
            $messageClass = 'transactional';
        }
        $recipient = (string) ($message['to'] ?? $message['topic_arn'] ?? '');
        (new CommunicationConsentService())->assertAllowed($channel, $recipient, $messageClass);

        $providers = $this->providersForChannel($channel, $messageClass);
        if ($providers === []) {
            throw new RuntimeException('No enabled and configured communication provider is available for ' . $channel . ' (' . $messageClass . ').');
        }

        $fallbackEnabled = Env::bool('COMMUNICATION_FALLBACK_ENABLED', true);
        $attempts = [];
        foreach ($providers as $row) {
            /** @var ProviderInterface $provider */
            $provider = $row['provider'];
            $this->enforceProviderLimit($provider->key(), (int) $row['daily_limit'], (int) $row['monthly_limit']);
            $started = microtime(true);
            try {
                $result = $provider->send($message);
                $duration = (int) round((microtime(true) - $started) * 1000);
                $this->logAttempt($channel, $provider->key(), $message, $messageClass, 'sent', $result->messageId, $result->detail, $duration, $result->metadata);
                $attempts[] = ['provider' => $provider->key(), 'status' => 'sent', 'message_id' => $result->messageId];
                return ['success' => true, 'provider' => $provider->key(), 'message_id' => $result->messageId, 'attempts' => $attempts];
            } catch (Throwable $e) {
                $duration = (int) round((microtime(true) - $started) * 1000);
                $this->logAttempt($channel, $provider->key(), $message, $messageClass, 'failed', null, $e->getMessage(), $duration, []);
                $attempts[] = ['provider' => $provider->key(), 'status' => 'failed', 'error' => $e->getMessage()];
                if (!$fallbackEnabled || !(bool) $row['allow_fallback']) {
                    break;
                }
            }
        }

        $last = end($attempts);
        throw new RuntimeException('All communication providers failed for ' . $channel . ': ' . (string) ($last['error'] ?? 'unknown failure'));
    }

    public function status(): array
    {
        $registry = new ProviderRegistry();
        $rows = $this->providerRows();
        $status = [];
        foreach ($rows as $row) {
            $provider = $registry->get((string) $row['provider_key']);
            $usage = $this->providerUsage((string) $row['provider_key']);
            $status[] = array_merge($row, $usage, [
                'configured' => $provider?->configured() ?? false,
                'label' => $provider?->label() ?? (string) $row['provider_key'],
            ]);
        }
        return $status;
    }

    private function providersForChannel(string $channel, string $messageClass): array
    {
        $registry = new ProviderRegistry();
        $rows = array_values(array_filter($this->providerRows(), static function (array $row) use ($channel, $messageClass): bool {
            if ($row['channel'] !== $channel || (int) $row['enabled'] !== 1) return false;
            return $messageClass === 'marketing' ? (int) $row['allow_marketing'] === 1 : (int) $row['allow_transactional'] === 1;
        }));
        usort($rows, static fn(array $a, array $b): int => ((int) $a['priority']) <=> ((int) $b['priority']));

        $providers = [];
        foreach ($rows as $row) {
            $provider = $registry->get((string) $row['provider_key']);
            if ($provider !== null && $provider->configured()) {
                $providers[] = [
                    'provider' => $provider,
                    'allow_fallback' => (int) $row['allow_fallback'] === 1,
                    'daily_limit' => (int) ($row['daily_limit'] ?? 0),
                    'monthly_limit' => (int) ($row['monthly_limit'] ?? 0),
                ];
            }
        }
        return $providers;
    }

    private function providerRows(): array
    {
        try {
            return Database::connection()->query(
                'SELECT provider_key,channel,enabled,priority,allow_fallback,allow_transactional,allow_marketing,daily_limit,monthly_limit,notes FROM communication_providers ORDER BY channel,priority,provider_key'
            )->fetchAll();
        } catch (Throwable) {
            return [
                ['provider_key' => 'microsoft_graph_email', 'channel' => 'email', 'enabled' => 1, 'priority' => 10, 'allow_fallback' => 1, 'allow_transactional' => 1, 'allow_marketing' => 0, 'daily_limit' => 0, 'monthly_limit' => 0, 'notes' => null],
                ['provider_key' => 'twilio_sms', 'channel' => 'sms', 'enabled' => 1, 'priority' => 30, 'allow_fallback' => 1, 'allow_transactional' => 1, 'allow_marketing' => 0, 'daily_limit' => 0, 'monthly_limit' => 0, 'notes' => null],
            ];
        }
    }

    private function providerUsage(string $providerKey): array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT SUM(created_at >= CURDATE()) daily_used, SUM(created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) monthly_used FROM communication_delivery_attempts WHERE provider_key=? AND status='sent'"
            );
            $stmt->execute([$providerKey]);
            $row = $stmt->fetch() ?: [];
            return ['daily_used' => (int) ($row['daily_used'] ?? 0), 'monthly_used' => (int) ($row['monthly_used'] ?? 0)];
        } catch (Throwable) {
            return ['daily_used' => 0, 'monthly_used' => 0];
        }
    }

    private function enforceProviderLimit(string $providerKey, int $dailyLimit, int $monthlyLimit): void
    {
        if ($dailyLimit <= 0 && $monthlyLimit <= 0) return;
        $usage = $this->providerUsage($providerKey);
        if ($dailyLimit > 0 && $usage['daily_used'] >= $dailyLimit) {
            throw new RuntimeException($providerKey . ' reached its daily usage limit.');
        }
        if ($monthlyLimit > 0 && $usage['monthly_used'] >= $monthlyLimit) {
            throw new RuntimeException($providerKey . ' reached its monthly usage limit.');
        }
    }

    private function logAttempt(string $channel, string $providerKey, array $message, string $messageClass, string $status, ?string $messageId, ?string $detail, int $durationMs, array $metadata): void
    {
        try {
            $parts = (array) ($message['sms_parts'] ?? []);
            Database::connection()->prepare(
                'INSERT INTO communication_delivery_attempts(channel,provider_key,recipient,subject,message_class,status,delivery_status,provider_message_id,error_message,duration_ms,sms_parts,sms_encoding,metadata_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $channel,
                $providerKey,
                (string) ($message['to'] ?? $message['topic_arn'] ?? ''),
                (string) ($message['subject'] ?? ''),
                $messageClass,
                $status,
                $status === 'sent' ? 'accepted' : 'failed',
                $messageId,
                $status === 'failed' ? $detail : null,
                $durationMs,
                (int) ($parts['parts'] ?? 0) ?: null,
                (string) ($parts['encoding'] ?? '') ?: null,
                json_encode(array_merge($metadata, ['context' => $message['context'] ?? [], 'characters' => $parts['characters'] ?? null]), JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }
    }
}
