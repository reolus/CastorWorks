<?php
namespace App\Services;
use App\Core\Database;
final class CustomFieldService{
 public static function definitions(string $entityType):array{$s=Database::connection()->prepare('SELECT * FROM custom_field_definitions WHERE entity_type=? AND active=1 ORDER BY sort_order,label');$s->execute([$entityType]);$rows=$s->fetchAll();foreach($rows as &$r)$r['options']=json_decode($r['options_json']??'[]',true)?:[];return $rows;}
 public static function values(string $entityType,int $entityId):array{$s=Database::connection()->prepare('SELECT d.field_key,v.value_text FROM custom_field_values v JOIN custom_field_definitions d ON d.id=v.custom_field_definition_id WHERE d.entity_type=? AND v.entity_id=?');$s->execute([$entityType,$entityId]);$out=[];foreach($s->fetchAll() as $r)$out[$r['field_key']]=$r['value_text'];return $out;}
 public static function save(string $entityType,int $entityId,array $input,?int $userId):void{$defs=self::definitions($entityType);$pdo=Database::connection();$q=$pdo->prepare('INSERT INTO custom_field_values(custom_field_definition_id,entity_id,value_text,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text),updated_by=VALUES(updated_by),updated_at=NOW()');foreach($defs as $d){$key='custom_'.$d['field_key'];$v=$d['field_type']==='checkbox'?(isset($input[$key])?'1':'0'):trim((string)($input[$key]??''));if($d['required']&&$v==='')throw new \InvalidArgumentException($d['label'].' is required.');$q->execute([$d['id'],$entityId,$v,$userId]);}}
}
