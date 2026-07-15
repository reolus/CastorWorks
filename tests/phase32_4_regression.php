<?php

declare(strict_types=1);

$root=dirname(__DIR__);$errors=[];
$must=[
 'app/services/LocationTrackingService.php'=>['gps_location_history','actual_arrival','clocked_in_only'],
 'app/controllers/GpsPolicyController.php'=>['gps_tracking_policies','GPS tracking policy updated'],
 'app/controllers/MobileController.php'=>['function location','LocationTrackingService'],
 'public/index.php'=>['/portal/mobile/location','/portal/gps-policies'],
 'database/migrate_phase32_4.sql'=>['gps_tracking_policies','actual_arrival','last_location_update'],
 'app/services/IntegrationHealthService.php'=>['Field GPS Tracking','gpsTracking'],
];
foreach($must as $file=>$needles){$path=$root.'/'.$file;if(!is_file($path)){$errors[]="Missing {$file}";continue;}$text=file_get_contents($path);foreach($needles as $needle)if(!str_contains((string)$text,$needle))$errors[]="{$file} missing {$needle}";}
if($errors){fwrite(STDERR,"CastorWorks 0.32.4 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "CastorWorks 0.32.4 regression passed.\n";
