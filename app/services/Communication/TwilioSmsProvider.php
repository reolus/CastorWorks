<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class TwilioSmsProvider implements ProviderInterface
{
    public function key(): string { return 'twilio_sms'; }
    public function channel(): string { return 'sms'; }
    public function label(): string { return 'Twilio SMS'; }
    public function configured(): bool
    {
        return Env::string('TWILIO_ACCOUNT_SID') !== ''
            && Env::string('TWILIO_AUTH_TOKEN') !== ''
            && Env::string('TWILIO_FROM_NUMBER') !== '';
    }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('Twilio SMS is not configured.');
        }

        $sid = Env::string('TWILIO_ACCOUNT_SID');
        $token = Env::string('TWILIO_AUTH_TOKEN');
        $from = Env::string('TWILIO_FROM_NUMBER');
        $to = (string) ($message['to'] ?? '');
        $body = (string) ($message['text'] ?? '');

        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $data = json_decode((string) $raw, true) ?: [];

        if ($code >= 200 && $code < 300) {
            return new ProviderResult(true, (string) ($data['sid'] ?? ''), 'Twilio accepted the SMS.', ['http_status' => $code]);
        }

        throw new RuntimeException((string) ($data['message'] ?? $error ?: 'Twilio SMS delivery failed.'));
    }
}
