<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class AssetAlertController{
 public function index():void{Auth::requireRole('owner','administrator','office');$pdo=Database::connection();$rows=$pdo->query('SELECT a.*,p.asset_type,p.asset_id,p.replacement_target_date,p.replacement_budget FROM asset_replacement_alerts a JOIN asset_replacement_plans p ON p.id=a.asset_replacement_plan_id ORDER BY FIELD(a.status,\'open\',\'acknowledged\',\'resolved\'),FIELD(a.severity,\'critical\',\'warning\',\'info\'),a.created_at DESC')->fetchAll();View::render('portal/assets/alerts',['title'=>'Asset Replacement Alerts','alerts'=>$rows],'portal');}
 public function update(int $id):void{Auth::requireRole('owner','administrator','office');verify_csrf();$status=$_POST['status']??'acknowledged';if(!in_array($status,['acknowledged','resolved'],true))$status='acknowledged';Database::connection()->prepare('UPDATE asset_replacement_alerts SET status=?,acknowledged_by=?,acknowledged_at=IF(?=\'acknowledged\',NOW(),acknowledged_at),resolved_at=IF(?=\'resolved\',NOW(),resolved_at) WHERE id=?')->execute([$status,Auth::id(),$status,$status,$id]);redirect('/portal/asset-alerts');}
}
