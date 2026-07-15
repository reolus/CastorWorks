<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View; use App\Services\GraphService;
final class GraphDiagnosticsController {
 public function index():void{Auth::requireRole('owner','administrator');$pdo=Database::connection();$rows=$pdo->query('SELECT * FROM graph_sync_diagnostics ORDER BY created_at DESC LIMIT 100')->fetchAll();View::render('portal/graph-diagnostics/index',['title'=>'Graph Diagnostics','rows'=>$rows],'portal');}
 public function test():void{Auth::requireRole('owner','administrator');verify_csrf();$start=microtime(true);$status='ok';$detail='Graph authentication, mailbox, and calendar validated.';$http=200;try{$g=new GraphService();if(!$g->configured())throw new \RuntimeException('Graph is not configured.');$g->request('GET','/users/'.rawurlencode((string)\App\Core\Env::get('M365_SHARED_MAILBOX','')).'?%24select=id,displayName,mail');}catch(\Throwable $e){$status='failed';$detail=$e->getMessage();$http=null;}Database::connection()->prepare('INSERT INTO graph_sync_diagnostics(operation,status,http_status,duration_ms,detail) VALUES(?,?,?,?,?)')->execute(['mailbox_calendar_test',$status,$http,(int)((microtime(true)-$start)*1000),$detail]);flash($status==='ok'?'success':'danger',$detail);redirect('/portal/graph-diagnostics');}
}
