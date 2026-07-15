<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View; use App\Services\AuditService;
final class TemplateController
{
 public function index(): void {Auth::requireRole('owner','administrator','office');$rows=Database::connection()->query('SELECT * FROM email_templates ORDER BY name')->fetchAll();View::render('portal/templates/index',['title'=>'Email Templates','templates'=>$rows],'portal');}
 public function update(string $id): void {Auth::requireRole('owner','administrator','office');verify_csrf();Database::connection()->prepare('UPDATE email_templates SET name=?,subject=?,html_body=?,active=? WHERE id=?')->execute([trim($_POST['name']),trim($_POST['subject']),$_POST['html_body'],isset($_POST['active']),(int)$id]);AuditService::log('email_template.updated','email_template',(int)$id);flash('success','Template updated.');redirect('/portal/email-templates');}
}
