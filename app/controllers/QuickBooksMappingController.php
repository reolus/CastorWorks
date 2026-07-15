<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View;
final class QuickBooksMappingController
{
 public function index():void{Auth::requireRole('owner','administrator','office');$pdo=Database::connection();View::render('portal/quickbooks/mappings',['title'=>'QuickBooks mappings','mappings'=>$pdo->query('SELECT * FROM quickbooks_mappings ORDER BY mapping_type,quickbooks_name')->fetchAll(),'services'=>$pdo->query('SELECT id,name FROM services ORDER BY name')->fetchAll()],'portal');}
 public function store():void{Auth::requireRole('owner','administrator');verify_csrf();Database::connection()->prepare('INSERT INTO quickbooks_mappings(mapping_type,local_reference_type,local_reference_id,local_key,quickbooks_id,quickbooks_name,active) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quickbooks_id=VALUES(quickbooks_id),quickbooks_name=VALUES(quickbooks_name),active=VALUES(active)')->execute([$_POST['mapping_type'],$_POST['local_reference_type']?:null,($_POST['local_reference_id']??'')!==''?(int)$_POST['local_reference_id']:null,$_POST['local_key']?:null,trim($_POST['quickbooks_id']),trim($_POST['quickbooks_name']),isset($_POST['active'])?1:0]);flash('success','Mapping saved.');redirect('/portal/quickbooks/mappings');}
}
