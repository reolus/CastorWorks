#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Env;use App\Core\Database;use App\Services\CommercialContractService;
require dirname(__DIR__).'/app/core/Env.php';Env::load(dirname(__DIR__).'/.env');spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$parts=explode('\\',substr($class,strlen($prefix)));$parts[0]=strtolower($parts[0]);$file=dirname(__DIR__).'/app/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$pdo=Database::connection();$pdo->exec("INSERT INTO commercial_contract_worker_runs(worker_key,status) VALUES('renewals','running')");$id=(int)$pdo->lastInsertId();try{$r=(new CommercialContractService($pdo))->monitorRenewals();$count=$r['pending']+$r['renewed'];$pdo->prepare("UPDATE commercial_contract_worker_runs SET status='ok',records_processed=?,detail=?,completed_at=NOW() WHERE id=?")->execute([$count,json_encode($r),$id]);echo $r['renewed']." renewed; ".$r['pending']." marked pending.\n";}catch(Throwable $e){$pdo->prepare("UPDATE commercial_contract_worker_runs SET status='failed',detail=?,completed_at=NOW() WHERE id=?")->execute([$e->getMessage(),$id]);fwrite(STDERR,$e->getMessage()."\n");exit(1);}
