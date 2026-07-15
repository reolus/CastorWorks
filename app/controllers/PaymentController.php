<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\PaymentService;
use Throwable;

final class PaymentController
{
    public function checkout(string $token): void
    {
        verify_csrf();$pdo=Database::connection();$s=$pdo->prepare('SELECT i.*,c.display_name,c.email FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.public_token=?');$s->execute([$token]);$invoice=$s->fetch();
        if(!$invoice||$invoice['status']==='paid'||(float)$invoice['balance_due']<=0){flash('danger','This invoice does not have an outstanding balance.');redirect('/invoice/'.$token);}
        try{$session=(new PaymentService())->createCheckout($invoice);$q=$pdo->prepare('INSERT INTO payment_sessions(invoice_id,provider,provider_session_id,checkout_url,amount,currency,status,expires_at) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$invoice['id'],'stripe',$session['id'],$session['url'],$session['amount'],$session['currency'],'open',$session['expires_at']]);AuditService::log('payment.checkout_created','invoice',(int)$invoice['id'],['session'=>$session['id']]);header('Location: '.$session['url']);exit;}catch(Throwable $e){flash('danger','Online payment is unavailable: '.$e->getMessage());redirect('/invoice/'.$token);}
    }

    public function success(): void { View::render('public/payment-success',['title'=>'Payment received'],'public'); }
    public function cancel(): void { View::render('public/payment-cancel',['title'=>'Payment cancelled'],'public'); }

    public function webhook(): void
    {
        $payload=(string)file_get_contents('php://input');$signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');$pdo=Database::connection();
        try{$event=(new PaymentService())->verifyWebhook($payload,$signature);$eventId=(string)($event['id']??hash('sha256',$payload));$type=(string)($event['type']??'unknown');$pdo->prepare('INSERT IGNORE INTO webhook_events(provider,provider_event_id,event_type,payload) VALUES(?,?,?,?)')->execute(['stripe',$eventId,$type,$payload]);
            if($type==='checkout.session.completed'){$obj=$event['data']['object']??[];$invoiceId=(int)($obj['metadata']['invoice_id']??$obj['client_reference_id']??0);$sessionId=(string)($obj['id']??'');$amount=((int)($obj['amount_total']??0))/100;if($invoiceId>0){$pdo->beginTransaction();$exists=$pdo->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id=? AND method='card' AND reference=?");$exists->execute([$invoiceId,$sessionId]);if((int)$exists->fetchColumn()===0){$pdo->prepare("INSERT INTO payments(invoice_id,amount,method,reference) VALUES(?,?,'card',?)")->execute([$invoiceId,$amount,$sessionId]);}$s=$pdo->prepare('SELECT total,COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id=?),0) paid FROM invoices WHERE id=?');$s->execute([$invoiceId,$invoiceId]);$i=$s->fetch();$balance=max(0,(float)$i['total']-(float)$i['paid']);$pdo->prepare('UPDATE invoices SET balance_due=?,status=? WHERE id=?')->execute([$balance,$balance<=0?'paid':'sent',$invoiceId]);$pdo->prepare("UPDATE payment_sessions SET status='paid' WHERE provider_session_id=?")->execute([$sessionId]);$pdo->commit();}}
            $pdo->prepare("UPDATE webhook_events SET status='processed',processed_at=NOW() WHERE provider='stripe' AND provider_event_id=?")->execute([$eventId]);http_response_code(200);echo 'ok';
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(400);echo 'invalid';}
    }
}
