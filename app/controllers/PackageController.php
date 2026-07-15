<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;use App\Core\View;
final class PackageController{
 public function index():void{Auth::requireRole('owner','office','estimator');$db=Database::connection();$packages=$db->query('SELECT * FROM service_packages ORDER BY name')->fetchAll();$services=$db->query('SELECT * FROM services WHERE active=1 ORDER BY category,name')->fetchAll();$items=$db->query('SELECT spi.*,s.name service_name FROM service_package_items spi JOIN services s ON s.id=spi.service_id')->fetchAll();$by=[];foreach($items as $i)$by[$i['service_package_id']][]=$i;View::render('portal/packages/index',['title'=>'Service Packages','packages'=>$packages,'services'=>$services,'packageItems'=>$by],'portal');}
 public function store():void{Auth::requireRole('owner','office');verify_csrf();$db=Database::connection();$db->beginTransaction();$db->prepare('INSERT INTO service_packages(name,description,package_price,active) VALUES(?,?,?,1)')->execute([trim($_POST['name']),trim($_POST['description']??''),(float)($_POST['package_price']??0)]);$id=(int)$db->lastInsertId();$ins=$db->prepare('INSERT INTO service_package_items(service_package_id,service_id,quantity) VALUES(?,?,?)');foreach($_POST['service_ids']??[] as $sid)$ins->execute([$id,(int)$sid,1]);$db->commit();flash('success','Service package created.');redirect('/portal/packages');}
}
