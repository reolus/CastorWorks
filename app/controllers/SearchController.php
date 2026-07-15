<?php
namespace App\Controllers;
use App\Core\Auth;use App\Core\Database;
final class SearchController
{
 public function index():void{
  Auth::requireLogin();header('Content-Type: application/json; charset=utf-8');
  $q=trim((string)($_GET['q']??''));if(mb_strlen($q)<2){echo json_encode(['results'=>[]]);return;}
  $pdo=Database::connection();$like='%'.$q.'%';$groups=[];
  $queries=[
   'Customers'=>["SELECT id,display_name title,COALESCE(email,phone,'') subtitle,CONCAT('/portal/customers/',id) url FROM customers WHERE display_name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY updated_at DESC LIMIT 6",4,'fa-users'],
   'Jobs'=>["SELECT id,job_number title,CONCAT(service_summary,' · ',status) subtitle,CONCAT('/portal/jobs/',id) url FROM jobs WHERE job_number LIKE ? OR service_summary LIKE ? OR notes LIKE ? ORDER BY updated_at DESC LIMIT 6",3,'fa-briefcase'],
   'Estimates'=>["SELECT id,estimate_number title,CONCAT(status,' · $',FORMAT(total,2)) subtitle,CONCAT('/portal/estimates/',id) url FROM estimates WHERE estimate_number LIKE ? OR notes LIKE ? ORDER BY updated_at DESC LIMIT 6",2,'fa-file-signature'],
   'Invoices'=>["SELECT id,invoice_number title,CONCAT(status,' · $',FORMAT(balance_due,2)) subtitle,CONCAT('/portal/invoices/',id) url FROM invoices WHERE invoice_number LIKE ? ORDER BY updated_at DESC LIMIT 6",1,'fa-file-invoice-dollar'],
   'Properties'=>["SELECT id,CONCAT(address1,', ',city) title,CONCAT(state,' ',postal_code) subtitle,CONCAT('/portal/customers/',customer_id) url FROM properties WHERE address1 LIKE ? OR city LIKE ? OR postal_code LIKE ? LIMIT 6",3,'fa-location-dot'],
  ];
  foreach($queries as $label=>[$sql,$count,$icon]){try{$s=$pdo->prepare($sql);$s->execute(array_fill(0,$count,$like));$rows=$s->fetchAll();if($rows)$groups[]=['label'=>$label,'icon'=>$icon,'items'=>$rows];}catch(\Throwable){} }
  echo json_encode(['results'=>$groups],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 }
}
