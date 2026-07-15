<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Env;use App\Core\View;use App\Services\IntegrationHealthService;
final class HealthController
{
 public function index():void{
  Auth::requireRole('owner','administrator');
  $checks=[
   ['name'=>'MySQL','status'=>'ok','detail'=>'Connected to '.Env::get('DB_DATABASE','rockbluffs_exterior')],
   ['name'=>'Storage','status'=>is_writable(dirname(__DIR__,2).'/storage')?'ok':'failed','detail'=>dirname(__DIR__,2).'/storage'],
  ];
  foreach((new IntegrationHealthService())->all(true) as $item){
   if(!$item['configured']&&($item['optional']??false))$status='disabled';
   else $status=!$item['configured']?'warning':($item['healthy']===false?'failed':($item['healthy']===true?'ok':'warning'));
   $detail=$item['detail'].($item['latency_ms']!==null?' ('.$item['latency_ms'].' ms)':'');
   $checks[]=['name'=>$item['name'],'status'=>$status,'detail'=>$detail,'last_tested_at'=>$item['last_tested_at']??null];
  }
  $checks[]=['name'=>'PHP','status'=>version_compare(PHP_VERSION,'8.2.0','>=')?'ok':'warning','detail'=>PHP_VERSION];
  View::render('portal/health/index',['title'=>'System Health','checks'=>$checks],'portal');
 }
}
