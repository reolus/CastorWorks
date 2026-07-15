<?php

declare(strict_types=1);

use App\Core\Env;
use App\Services\RouteProgressService;

$root=dirname(__DIR__); require $root.'/app/core/Env.php'; Env::load($root.'/.env');
$autoload=$root.'/vendor/autoload.php'; if(is_file($autoload))require $autoload;
spl_autoload_register(function(string $class)use($root):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$parts=explode('\\',substr($class,strlen($prefix)));$parts[0]=strtolower($parts[0]);$file=$root.'/app/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$date=date('Y-m-d'); foreach($argv as $arg){if(str_starts_with($arg,'--date='))$date=substr($arg,7);} 
try{$result=(new RouteProgressService())->updateDate($date);echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;exit(0);}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
