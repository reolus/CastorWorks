<?php
namespace App\Controllers;
use App\Core\Database; use App\Core\View;
final class PublicInvoiceController
{
 public function show(string $token): void {$s=Database::connection()->prepare('SELECT i.*,c.display_name,c.email,c.phone,j.service_summary FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN jobs j ON j.id=i.job_id WHERE i.public_token=?');$s->execute([$token]);$i=$s->fetch();if(!$i){http_response_code(404);View::render('errors/404',['title'=>'Invoice unavailable'],'public');return;}View::render('customer/invoice',['title'=>$i['invoice_number'],'invoice'=>$i],'public');}
}
