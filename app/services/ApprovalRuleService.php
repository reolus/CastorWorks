<?php
namespace App\Services;
use App\Core\Database;
final class ApprovalRuleService {
 public static function evaluate(string $type,int $id,float $value,?int $requestedBy=null):bool{
  $pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM approval_rules WHERE entity_type=? AND active=1');$s->execute([$type]);$needed=false;
  foreach($s->fetchAll() as $r){$t=(float)($r['threshold_value']??0);$ok=match($r['operator']){'always'=>true,'gt'=>$value>$t,'gte'=>$value>=$t,'lt'=>$value<$t,'lte'=>$value<=$t,'eq'=>abs($value-$t)<0.001,default=>false};if(!$ok)continue;$needed=true;$q=$pdo->prepare("SELECT COUNT(*) FROM entity_approvals WHERE entity_type=? AND entity_id=? AND status='pending'");$q->execute([$type,$id]);if(!(int)$q->fetchColumn()){$pdo->prepare('INSERT INTO entity_approvals(entity_type,entity_id,approval_rule_id,requested_by,assigned_role,request_note) VALUES(?,?,?,?,?,?)')->execute([$type,$id,$r['id'],$requestedBy,$r['required_role'],'Automatically requested by '.$r['name']]);}}
  return $needed;
 }
}
