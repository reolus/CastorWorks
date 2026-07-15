<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View;
final class ObservabilityController {
 public function index():void{Auth::requireRole('owner','administrator');$pdo=Database::connection();$metrics=$pdo->query("SELECT m.* FROM system_metrics m JOIN (SELECT metric_key,MAX(measured_at) max_time FROM system_metrics GROUP BY metric_key) x ON x.metric_key=m.metric_key AND x.max_time=m.measured_at ORDER BY FIELD(m.status,'critical','warning','unknown','ok'),m.metric_key")->fetchAll();$history=$pdo->query("SELECT * FROM system_metrics ORDER BY measured_at DESC LIMIT 100")->fetchAll();View::render('portal/observability/index',['title'=>'Production Monitoring','metrics'=>$metrics,'history'=>$history],'portal');}
}
