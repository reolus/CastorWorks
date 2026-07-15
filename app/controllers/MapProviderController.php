<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Services\AuditService;
use App\Services\GeocodingService;

final class MapProviderController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'administrator');
        $pdo = Database::connection();
        $service = new GeocodingService($pdo);
        $stats = $pdo->query(
            "SELECT COUNT(*) total,
             SUM(geocode_status='pending') pending,
             SUM(geocode_status='geocoded') geocoded,
             SUM(geocode_status='verified') verified,
             SUM(geocode_status='failed') failed
             FROM properties"
        )->fetch();
        $recent = $pdo->query(
            "SELECT p.id,p.label,p.address1,p.city,p.state,p.postal_code,p.geocode_status,p.geocode_source,p.geocode_error,p.geocoded_at,c.display_name
             FROM properties p JOIN customers c ON c.id=p.customer_id
             ORDER BY COALESCE(p.geocoded_at,'1970-01-01') DESC,p.id DESC LIMIT 25"
        )->fetchAll();
        View::render('portal/map-providers/index', [
            'title' => 'Map Providers', 'settings' => $service->settings(), 'stats' => $stats ?: [],
            'recent' => $recent, 'googleConfigured' => Env::string('GOOGLE_MAPS_API_KEY') !== '',
        ], 'portal');
    }

    public function update(): void
    {
        Auth::requireRole('owner', 'administrator'); verify_csrf();
        (new GeocodingService())->saveSettings($_POST);
        AuditService::log('maps.settings_updated', 'system', null);
        flash('success', 'Map provider settings saved.'); redirect('/portal/map-providers');
    }

    public function test(): void
    {
        Auth::requireRole('owner', 'administrator'); verify_csrf();
        try {
            $result = (new GeocodingService())->test(trim((string)($_POST['address'] ?? '')));
            flash('success', sprintf('Geocoding succeeded: %.6f, %.6f', $result['latitude'], $result['longitude']));
        } catch (\Throwable $e) {
            flash('danger', 'Geocoding test failed: ' . $e->getMessage());
        }
        redirect('/portal/map-providers');
    }
}
