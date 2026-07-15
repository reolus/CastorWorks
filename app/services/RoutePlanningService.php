<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class RoutePlanningService
{
    /**
     * @param array<int,array<string,mixed>> $jobs
     * @param array<int,int> $lockedJobIds
     * @return array<string,mixed>
     */
    public static function buildProposal(array $jobs, array $lockedJobIds = []): array
    {
        $jobs = array_values(array_filter($jobs, static fn(array $job): bool => self::hasCoordinates($job)));
        if ($jobs === []) {
            throw new RuntimeException('No geocoded jobs are available for route optimization.');
        }

        usort($jobs, static fn(array $a, array $b): int =>
            ((int)($a['route_order'] ?? 9999) <=> (int)($b['route_order'] ?? 9999))
            ?: strcmp((string)($a['scheduled_start'] ?? ''), (string)($b['scheduled_start'] ?? ''))
        );

        $current = $jobs;
        $lockedLookup = array_fill_keys(array_map('intval', $lockedJobIds), true);
        $optimized = self::optimizeWithLocks($current, $lockedLookup);

        $currentDistance = self::totalDistance($current);
        $optimizedDistance = self::totalDistance($optimized);
        $speed = max(10.0, (float)Env::get('ROUTE_AVERAGE_SPEED_MPH', 32));
        $currentMinutes = (int)round(($currentDistance / $speed) * 60);
        $optimizedMinutes = (int)round(($optimizedDistance / $speed) * 60);

        return [
            'current' => $current,
            'optimized' => $optimized,
            'current_distance_miles' => $currentDistance,
            'optimized_distance_miles' => $optimizedDistance,
            'distance_savings_miles' => round(max(0, $currentDistance - $optimizedDistance), 2),
            'current_duration_minutes' => $currentMinutes,
            'optimized_duration_minutes' => $optimizedMinutes,
            'duration_savings_minutes' => max(0, $currentMinutes - $optimizedMinutes),
            'provider' => (string)Env::get('ROUTE_MATRIX_PROVIDER', 'local'),
        ];
    }

    /** @param array<int,array<string,mixed>> $jobs */
    public static function totalDistance(array $jobs): float
    {
        $total = 0.0;
        for ($i = 1, $count = count($jobs); $i < $count; $i++) {
            $total += self::distance(
                (float)$jobs[$i - 1]['latitude'],
                (float)$jobs[$i - 1]['longitude'],
                (float)$jobs[$i]['latitude'],
                (float)$jobs[$i]['longitude']
            );
        }
        return round($total, 2);
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @param array<int,bool> $lockedLookup
     * @return array<int,array<string,mixed>>
     */
    private static function optimizeWithLocks(array $jobs, array $lockedLookup): array
    {
        if (count($jobs) < 3 || $lockedLookup === []) {
            return self::nearestNeighbor($jobs);
        }

        $result = array_fill(0, count($jobs), null);
        $unlocked = [];
        foreach ($jobs as $index => $job) {
            if (isset($lockedLookup[(int)$job['id']])) {
                $result[$index] = $job;
            } else {
                $unlocked[] = $job;
            }
        }

        $cursor = 0;
        foreach ($result as $index => $job) {
            if ($job !== null) {
                continue;
            }
            $anchor = null;
            for ($p = $index - 1; $p >= 0; $p--) {
                if ($result[$p] !== null) {
                    $anchor = $result[$p];
                    break;
                }
            }
            $bestIndex = 0;
            $bestDistance = PHP_FLOAT_MAX;
            foreach ($unlocked as $candidateIndex => $candidate) {
                $distance = $anchor === null ? $candidateIndex : self::distance(
                    (float)$anchor['latitude'],
                    (float)$anchor['longitude'],
                    (float)$candidate['latitude'],
                    (float)$candidate['longitude']
                );
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestIndex = $candidateIndex;
                }
            }
            $result[$index] = $unlocked[$bestIndex];
            array_splice($unlocked, $bestIndex, 1);
            $cursor++;
        }

        return array_values($result);
    }

    /** @param array<int,array<string,mixed>> $jobs @return array<int,array<string,mixed>> */
    private static function nearestNeighbor(array $jobs): array
    {
        if (count($jobs) < 2) {
            return $jobs;
        }
        $remaining = array_values($jobs);
        $ordered = [array_shift($remaining)];
        while ($remaining !== []) {
            $last = $ordered[array_key_last($ordered)];
            $bestIndex = 0;
            $bestDistance = PHP_FLOAT_MAX;
            foreach ($remaining as $index => $candidate) {
                $distance = self::distance(
                    (float)$last['latitude'],
                    (float)$last['longitude'],
                    (float)$candidate['latitude'],
                    (float)$candidate['longitude']
                );
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestIndex = $index;
                }
            }
            $ordered[] = $remaining[$bestIndex];
            array_splice($remaining, $bestIndex, 1);
        }
        return $ordered;
    }

    /** @param array<string,mixed> $job */
    private static function hasCoordinates(array $job): bool
    {
        return is_numeric($job['latitude'] ?? null)
            && is_numeric($job['longitude'] ?? null)
            && abs((float)$job['latitude']) <= 90
            && abs((float)$job['longitude']) <= 180;
    }

    private static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radius = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $radius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
