<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class SmsTemplateController{
 public function index():void{Auth::requireRole('owner','office');$templates=Database::connection()->query('SELECT * FROM sms_templates ORDER BY name')->fetchAll();View::render('portal/sms-templates/index',['title'=>'SMS Templates','templates'=>$templates],'portal');}
 public function update(string $id):void{Auth::requireRole('owner','office');verify_csrf();Database::connection()->prepare('UPDATE sms_templates SET name=?,body=?,active=? WHERE id=?')->execute([trim($_POST['name']),trim($_POST['body']),isset($_POST['active'])?1:0,(int)$id]);flash('success','SMS template updated.');redirect('/portal/sms-templates');}
 public function callback():void{$sid=$_POST['MessageSid']??'';$status=$_POST['MessageStatus']??'';if($sid!=='')Database::connection()->prepare('UPDATE sms_messages SET provider_status=?,status=?,delivered_at=IF(?="delivered",NOW(),delivered_at),status_callback_at=NOW() WHERE provider_message_id=?')->execute([$status,in_array($status,['delivered','sent'],true)?$status:($status==='failed'||$status==='undelivered'?'failed':'sent'),$status,$sid]);http_response_code(204);}
}
