<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;use App\Services\AuditService;
final class ServiceController{
 public function index():void{Auth::requireRole('owner','office');$rows=Database::connection()->query('SELECT * FROM services ORDER BY category,name')->fetchAll();View::render('portal/services/index',['title'=>'Service Catalog','services'=>$rows],'portal');}
 public function store():void{Auth::requireRole('owner','office');verify_csrf();$s=Database::connection()->prepare('INSERT INTO services(name,category,description,default_price,unit_label,active) VALUES(?,?,?,?,?,1)');$s->execute([trim($_POST['name']),trim($_POST['category']),trim($_POST['description']??''),($_POST['default_price']??'')===''?null:(float)$_POST['default_price'],trim($_POST['unit_label']??'project')]);AuditService::log('service.created','service',(int)Database::connection()->lastInsertId());flash('success','Service created.');redirect('/portal/services');}
 public function update(string $id):void{Auth::requireRole('owner','office');verify_csrf();$s=Database::connection()->prepare('UPDATE services SET name=?,category=?,description=?,default_price=?,unit_label=?,active=? WHERE id=?');$s->execute([trim($_POST['name']),trim($_POST['category']),trim($_POST['description']??''),($_POST['default_price']??'')===''?null:(float)$_POST['default_price'],trim($_POST['unit_label']),isset($_POST['active'])?1:0,(int)$id]);AuditService::log('service.updated','service',(int)$id);flash('success','Service updated.');redirect('/portal/services');}
}
