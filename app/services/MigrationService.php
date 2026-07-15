<?php
namespace App\Services;
use App\Core\Database;
final class MigrationService
{
 public static function pending():array{$pdo=Database::connection();$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,migration VARCHAR(190) NOT NULL UNIQUE,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");$done=$pdo->query('SELECT migration FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);$files=glob(dirname(__DIR__,2).'/database/migrate_phase*.sql')?:[];sort($files,SORT_NATURAL);return array_values(array_filter($files,fn($f)=>!in_array(basename($f),$done,true)));}
 public static function run(string $file):void{$pdo=Database::connection();$sql=file_get_contents($file);$sql=preg_replace('/^\s*(USE|SOURCE)\b.*;\s*$/mi','',$sql);$parts=preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[];foreach($parts as $stmt){$stmt=trim($stmt);if($stmt===''||str_starts_with($stmt,'--'))continue;try{$pdo->exec($stmt);}catch(\PDOException $e){if(!str_contains($e->getMessage(),'Duplicate column')&&!str_contains($e->getMessage(),'already exists')&&!str_contains($e->getMessage(),'Duplicate key'))throw $e;}}$pdo->prepare('INSERT IGNORE INTO schema_migrations(migration) VALUES(?)')->execute([basename($file)]);}
 public static function runPending():array{$ran=[];foreach(self::pending() as $file){self::run($file);$ran[]=basename($file);}return $ran;}
}
