<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View; use App\Services\AuditService;
final class TaxRuleController
{
 public function index():void{Auth::requireRole('owner','administrator','office');$pdo=Database::connection();View::render('portal/tax-rules/index',['title'=>'Tax & discount rules','rules'=>$pdo->query('SELECT * FROM tax_rules ORDER BY priority,name')->fetchAll(),'services'=>$pdo->query('SELECT id,name FROM services WHERE active=1 ORDER BY name')->fetchAll(),'territories'=>$pdo->query('SELECT id,name FROM service_territories WHERE active=1 ORDER BY name')->fetchAll()],'portal');}
 public function store():void{Auth::requireRole('owner','administrator');verify_csrf();$pdo=Database::connection();$pdo->prepare('INSERT INTO tax_rules(name,rule_type,calculation_type,amount,applies_to,reference_id,priority,active,starts_at,ends_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([trim($_POST['name']),$_POST['rule_type'],$_POST['calculation_type'],(float)$_POST['amount'],$_POST['applies_to'],($_POST['reference_id']??'')!==''?(int)$_POST['reference_id']:null,(int)($_POST['priority']??100),isset($_POST['active'])?1:0,$_POST['starts_at']?:null,$_POST['ends_at']?:null]);AuditService::log('tax_rule.created','tax_rule',(int)$pdo->lastInsertId());flash('success','Rule created.');redirect('/portal/tax-rules');}
 public function update(string $id):void{Auth::requireRole('owner','administrator');verify_csrf();Database::connection()->prepare('UPDATE tax_rules SET name=?,amount=?,priority=?,active=? WHERE id=?')->execute([trim($_POST['name']),(float)$_POST['amount'],(int)$_POST['priority'],isset($_POST['active'])?1:0,(int)$id]);flash('success','Rule updated.');redirect('/portal/tax-rules');}
}
