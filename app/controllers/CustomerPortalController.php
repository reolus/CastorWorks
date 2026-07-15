<?php
namespace App\Controllers;
use App\Core\Database; use App\Core\View;
final class CustomerPortalController
{
    private function customer(string $token): array
    {
        $s=Database::connection()->prepare('SELECT * FROM customers WHERE portal_token=? AND (portal_token_expires_at IS NULL OR portal_token_expires_at>NOW())');$s->execute([$token]);$c=$s->fetch();
        if(!$c){http_response_code(404);View::render('errors/404',['title'=>'Portal link unavailable'],'public');exit;}
        Database::connection()->prepare('INSERT INTO portal_access_logs(customer_id,action,ip_address,user_agent) VALUES(?,?,?,?)')->execute([$c['id'],'portal.view',$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
        return $c;
    }
    public function home(string $token): void
    {
        $c=$this->customer($token);$pdo=Database::connection();
        $e=$pdo->prepare('SELECT * FROM estimates WHERE customer_id=? ORDER BY created_at DESC');$e->execute([$c['id']]);
        $i=$pdo->prepare('SELECT * FROM invoices WHERE customer_id=? ORDER BY created_at DESC');$i->execute([$c['id']]);
        $j=$pdo->prepare('SELECT * FROM jobs WHERE customer_id=? ORDER BY COALESCE(scheduled_start,created_at) DESC');$j->execute([$c['id']]);
        View::render('customer/home',['title'=>'Customer Portal','customer'=>$c,'estimates'=>$e->fetchAll(),'invoices'=>$i->fetchAll(),'jobs'=>$j->fetchAll(),'token'=>$token],'public');
    }
    public function preferences(string $token): void
    {
        verify_csrf();$c=$this->customer($token);$q=Database::connection()->prepare('INSERT INTO notification_preferences(customer_id,email_estimates,email_invoices,email_appointments,email_marketing) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE email_estimates=VALUES(email_estimates),email_invoices=VALUES(email_invoices),email_appointments=VALUES(email_appointments),email_marketing=VALUES(email_marketing)');
        $q->execute([$c['id'],isset($_POST['email_estimates']),isset($_POST['email_invoices']),isset($_POST['email_appointments']),isset($_POST['email_marketing'])]);flash('success','Communication preferences updated.');redirect('/customer/'.$token);
    }
}
