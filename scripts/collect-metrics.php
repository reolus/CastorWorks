#!/usr/bin/env php
<?php
declare(strict_types=1);
use App\Core\Database; use App\Core\Env;
require dirname(__DIR__).'/app/core/Env.php';Env::load(dirname(__DIR__).'/.env');spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$pdo=Database::connection();$metrics=[];
try{$start=microtime(true);$pdo->query('SELECT 1')->fetchColumn();$ms=(microtime(true)-$start)*1000;$metrics[]=['database.response_ms',$ms,null,$ms>1000?'critical':($ms>250?'warning':'ok')];}catch(Throwable $e){$metrics[]=['database.response_ms',null,$e->getMessage(),'critical'];}
$free=@disk_free_space(dirname(__DIR__));$total=@disk_total_space(dirname(__DIR__));$pct=($free&&$total)?($free/$total*100):null;$metrics[]=['storage.free_percent',$pct,null,$pct===null?'unknown':($pct<5?'critical':($pct<15?'warning':'ok'))];
$queue=(int)$pdo->query("SELECT COUNT(*) FROM integration_queue WHERE status IN ('pending','failed')")->fetchColumn();$metrics[]=['queue.pending_count',$queue,null,$queue>100?'critical':($queue>20?'warning':'ok')];
$overdue=(int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE balance_due>0 AND due_date<CURDATE()")->fetchColumn();$metrics[]=['billing.overdue_invoices',$overdue,null,$overdue>25?'warning':'ok'];
$stmt=$pdo->prepare('INSERT INTO system_metrics(metric_key,metric_value,metric_text,status) VALUES(?,?,?,?)');foreach($metrics as $m){$stmt->execute($m);echo $m[0].': '.($m[1]??$m[2]).' ['.$m[3].']'.PHP_EOL;}
