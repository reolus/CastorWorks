<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);require $root.'/app/core/Env.php';Env::load($root.'/.env');require $root.'/app/core/Database.php';
$pdo=Database::connection();$days=(int)($pdo->query('SELECT retention_days FROM gps_tracking_policies WHERE id=1')->fetchColumn()?:90);$days=max(1,min(3650,$days));
$stmt=$pdo->prepare('DELETE FROM gps_location_history WHERE captured_at < DATE_SUB(NOW(), INTERVAL '. $days .' DAY)');$stmt->execute();
echo 'Deleted '.$stmt->rowCount().' GPS location record(s) older than '.$days." day(s).\n";
