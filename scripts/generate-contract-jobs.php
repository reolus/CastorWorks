#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Database;
use App\Services\CommercialContractService;

require dirname(__DIR__).'/app/core/Env.php';
Env::load(dirname(__DIR__).'/.env');
spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$parts=explode('\\',substr($class,strlen($prefix)));$parts[0]=strtolower($parts[0]);$file=dirname(__DIR__).'/app/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$vendor=dirname(__DIR__).'/vendor/autoload.php';if(is_file($vendor))require_once $vendor;
$pdo=Database::connection();$run=$pdo->prepare("INSERT INTO commercial_contract_worker_runs(worker_key,status) VALUES('recurring_jobs','running')");$run->execute();$runId=(int)$pdo->lastInsertId();
try{$result=(new CommercialContractService($pdo))->generateDueJobs(null,250);$detail=json_encode($result,JSON_UNESCAPED_SLASHES);$pdo->prepare("UPDATE commercial_contract_worker_runs SET status='ok',records_processed=?,detail=?,completed_at=NOW() WHERE id=?")->execute([$result['created'],$detail,$runId]);echo $result['created']." commercial job(s) created; ".$result['skipped']." skipped.\n";exit($result['errors']?2:0);}catch(Throwable $e){$pdo->prepare("UPDATE commercial_contract_worker_runs SET status='failed',detail=?,completed_at=NOW() WHERE id=?")->execute([$e->getMessage(),$runId]);fwrite(STDERR,$e->getMessage()."\n");exit(1);}
