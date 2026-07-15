<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;use App\Services\AuditService;
final class CustomFieldController{
 public function index():void{Auth::requireRole('owner','administrator');$rows=Database::connection()->query('SELECT * FROM custom_field_definitions ORDER BY entity_type,sort_order,label')->fetchAll();View::render('portal/custom-fields/index',['title'=>'Custom Fields','fields'=>$rows],'portal');}
 public function store():void{Auth::requireRole('owner','administrator');verify_csrf();$key=preg_replace('/[^a-z0-9_]+/','_',strtolower(trim($_POST['field_key'])));Database::connection()->prepare('INSERT INTO custom_field_definitions(entity_type,field_key,label,field_type,options_json,required,active,sort_order) VALUES(?,?,?,?,?,?,?,?)')->execute([$_POST['entity_type'],$key,trim($_POST['label']),$_POST['field_type'],($_POST['options']??'')!==''?json_encode(array_values(array_filter(array_map('trim',explode(',',$_POST['options']))))):null,isset($_POST['required'])?1:0,isset($_POST['active'])?1:0,(int)($_POST['sort_order']??100)]);AuditService::log('custom_field.created','custom_field_definition',(int)Database::connection()->lastInsertId());redirect('/portal/custom-fields');}
 public function toggle(int $id):void{Auth::requireRole('owner','administrator');verify_csrf();Database::connection()->prepare('UPDATE custom_field_definitions SET active=1-active WHERE id=?')->execute([$id]);redirect('/portal/custom-fields');}
}
