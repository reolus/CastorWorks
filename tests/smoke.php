<?php
declare(strict_types=1);
$root=dirname(__DIR__);$required=['public/index.php','app/core/Router.php','app/core/Database.php','database/schema.sql','.env.example'];$fail=[];foreach($required as $f)if(!is_file($root.'/'.$f))$fail[]=$f;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f){if($f->isFile()&&$f->getExtension()==='php'){exec('php -l '.escapeshellarg($f->getPathname()),$o,$c);if($c!==0)$fail[]=$f->getPathname();}}if($fail){fwrite(STDERR,"Smoke test failed:\n - ".implode("\n - ",$fail)."\n");exit(1);}echo "Smoke test passed.\n";
