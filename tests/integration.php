<?php
declare(strict_types=1);
use App\Core\Env;use App\Core\Database;
$root=dirname(__DIR__);require $root.'/app/core/Env.php';Env::load($root.'/.env');spl_autoload_register(function(string $class)use($root){$prefix='App\\';if(!str_starts_with($class,$prefix))return;$file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});
try{$pdo=Database::connection();$required=['inspections','inspection_responses','custom_field_values','conversation_threads','equipment_custody','employee_certifications','api_idempotency_keys'];foreach($required as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$table]);if(!(int)$q->fetchColumn())throw new RuntimeException("Missing table: $table");echo "PASS - table $table\n";}echo "Database integration checks passed.\n";}catch(Throwable $e){fwrite(STDERR,"SKIP/FAIL - ".$e->getMessage()."\n");exit(2);}
