<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\CommercialContractService;
use App\Services\DocumentService;
use App\Services\EmailService;
use Throwable;

final class AgreementController
{
    public function index(): void
    {
        Auth::requireRole('owner','office');
        $pdo=Database::connection();
        $service=new CommercialContractService($pdo);
        $data=$service->dashboard();
        $data['title']='Commercial Contracts';
        $data['customers']=$pdo->query('SELECT id,display_name FROM customers ORDER BY display_name')->fetchAll();
        $data['properties']=$pdo->query("SELECT p.id,p.customer_id,CONCAT_WS(', ',p.address_line1,p.city,p.state,p.postal_code) address FROM properties p ORDER BY p.city,p.address_line1")->fetchAll();
        $data['services']=$pdo->query('SELECT id,name FROM services WHERE active=1 ORDER BY name')->fetchAll();
        $data['locations']=$pdo->query("SELECT cl.id,cl.contract_id,COALESCE(cl.location_name,CONCAT_WS(', ',p.address_line1,p.city,p.state)) label FROM commercial_contract_locations cl JOIN properties p ON p.id=cl.property_id WHERE cl.active=1 ORDER BY label")->fetchAll();
        View::render('portal/agreements/index',$data,'portal');
    }

    public function store(): void
    {
        Auth::requireRole('owner','office');verify_csrf();
        $service=new CommercialContractService();
        $action=(string)($_POST['action']??'create_legacy');
        try{
            switch($action){
                case 'create_contract':$id=$service->createContract($_POST,Auth::id());flash('success','Commercial contract created.');break;
                case 'add_location':$service->addLocation((int)$_POST['contract_id'],$_POST,Auth::id());flash('success','Contract location saved.');break;
                case 'add_service':$service->addService((int)$_POST['contract_id'],$_POST,Auth::id());flash('success','Recurring service saved.');break;
                case 'add_sla':$service->addSla((int)$_POST['contract_id'],$_POST,Auth::id());flash('success','SLA rule saved.');break;
                case 'add_amendment':$service->addAmendment((int)$_POST['contract_id'],$_POST,Auth::id());flash('success','Contract amendment saved.');break;
                case 'add_po':$service->addPurchaseOrder((int)$_POST['contract_id'],$_POST,Auth::id());flash('success','Purchase order saved.');break;
                case 'update_status':$service->updateStatus((int)$_POST['contract_id'],(string)$_POST['status'],Auth::id());flash('success','Contract status updated.');break;
                case 'generate_jobs':$r=$service->generateDueJobs(($_POST['contract_id']??'')!==''?(int)$_POST['contract_id']:null);flash('success',$r['created'].' recurring job(s) generated.');break;
                case 'generate_invoices':$r=$service->generateDueInvoices(($_POST['contract_id']??'')!==''?(int)$_POST['contract_id']:null);flash('success',$r['created'].' recurring invoice(s) generated.');break;
                default:$this->createLegacyAgreement();return;
            }
        }catch(Throwable $e){flash('danger',$e->getMessage());}
        redirect('/portal/agreements');
    }

    private function createLegacyAgreement():void
    {
        $pdo=Database::connection();$num=next_number('AGR-');$token=public_token();$body=trim($_POST['body']??'');
        if($body==='')$body='CastorWorks service agreement. Customer will provide safe access, disclose hazards, secure pets, and pay according to stated terms.';
        $pdo->prepare("INSERT INTO service_agreements(agreement_number,customer_id,property_id,title,agreement_type,body,status,public_token,effective_date,expiration_date,created_by) VALUES(?,?,?,?,?,?,'sent',?,?,?,?,?)")
            ->execute([$num,(int)$_POST['customer_id'],($_POST['property_id']??'')?:null,trim($_POST['title']),$_POST['agreement_type']??'commercial',$body,$token,($_POST['effective_date']??'')?:null,($_POST['expiration_date']??'')?:null,Auth::id()]);
        AuditService::log('agreement.created','service_agreement',(int)$pdo->lastInsertId());flash('success','Agreement created and ready for signature.');redirect('/portal/agreements');
    }

    public function send(string $id):void
    {
        Auth::requireRole('owner','office');verify_csrf();$pdo=Database::connection();
        $q=$pdo->prepare('SELECT a.*,c.display_name,c.email FROM service_agreements a JOIN customers c ON c.id=a.customer_id WHERE a.id=?');$q->execute([(int)$id]);$a=$q->fetch();
        if(!$a||!$a['email']){flash('danger','Agreement or customer email not found.');redirect('/portal/agreements');}
        try{$url=rtrim((string)\App\Core\Env::get('APP_URL',''),'/').'/agreement/'.$a['public_token'];$message=(new EmailService())->sendMail($a['email'],'Your CastorWorks service agreement '.$a['agreement_number'],'<p>Hello '.htmlspecialchars($a['display_name']).',</p><p>Your service agreement is ready for review and signature.</p><p><a href="'.$url.'">Review and sign agreement</a></p>');$pdo->prepare('UPDATE service_agreements SET delivered_at=NOW(),graph_message_id=? WHERE id=?')->execute([$message['message_id']??null,$a['id']]);AuditService::log('agreement.sent','service_agreement',(int)$id);flash('success','Agreement delivered.');}catch(Throwable $e){flash('danger',$e->getMessage());}
        redirect('/portal/agreements');
    }

    public function publicShow(string $token):void
    {
        $pdo=Database::connection();$q=$pdo->prepare('SELECT a.*,c.display_name,c.email FROM service_agreements a JOIN customers c ON c.id=a.customer_id WHERE a.public_token=?');$q->execute([$token]);$agreement=$q->fetch();if(!$agreement){http_response_code(404);exit('Agreement not found.');}if($agreement['status']==='sent')$pdo->prepare("UPDATE service_agreements SET status='viewed' WHERE id=?")->execute([$agreement['id']]);View::render('customer/agreement',['title'=>$agreement['title'],'agreement'=>$agreement,'token'=>$token],'public');
    }

    public function sign(string $token):void
    {
        verify_csrf();$pdo=Database::connection();$q=$pdo->prepare('SELECT id,status FROM service_agreements WHERE public_token=?');$q->execute([$token]);$a=$q->fetch();if(!$a||in_array($a['status'],['signed','cancelled','expired'],true)){http_response_code(409);exit('Agreement cannot be signed.');}$pdo->prepare("UPDATE service_agreements SET status='signed',signed_name=?,signed_email=?,signature_data=?,signed_ip=?,signed_at=NOW() WHERE id=?")->execute([trim($_POST['signed_name']),trim($_POST['signed_email']),$_POST['signature_data']??null,$_SERVER['REMOTE_ADDR']??null,$a['id']]);try{$doc=(new DocumentService())->generate('agreement',(int)$a['id']);$pdo->prepare('UPDATE service_agreements SET document_id=? WHERE id=?')->execute([$doc['id'],$a['id']]);}catch(Throwable){}View::render('customer/agreement-signed',['title'=>'Agreement Signed'],'public');
    }
}
