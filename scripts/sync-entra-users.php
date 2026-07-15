<?php
require dirname(__DIR__).'/app/core/Env.php';
App\Core\Env::load(dirname(__DIR__).'/.env');
spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$parts=explode('\\',substr($class,strlen($prefix)));$parts[0]=strtolower($parts[0]);$file=dirname(__DIR__).'/app/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$settings=(new App\Services\EntraAccessService())->settings();
if(empty($settings['schedule_enabled']) && !in_array('--force',$argv,true)){
    fwrite(STDOUT,"Scheduled Entra synchronization is disabled. Use --force to run manually.\n");
    exit(0);
}
$disable=in_array('--disable-missing',$argv,true) || !empty($settings['disable_missing']);
$result=(new App\Services\EntraUserSyncService())->syncAll($disable);
echo json_encode($result,JSON_PRETTY_PRINT),PHP_EOL;
exit($result['errors']?1:0);
