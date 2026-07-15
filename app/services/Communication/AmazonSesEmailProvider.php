<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AmazonSesEmailProvider implements ProviderInterface
{
    public function key(): string { return 'amazon_ses_email'; }
    public function channel(): string { return 'email'; }
    public function label(): string { return 'Amazon SES Email'; }
    public function configured(): bool
    {
        return AwsClientFactory::configured() && Env::string('AWS_SES_FROM_EMAIL') !== '';
    }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('Amazon SES email is not configured.');
        }

        $client = new \Aws\SesV2\SesV2Client(AwsClientFactory::config());
        $raw = $this->mimeMessage($message);
        $result = $client->sendEmail([
            'FromEmailAddress' => Env::string('AWS_SES_FROM_EMAIL'),
            'Destination' => ['ToAddresses' => [(string) ($message['to'] ?? '')]],
            'Content' => ['Raw' => ['Data' => $raw]],
            'ReplyToAddresses' => array_values(array_filter([Env::string('AWS_SES_REPLY_TO')])),
        ])->toArray();

        return new ProviderResult(true, (string) ($result['MessageId'] ?? ''), 'Amazon SES accepted the email.');
    }

    private function mimeMessage(array $message): string
    {
        $boundary = 'serviceos_' . bin2hex(random_bytes(12));
        $to = (string) ($message['to'] ?? '');
        $subject = (string) ($message['subject'] ?? '');
        $html = (string) ($message['html'] ?? '');
        $from = Env::string('AWS_SES_FROM_EMAIL');
        $plain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '_alt"' . "\r\n\r\n";
        $body .= '--' . $boundary . "_alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($plain)) . "\r\n";
        $body .= '--' . $boundary . "_alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
        $body .= '--' . $boundary . "_alt--\r\n";

        foreach ((array) ($message['attachments'] ?? []) as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $name = (string) ($attachment['name'] ?? basename($path));
            $type = (string) ($attachment['type'] ?? 'application/octet-stream');
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                continue;
            }
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Type: ' . $type . '; name="' . addcslashes($name, '"\\') . '"' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . addcslashes($name, '"\\') . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($bytes)) . "\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";
        return $body;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
