<?php
namespace App\Controllers;
use App\Core\Auth; use App\Core\Database; use App\Core\View;
final class AccountingController {
 public function index():void{Auth::requireRole('owner','office');View::render('portal/accounting/index',['title'=>'Accounting Export'],'portal');}
 public function export():void{Auth::requireRole('owner','office');$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-t');$pdo=Database::connection();$s=$pdo->prepare("SELECT i.invoice_number,i.issue_date,c.display_name,i.subtotal,i.tax,i.total,i.balance_due,i.status FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.issue_date BETWEEN ? AND ? ORDER BY i.issue_date,i.invoice_number");$s->execute([$from,$to]);header('Content-Type:text/csv');header('Content-Disposition:attachment; filename="rbes-accounting-'.$from.'-'.$to.'.csv"');$o=fopen('php://output','w');fputcsv($o,['Invoice Number','Date','Customer','Income Account','Subtotal','Tax','Total','Balance','Status']);foreach($s as $r)fputcsv($o,[$r['invoice_number'],$r['issue_date'],$r['display_name'],'Service Revenue',$r['subtotal'],$r['tax'],$r['total'],$r['balance_due'],$r['status']]);fclose($o);}
}
