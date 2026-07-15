<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View; use App\Services\AuditService;
final class CollectionsController {
 public function index():void{Auth::requireRole('owner','office');$pdo=Database::connection();$rows=$pdo->query("SELECT i.*,c.display_name,DATEDIFF(CURDATE(),i.due_date) days_overdue,(SELECT MAX(action_date) FROM collection_actions ca WHERE ca.invoice_id=i.id) last_action FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.balance_due>0 AND i.due_date<CURDATE() ORDER BY days_overdue DESC")->fetchAll();View::render('portal/collections/index',['title'=>'Collections','rows'=>$rows],'portal');}
 public function action(string $id):void{Auth::requireRole('owner','office');verify_csrf();$pdo=Database::connection();$pdo->prepare('INSERT INTO collection_actions(invoice_id,action_type,amount_promised,promise_date,notes,status,created_by) VALUES(?,?,?,?,?,?,?)')->execute([(int)$id,$_POST['action_type'],($_POST['amount_promised']??'')?:null,($_POST['promise_date']??'')?:null,trim($_POST['notes']??''),'completed',Auth::id()]);AuditService::log('collection.action','invoice',(int)$id);flash('success','Collection action recorded.');redirect('/portal/collections');}
}
