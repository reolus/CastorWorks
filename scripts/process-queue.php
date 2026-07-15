#!/usr/bin/env php
<?php
declare(strict_types=1);
use App\Core\Database;use App\Core\Env;use App\Services\CalendarSyncService;
require dirname(__DIR__).'/app/core/Env.php';Env::load(dirname(__DIR__).'/.env');
spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$pdo=Database::connection();$rows=$pdo->query("SELECT * FROM communication_queue WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT 25")->fetchAll();
foreach($rows as $row){$pdo->prepare("UPDATE communication_queue SET status='processing',attempts=attempts+1 WHERE id=?")->execute([$row['id']]);try{$payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);if($row['channel']==='calendar'&&in_array($row['action'],['create_event','push_job'],true))CalendarSyncService::pushJob((int)$payload['job_id']);if($row['channel']==='calendar'&&$row['action']==='graph_notification')CalendarSyncService::pullEvent($payload);$pdo->prepare("UPDATE communication_queue SET status='completed',completed_at=NOW(),last_error=NULL WHERE id=?")->execute([$row['id']]);}catch(Throwable $e){$delay=min(1440,5*(2**min(8,(int)$row['attempts'])));$status=((int)$row['attempts']>=8)?'failed':'pending';$pdo->prepare("UPDATE communication_queue SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? MINUTE),last_error=? WHERE id=?")->execute([$status,$delay,$e->getMessage(),$row['id']]);}}
echo 'Processed '.count($rows)." queue item(s).\n";
