<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"CLI only.\n"); exit(1); }
$root=dirname(__DIR__); require_once $root.'/app/core/Env.php'; Env::load($root.'/.env'); require_once $root.'/app/core/Database.php';
$checks=[];
$check=function(string $name, callable $fn) use (&$checks):void { try{$detail=$fn();$checks[]=[$name,true,is_string($detail)?$detail:'PASS'];}catch(Throwable $e){$checks[]=[$name,false,$e->getMessage()];}};
$check('PHP version',fn()=>version_compare(PHP_VERSION,'8.2.0','>=')?'PHP '.PHP_VERSION:throw new RuntimeException('PHP 8.2+ required.'));
$check('Required extensions',function(){foreach(['pdo_mysql','curl','json','mbstring'] as $ext)if(!extension_loaded($ext))throw new RuntimeException("Missing {$ext}");return 'PASS';});
$check('Composer autoload',function()use($root){if(!is_file($root.'/vendor/autoload.php'))throw new RuntimeException('vendor/autoload.php missing');return 'PASS';});
$check('Database connection',function(){Database::connection()->query('SELECT 1');return 'PASS';});
$check('Migration 0.32.5',function(){ $s=Database::connection()->prepare("SELECT COUNT(*) FROM schema_migrations WHERE migration='migrate_phase32_5.sql'");$s->execute();if(!(int)$s->fetchColumn())throw new RuntimeException('Migration not recorded');return 'PASS';});
$check('GPS tracking schema',function(){ $pdo=Database::connection();foreach(['gps_location_history','gps_tracking_policies'] as $t){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$t]);if(!(int)$s->fetchColumn())throw new RuntimeException("Missing {$t}");}return 'PASS';});
$check('Route planning schema',function(){ $pdo=Database::connection();foreach(['route_plans','route_plan_stops','route_plan_history'] as $t){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$t]);if(!(int)$s->fetchColumn())throw new RuntimeException("Missing {$t}");}return 'PASS';});
$check('ETA progress schema',function(){ $pdo=Database::connection();foreach(['eta_notification_settings','route_progress_events','eta_worker_runs'] as $t){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$t]);if(!(int)$s->fetchColumn())throw new RuntimeException("Missing {$t}");}return 'PASS';});
$check('Writable storage',function()use($root){if(!is_writable($root.'/storage'))throw new RuntimeException('storage is not writable');return 'PASS';});
$check('Location tracking service load',function()use($root){require_once $root.'/app/services/LocationTrackingService.php';if(!class_exists('App\\Services\\LocationTrackingService'))throw new RuntimeException('Class not loadable');return 'PASS';});
$check('Route progress service load',function()use($root){require_once $root.'/app/services/RouteProgressService.php';if(!class_exists('App\\Services\\RouteProgressService'))throw new RuntimeException('Class not loadable');return 'PASS';});
$check('Route planning service load',function()use($root){require_once $root.'/app/services/GeocodingService.php';if(!class_exists('App\\Services\\GeocodingService'))throw new RuntimeException('Class not loadable');return 'PASS';});
echo "==========================================\nCastorWorks Upgrade Validation\nVersion: 0.32.5\n==========================================\n\n";$failed=0;foreach($checks as [$name,$ok,$detail]){printf("%-30s %s",$name,$ok?'PASS':'FAIL');if(!$ok||$detail!=='PASS')echo " - {$detail}";echo PHP_EOL;if(!$ok)$failed++;}echo "\nOverall Status: ".($failed?'NOT READY':'READY FOR PRODUCTION').PHP_EOL;exit($failed?2:0);
