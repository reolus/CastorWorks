<?php

declare(strict_types=1);

$root=dirname(__DIR__);$errors=[];
$must=[
 'app/services/RouteProgressService.php'=>['class RouteProgressService','estimated_arrival','sendSms'],
 'app/controllers/EtaPolicyController.php'=>['class EtaPolicyController','recalculate'],
 'database/migrate_phase32_5.sql'=>['eta_notification_settings','eta_worker_runs','eta_notified_at'],
 'scripts/update-route-etas.php'=>['RouteProgressService'],
 'app/views/portal/routes/index.php'=>['Recalculate ETAs','estimated_arrival'],
];
foreach($must as $file=>$needles){$path=$root.'/'.$file;if(!is_file($path)){$errors[]="Missing {$file}";continue;}$text=file_get_contents($path);foreach($needles as $needle)if(!str_contains($text,$needle))$errors[]="{$file} missing {$needle}";}
foreach(['app/services/RouteProgressService.php','app/controllers/EtaPolicyController.php','scripts/update-route-etas.php'] as $file){exec('php -l '.escapeshellarg($root.'/'.$file).' 2>&1',$out,$code);if($code!==0)$errors[]="Syntax failure in {$file}: ".implode(' ',$out);$out=[];}
if($errors){echo "CastorWorks 0.32.5 regression failed:\n- ".implode("\n- ",$errors)."\n";exit(1);}echo "CastorWorks 0.32.5 regression passed.\n";
