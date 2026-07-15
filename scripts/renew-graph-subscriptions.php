<?php
declare(strict_types=1);
use App\Core\Env; use App\Core\Database; use App\Services\GraphService;
require dirname(__DIR__).'/app/core/Env.php';Env::load(dirname(__DIR__).'/.env');spl_autoload_register(function($class){$p='App\\';if(!str_starts_with($class,$p))return;$f=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($f))require$f;});
$pdo=Database::connection();$rows=$pdo->query("SELECT * FROM graph_subscriptions WHERE expiration_at < DATE_ADD(NOW(), INTERVAL 24 HOUR)")->fetchAll();
foreach($rows as $r){try{(new GraphService())->request('PATCH','/subscriptions/'.$r['subscription_id'],['expirationDateTime'=>gmdate('c',time()+2*86400)]);$pdo->prepare('UPDATE graph_subscriptions SET expiration_at=DATE_ADD(NOW(),INTERVAL 2 DAY) WHERE id=?')->execute([$r['id']]);echo "Renewed {$r['subscription_id']}\n";}catch(Throwable $e){fwrite(STDERR,"Failed {$r['subscription_id']}: {$e->getMessage()}\n");}}
