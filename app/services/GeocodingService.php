<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use PDO;
use RuntimeException;

final class GeocodingService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function settings(): array
    {
        $row = $this->pdo->query("SELECT * FROM map_provider_settings WHERE id=1")->fetch();
        return is_array($row) ? $row : [
            'provider' => 'nominatim',
            'fallback_provider' => 'none',
            'cache_days' => 180,
            'requests_per_second' => 1,
            'user_agent' => 'ServiceOS/0.32.1',
            'contact_email' => '',
            'google_api_key_configured' => Env::string('GOOGLE_MAPS_API_KEY') !== '' ? 1 : 0,
            'active' => 1,
        ];
    }

    public function saveSettings(array $input): void
    {
        $provider = in_array($input['provider'] ?? '', ['nominatim', 'google', 'disabled'], true)
            ? (string)$input['provider'] : 'nominatim';
        $fallback = in_array($input['fallback_provider'] ?? '', ['none', 'nominatim', 'google'], true)
            ? (string)$input['fallback_provider'] : 'none';
        if ($fallback === $provider) $fallback = 'none';

        $stmt = $this->pdo->prepare(
            "INSERT INTO map_provider_settings
             (id,provider,fallback_provider,cache_days,requests_per_second,user_agent,contact_email,active,updated_at)
             VALUES(1,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE provider=VALUES(provider),fallback_provider=VALUES(fallback_provider),
             cache_days=VALUES(cache_days),requests_per_second=VALUES(requests_per_second),
             user_agent=VALUES(user_agent),contact_email=VALUES(contact_email),active=VALUES(active),updated_at=NOW()"
        );
        $stmt->execute([
            $provider,
            $fallback,
            max(1, min(3650, (int)($input['cache_days'] ?? 180))),
            max(1, min(10, (int)($input['requests_per_second'] ?? 1))),
            trim((string)($input['user_agent'] ?? 'ServiceOS/0.32.1')) ?: 'ServiceOS/0.32.1',
            trim((string)($input['contact_email'] ?? '')),
            isset($input['active']) ? 1 : 0,
        ]);
    }

    public function geocodeProperty(int $propertyId, bool $force = false): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id=?');
        $stmt->execute([$propertyId]);
        $property = $stmt->fetch();
        if (!$property) throw new RuntimeException('Property not found.');

        $address = $this->formatAddress($property);
        if ($address === '') throw new RuntimeException('Property address is incomplete.');
        $hash = hash('sha256', mb_strtolower($address));
        $settings = $this->settings();

        if (!$force) {
            $cached = $this->cached($hash, (int)($settings['cache_days'] ?? 180));
            if ($cached) return $this->applyResult($propertyId, $cached, 'cache');
        }

        if (!(int)($settings['active'] ?? 1) || ($settings['provider'] ?? 'disabled') === 'disabled') {
            throw new RuntimeException('Geocoding is disabled.');
        }

        $providers = [(string)$settings['provider']];
        if (($settings['fallback_provider'] ?? 'none') !== 'none') $providers[] = (string)$settings['fallback_provider'];
        $errors = [];

        foreach (array_unique($providers) as $provider) {
            try {
                $result = $this->request($provider, $address, $settings);
                $this->storeCache($hash, $address, $provider, $result);
                return $this->applyResult($propertyId, $result, $provider);
            } catch (\Throwable $e) {
                $errors[] = $provider . ': ' . $e->getMessage();
            }
        }

        $message = implode('; ', $errors) ?: 'No geocoding provider succeeded.';
        $this->pdo->prepare("UPDATE properties SET geocode_status='failed',geocode_error=?,geocoded_at=NOW() WHERE id=?")
            ->execute([$message, $propertyId]);
        throw new RuntimeException($message);
    }

    public function setManualCoordinates(int $propertyId, float $latitude, float $longitude, bool $verified = true): void
    {
        $this->assertCoordinates($latitude, $longitude);
        $stmt = $this->pdo->prepare(
            "UPDATE properties SET latitude=?,longitude=?,geocode_source='manual',geocode_status='verified',
             geocode_error=NULL,geocoded_at=NOW(),geocode_verified_at=? WHERE id=?"
        );
        $stmt->execute([$latitude, $longitude, $verified ? date('Y-m-d H:i:s') : null, $propertyId]);
    }

    public function markPending(int $propertyId): void
    {
        $this->pdo->prepare(
            "UPDATE properties SET geocode_status='pending',geocode_error=NULL,geocode_verified_at=NULL WHERE id=?"
        )->execute([$propertyId]);
    }

    public function test(string $address): array
    {
        $settings = $this->settings();
        if (($settings['provider'] ?? 'disabled') === 'disabled') throw new RuntimeException('Geocoding is disabled.');
        return $this->request((string)$settings['provider'], trim($address), $settings);
    }

    private function request(string $provider, string $address, array $settings): array
    {
        return match ($provider) {
            'nominatim' => $this->requestNominatim($address, $settings),
            'google' => $this->requestGoogle($address),
            default => throw new RuntimeException('Unsupported geocoding provider.'),
        };
    }

    private function requestNominatim(string $address, array $settings): array
    {
        $query = http_build_query(['q' => $address, 'format' => 'jsonv2', 'limit' => 1, 'addressdetails' => 1]);
        $url = 'https://nominatim.openstreetmap.org/search?' . $query;
        $headers = ['Accept: application/json', 'User-Agent: ' . ($settings['user_agent'] ?: 'ServiceOS/0.32.1')];
        if (!empty($settings['contact_email'])) $headers[] = 'From: ' . $settings['contact_email'];
        $data = $this->httpJson($url, $headers);
        if (!isset($data[0]['lat'], $data[0]['lon'])) throw new RuntimeException('Address was not found by Nominatim.');
        return [
            'latitude' => (float)$data[0]['lat'],
            'longitude' => (float)$data[0]['lon'],
            'formatted_address' => (string)($data[0]['display_name'] ?? $address),
            'accuracy' => (string)($data[0]['type'] ?? 'unknown'),
            'raw' => $data[0],
        ];
    }

    private function requestGoogle(string $address): array
    {
        $key = Env::string('GOOGLE_MAPS_API_KEY');
        if ($key === '') throw new RuntimeException('GOOGLE_MAPS_API_KEY is not configured.');
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(['address' => $address, 'key' => $key]);
        $data = $this->httpJson($url, ['Accept: application/json']);
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]['geometry']['location'])) {
            throw new RuntimeException('Google geocoding failed: ' . (string)($data['status'] ?? 'unknown'));
        }
        $row = $data['results'][0];
        return [
            'latitude' => (float)$row['geometry']['location']['lat'],
            'longitude' => (float)$row['geometry']['location']['lng'],
            'formatted_address' => (string)($row['formatted_address'] ?? $address),
            'accuracy' => (string)($row['geometry']['location_type'] ?? 'unknown'),
            'raw' => $row,
        ];
    }

    private function httpJson(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $error !== '') throw new RuntimeException('HTTP request failed: ' . $error);
        if ($status < 200 || $status >= 300) throw new RuntimeException('Geocoding provider returned HTTP ' . $status . '.');
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) throw new RuntimeException('Geocoding provider returned invalid JSON.');
        return $decoded;
    }

    private function cached(string $hash, int $cacheDays): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT latitude,longitude,formatted_address,accuracy,raw_response FROM geocoding_cache
             WHERE address_hash=? AND expires_at>NOW() ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return [
            'latitude' => (float)$row['latitude'], 'longitude' => (float)$row['longitude'],
            'formatted_address' => (string)$row['formatted_address'], 'accuracy' => (string)$row['accuracy'],
            'raw' => json_decode((string)$row['raw_response'], true) ?: [],
        ];
    }

    private function storeCache(string $hash, string $address, string $provider, array $result): void
    {
        $settings = $this->settings();
        $days = max(1, (int)($settings['cache_days'] ?? 180));
        $stmt = $this->pdo->prepare(
            'INSERT INTO geocoding_cache(address_hash,address_text,provider,latitude,longitude,formatted_address,accuracy,raw_response,expires_at)
             VALUES(?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY))
             ON DUPLICATE KEY UPDATE provider=VALUES(provider),latitude=VALUES(latitude),longitude=VALUES(longitude),
             formatted_address=VALUES(formatted_address),accuracy=VALUES(accuracy),raw_response=VALUES(raw_response),expires_at=VALUES(expires_at),updated_at=NOW()'
        );
        $stmt->execute([$hash, $address, $provider, $result['latitude'], $result['longitude'], $result['formatted_address'],
            $result['accuracy'], json_encode($result['raw'], JSON_UNESCAPED_SLASHES), $days]);
    }

    private function applyResult(int $propertyId, array $result, string $source): array
    {
        $this->assertCoordinates((float)$result['latitude'], (float)$result['longitude']);
        $stmt = $this->pdo->prepare(
            "UPDATE properties SET latitude=?,longitude=?,geocode_source=?,geocode_status='geocoded',geocode_accuracy=?,
             geocode_formatted_address=?,geocode_error=NULL,geocoded_at=NOW() WHERE id=?"
        );
        $stmt->execute([(float)$result['latitude'], (float)$result['longitude'], $source,
            (string)($result['accuracy'] ?? ''), (string)($result['formatted_address'] ?? ''), $propertyId]);
        return $result;
    }

    private function formatAddress(array $property): string
    {
        return trim(implode(', ', array_filter([
            trim((string)($property['address1'] ?? '')),
            trim((string)($property['address2'] ?? '')),
            trim((string)($property['city'] ?? '')),
            trim((string)($property['state'] ?? '')) . ' ' . trim((string)($property['postal_code'] ?? '')),
            'USA',
        ])));
    }

    private function assertCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('Coordinates are outside valid latitude/longitude ranges.');
        }
    }
}
