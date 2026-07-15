<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Services\RouteAnalyticsService;

$root=dirname(__DIR__);require $root.'/app/core/Env.php';Env::load($root.'/.env');$autoload=$root.'/vendor/autoload.php';if(is_file($autoload))require $autoload;
spl_autoload_register(function(string $class)use($root):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$parts=explode('\\',substr($class,strlen($prefix)));$parts[0]=strtolower($parts[0]);$file=$root.'/app/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$date=$argv[1]??date('Y-m-d');try{$r=(new RouteAnalyticsService(Database::connection()))->buildDate($date);echo json_encode($r,JSON_PRETTY_PRINT).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
