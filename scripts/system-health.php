<?php
declare(strict_types=1);
use App\Core\Database;use App\Core\Env;
require dirname(__DIR__).'/app/core/Env.php';Env::load(dirname(__DIR__).'/.env');spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
$checks=[];try{Database::connection()->query('SELECT 1');$checks[]=['mysql','ok','Connection successful'];}catch(Throwable $e){$checks[]=['mysql','failed',$e->getMessage()];}
$checks[]=['storage',is_writable(dirname(__DIR__).'/storage')?'ok':'failed',dirname(__DIR__).'/storage'];
foreach(['M365_TENANT_ID'=>'graph','STRIPE_SECRET_KEY'=>'stripe','TWILIO_ACCOUNT_SID'=>'twilio'] as $k=>$n)$checks[]=[$n,Env::get($k)?'ok':'warning',Env::get($k)?'configured':'not configured'];
$db=Database::connection();$ins=$db->prepare('INSERT INTO system_health_checks(check_name,status,detail) VALUES(?,?,?)');foreach($checks as $c){$ins->execute($c);echo implode(' | ',$c).PHP_EOL;}
