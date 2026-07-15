<?php
namespace App\Services;
use PDO;
final class CustomerTimelineService
{
 public static function get(PDO $pdo,int $customerId,int $limit=100):array{
  $items=[];
  $sources=[
   ['estimate','SELECT id,estimate_number ref,status,total,created_at occurred_at FROM estimates WHERE customer_id=?','fa-file-signature','/portal/estimates/'],
   ['job','SELECT id,job_number ref,status,service_summary total,created_at occurred_at FROM jobs WHERE customer_id=?','fa-briefcase','/portal/jobs/'],
   ['invoice','SELECT id,invoice_number ref,status,balance_due total,created_at occurred_at FROM invoices WHERE customer_id=?','fa-file-invoice-dollar','/portal/invoices/'],
   ['payment','SELECT p.id,p.invoice_id,i.invoice_number ref,p.method status,p.amount total,p.received_at occurred_at FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE i.customer_id=?','fa-credit-card','/portal/invoices/'],
  ];
  foreach($sources as [$type,$sql,$icon,$prefix]){try{$s=$pdo->prepare($sql);$s->execute([$customerId]);foreach($s->fetchAll() as $r){$r['type']=$type;$r['icon']=$icon;$r['url']=$type==='payment'?$prefix.$r['invoice_id']:$prefix.$r['id'];$items[]=$r;}}catch(\Throwable){}}
  try{$s=$pdo->prepare("SELECT id,subject ref,status,channel total,created_at occurred_at FROM conversations WHERE customer_id=?");$s->execute([$customerId]);foreach($s->fetchAll() as $r){$r+=['type'=>'conversation','icon'=>'fa-comments','url'=>'/portal/conversations'];$items[]=$r;}}catch(\Throwable){}
  usort($items,fn($a,$b)=>strcmp((string)$b['occurred_at'],(string)$a['occurred_at']));return array_slice($items,0,$limit);
 }
}
