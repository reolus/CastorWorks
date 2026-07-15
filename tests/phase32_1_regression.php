<?php
$root=dirname(__DIR__);$errors=[];
$required=['app/services/GeocodingService.php','app/controllers/MapProviderController.php','app/views/portal/map-providers/index.php','scripts/geocode-properties.php','scripts/validate-upgrade.php','database/migrate_phase32_1.sql'];
foreach($required as $file)if(!is_file($root.'/'.$file))$errors[]="Missing {$file}";
$index=file_get_contents($root.'/public/index.php');foreach(['/portal/map-providers','/portal/properties/{id}/geocode'] as $route)if(!str_contains($index,$route))$errors[]="Missing route {$route}";
$migration=file_get_contents($root.'/database/migrate_phase32_1.sql');foreach(['geocoding_cache','map_provider_settings','gps_location_history','geocode_status'] as $term)if(!str_contains($migration,$term))$errors[]="Migration missing {$term}";
$service=file_get_contents($root.'/app/services/GeocodingService.php');foreach(['nominatim.openstreetmap.org','GOOGLE_MAPS_API_KEY','assertCoordinates','geocoding_cache'] as $term)if(!str_contains($service,$term))$errors[]="Geocoding service missing {$term}";
if($errors){fwrite(STDERR,"ServiceOS 0.32.1 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "ServiceOS 0.32.1 regression passed.\n";
