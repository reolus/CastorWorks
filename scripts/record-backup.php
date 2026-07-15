<?php
use App\Core\Env;use App\Core\Database;
$base=dirname(__DIR__);require $base.'/app/core/Env.php';Env::load($base.'/.env');spl_autoload_register(function($c)use($base){$p='App\\';if(str_starts_with($c,$p)){ $f=$base.'/app/'.str_replace('\\','/',substr($c,strlen($p))).'.php';if(is_file($f))require $f;}});
[$script,$type,$status,$file,$detail]=$argv+array_fill(0,5,null);$size=is_file($file)?filesize($file):null;$hash=is_file($file)?hash_file('sha256',$file):null;Database::connection()->prepare('INSERT INTO backup_runs(backup_type,status,filename,size_bytes,checksum_sha256,detail,completed_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$type?:'full',$status?:'completed',$file?basename($file):null,$size,$hash,$detail]);
