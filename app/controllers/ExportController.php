<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

final class ExportController
{
    public function customers(): void { Auth::requireRole('office','owner');$this->csv('customers.csv',Database::connection()->query('SELECT id,customer_type,display_name,contact_name,email,phone,status,created_at FROM customers ORDER BY display_name')->fetchAll()); }
    public function invoices(): void { Auth::requireRole('office','owner');$this->csv('invoices.csv',Database::connection()->query('SELECT i.invoice_number,c.display_name,i.status,i.issue_date,i.due_date,i.total,i.balance_due FROM invoices i JOIN customers c ON c.id=i.customer_id ORDER BY i.issue_date DESC')->fetchAll()); }
    public function jobs(): void { Auth::requireRole('office','crew_leader','owner');$this->csv('jobs.csv',Database::connection()->query('SELECT j.job_number,c.display_name,j.service_summary,j.status,j.scheduled_start,j.scheduled_end FROM jobs j JOIN customers c ON c.id=j.customer_id ORDER BY j.created_at DESC')->fetchAll()); }
    public function revenue(): void { Auth::requireRole('office','owner');$from=$_GET['from']??date('Y-01-01');$to=$_GET['to']??date('Y-m-d');$s=Database::connection()->prepare('SELECT DATE(received_at) payment_date,method,reference,amount FROM payments WHERE DATE(received_at) BETWEEN ? AND ? ORDER BY received_at');$s->execute([$from,$to]);$this->csv('revenue-'.$from.'-'.$to.'.csv',$s->fetchAll()); }
    private function csv(string $filename,array $rows): never { header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');$out=fopen('php://output','w');if($rows){fputcsv($out,array_keys($rows[0]));foreach($rows as $row)fputcsv($out,$row);}fclose($out);exit; }
}
