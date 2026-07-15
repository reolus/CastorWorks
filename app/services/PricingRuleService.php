<?php
namespace App\Services;
use App\Core\Database;
final class PricingRuleService
{
 public static function calculate(float $subtotal,string $customerType='residential',?int $serviceId=null,?int $territoryId=null):array
 {
  $rows=Database::connection()->query("SELECT * FROM tax_rules WHERE active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY priority,id")->fetchAll();$discount=0.0;$tax=0.0;$applied=[];
  foreach($rows as $r){$ok=$r['applies_to']==='all'||$r['applies_to']===$customerType||($r['applies_to']==='service'&&(int)$r['reference_id']===$serviceId)||($r['applies_to']==='territory'&&(int)$r['reference_id']===$territoryId);if(!$ok)continue;$base=max(0,$subtotal-$discount);$v=$r['calculation_type']==='percentage'?$base*((float)$r['amount']/100):(float)$r['amount'];if($r['rule_type']==='discount')$discount+=$v;else $tax+=$v;$applied[]=['name'=>$r['name'],'amount'=>round($v,2),'type'=>$r['rule_type']];}
  return ['subtotal'=>round($subtotal,2),'discount'=>round(min($discount,$subtotal),2),'tax'=>round($tax,2),'total'=>round(max(0,$subtotal-$discount)+$tax,2),'rules'=>$applied];
 }
}
