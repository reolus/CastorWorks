<?php
declare(strict_types=1);
use App\Core\Database;use App\Core\Env;
require dirname(__DIR__).'/app/core/Env.php';Env::load(dirname(__DIR__).'/.env');spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});$days=max(1,(int)Env::get('TECHNICIAN_LOCATION_RETENTION_DAYS','30'));$s=Database::connection()->prepare('DELETE FROM technician_locations WHERE captured_at < DATE_SUB(NOW(),INTERVAL ? DAY)');$s->execute([$days]);echo 'Deleted '.$s->rowCount().' old technician location records.'.PHP_EOL;
