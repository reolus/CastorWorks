<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class ModuleController
{
 public function index():void{Auth::requireRole('owner','administrator');$manifest=require dirname(__DIR__).'/config/modules.php';$states=[];try{$rows=Database::connection()->query('SELECT module_key,enabled FROM module_states')->fetchAll();foreach($rows as $r)$states[$r['module_key']]=(bool)$r['enabled'];}catch(\Throwable){}foreach($manifest as $key=>&$module)$module['enabled']=$states[$key]??$module['enabled'];View::render('portal/modules/index',['title'=>'Modules','modules'=>$manifest],'portal');}
 public function update():void{Auth::requireRole('owner','administrator');verify_csrf();$key=preg_replace('/[^a-z0-9_-]/i','',(string)($_POST['module_key']??''));$enabled=isset($_POST['enabled'])?1:0;$manifest=require dirname(__DIR__).'/config/modules.php';if(!isset($manifest[$key])){flash('danger','Unknown module.');redirect('/portal/modules');}if($key==='core')$enabled=1;Database::connection()->prepare('INSERT INTO module_states(module_key,enabled,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by),updated_at=NOW()')->execute([$key,$enabled,Auth::id()]);flash('success',$manifest[$key]['name'].' updated.');redirect('/portal/modules');}
}
