<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AzureCommunicationClient
{
    private string $endpoint;
    private string $accessKey;

    public function __construct()
    {
        $connection = Env::string('AZURE_COMMUNICATION_CONNECTION_STRING');
        $parts = [];
        foreach (explode(';', $connection) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $parts[strtolower(trim($key))] = trim($value);
        }

        $this->endpoint = rtrim((string) ($parts['endpoint'] ?? Env::string('AZURE_COMMUNICATION_ENDPOINT')), '/');
        $this->accessKey = (string) ($parts['accesskey'] ?? Env::string('AZURE_COMMUNICATION_ACCESS_KEY'));
    }

    public function configured(): bool
    {
        return $this->endpoint !== '' && $this->accessKey !== '';
    }

    public function request(string $method, string $pathAndQuery, array $body, array $extraHeaders = []): array
    {
        if (!$this->configured()) {
            throw new RuntimeException('Azure Communication Services is not configured.');
        }

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode Azure Communication Services request.');
        }

        $url = $this->endpoint . $pathAndQuery;
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $contentHash = base64_encode(hash('sha256', $json, true));
        $stringToSign = strtoupper($method) . "\n" . $pathAndQuery . "\n" . $date . ';' . $host . ';' . $contentHash;
        $decodedKey = base64_decode($this->accessKey, true);
        if ($decodedKey === false) {
            throw new RuntimeException('Azure Communication Services access key is not valid base64.');
        }
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));
        $authorization = 'HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=' . $signature;

        $headers = array_merge([
            'Content-Type: application/json',
            'x-ms-date: ' . $date,
            'x-ms-content-sha256: ' . $contentHash,
            'Authorization: ' . $authorization,
        ], $extraHeaders);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Azure Communication Services request failed: ' . ($error ?: 'unknown cURL error'));
        }

        $headerText = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);
        $data = $responseBody !== '' ? (json_decode($responseBody, true) ?: ['raw' => $responseBody]) : [];

        if ($code < 200 || $code >= 300) {
            $detail = is_array($data['error'] ?? null)
                ? (string) ($data['error']['message'] ?? $responseBody)
                : $responseBody;
            throw new RuntimeException('Azure Communication Services request failed (HTTP ' . $code . '): ' . substr($detail, 0, 500));
        }

        return [
            'http_status' => $code,
            'headers' => $this->parseHeaders($headerText),
            'body' => $data,
        ];
    }

    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', trim($raw)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
        return $headers;
    }
}
