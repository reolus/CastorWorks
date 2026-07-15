<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use PDO;
use Throwable;

final class CustomerAccountController
{
    private function requireAccount(): array
    {
        if (empty($_SESSION['customer_account'])) {
            redirect('/customer-login');
        }
        return $_SESSION['customer_account'];
    }

    public function loginForm(): void { View::render('customer/login', ['title' => 'Customer sign in'], 'auth'); }

    public function login(): void
    {
        verify_csrf();
        $pdo = Database::connection();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $q = $pdo->prepare('SELECT ca.*, c.display_name, c.contact_name FROM customer_accounts ca JOIN customers c ON c.id=ca.customer_id WHERE LOWER(ca.email)=? LIMIT 1');
        $q->execute([$email]);
        $a = $q->fetch();
        if (!$a || $a['status'] !== 'active' || ($a['locked_until'] && strtotime($a['locked_until']) > time()) || !password_verify((string)($_POST['password'] ?? ''), $a['password_hash'])) {
            if ($a) {
                $n = (int)$a['failed_attempts'] + 1;
                $pdo->prepare('UPDATE customer_accounts SET failed_attempts=?, locked_until=? WHERE id=?')->execute([$n, $n >= 5 ? date('Y-m-d H:i:s', time() + 900) : null, $a['id']]);
            }
            flash('danger', 'Invalid email or password.');
            redirect('/customer-login');
        }
        session_regenerate_id(true);
        $_SESSION['customer_account'] = ['id'=>(int)$a['id'], 'customer_id'=>(int)$a['customer_id'], 'name'=>$a['contact_name'] ?: $a['display_name'], 'email'=>$a['email']];
        $pdo->prepare('UPDATE customer_accounts SET failed_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')->execute([$a['id']]);
        redirect('/account');
    }

    public function registerForm(): void { View::render('customer/register', ['title' => 'Create customer account'], 'auth'); }

    public function register(): void
    {
        verify_csrf();
        $pdo = Database::connection();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $q = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email)=? LIMIT 1');
        $q->execute([$email]);
        $cid = $q->fetchColumn();
        if (!$cid) { flash('danger', 'No customer record matches that email. Contact the office first.'); redirect('/customer-register'); }
        if (strlen((string)($_POST['password'] ?? '')) < 10) { flash('danger', 'Password must be at least 10 characters.'); redirect('/customer-register'); }
        try {
            $pdo->prepare('INSERT INTO customer_accounts(customer_id,email,password_hash,email_verified_at) VALUES(?,?,?,NOW())')->execute([(int)$cid,$email,password_hash((string)$_POST['password'],PASSWORD_DEFAULT)]);
        } catch (Throwable) { flash('danger','An account already exists for that email.'); redirect('/customer-login'); }
        flash('success','Account created. Sign in to continue.'); redirect('/customer-login');
    }

    public function home(): void
    {
        $account = $this->requireAccount();
        $pdo = Database::connection();
        $cid = (int)$account['customer_id'];
        $customer = $this->one($pdo, 'SELECT * FROM customers WHERE id=?', [$cid]);
        $data = [
            'title'=>'My account', 'customer'=>$customer,
            'properties'=>$this->all($pdo,'SELECT * FROM properties WHERE customer_id=? ORDER BY label,address1',[$cid]),
            'estimates'=>$this->all($pdo,'SELECT * FROM estimates WHERE customer_id=? ORDER BY created_at DESC LIMIT 30',[$cid]),
            'invoices'=>$this->all($pdo,'SELECT * FROM invoices WHERE customer_id=? ORDER BY created_at DESC LIMIT 30',[$cid]),
            'jobs'=>$this->all($pdo,'SELECT * FROM jobs WHERE customer_id=? ORDER BY COALESCE(scheduled_start,created_at) DESC LIMIT 30',[$cid]),
            'recurring'=>$this->all($pdo,'SELECT rs.*,s.name service_name,p.label property_label FROM recurring_services rs LEFT JOIN services s ON s.id=rs.service_id LEFT JOIN properties p ON p.id=rs.property_id WHERE rs.customer_id=? ORDER BY rs.active DESC,rs.next_service_date',[$cid]),
            'agreements'=>$this->all($pdo,'SELECT * FROM service_agreements WHERE customer_id=? ORDER BY created_at DESC LIMIT 20',[$cid]),
            'requests'=>$this->all($pdo,'SELECT csr.*,s.name service_name,p.label property_label FROM customer_service_requests csr LEFT JOIN services s ON s.id=csr.service_id LEFT JOIN properties p ON p.id=csr.property_id WHERE csr.customer_id=? ORDER BY csr.created_at DESC LIMIT 20',[$cid]),
            'services'=>$this->all($pdo,'SELECT * FROM services WHERE active=1 ORDER BY category,name'),
            'threads'=>$this->all($pdo,"SELECT * FROM conversation_threads WHERE customer_id=? AND status<>'closed' ORDER BY COALESCE(last_message_at,created_at) DESC",[$cid]),
            'preferences'=>$this->one($pdo,'SELECT * FROM notification_preferences WHERE customer_id=?',[$cid]),
        ];
        View::render('customer/account',$data,'public');
    }

    public function requestService(): void
    {
        $account = $this->requireAccount(); verify_csrf(); $pdo=Database::connection(); $cid=(int)$account['customer_id'];
        $subject=trim((string)($_POST['subject']??''));
        if($subject===''){flash('danger','Please describe the service you need.');redirect('/account#request-service');}
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('INSERT INTO customer_service_requests(customer_id,property_id,service_id,subject,details,preferred_date,alternate_date,preferred_window) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$cid,$this->nullableInt($_POST['property_id']??null),$this->nullableInt($_POST['service_id']??null),$subject,trim((string)($_POST['details']??'')) ?: null,$_POST['preferred_date']?:null,$_POST['alternate_date']?:null,$_POST['preferred_window']??'any']);
            $id=(int)$pdo->lastInsertId();
            $this->storePhotos($pdo,$id);
            $pdo->commit(); flash('success','Your service request was submitted. We will follow up shortly.');
        } catch(Throwable $e){$pdo->rollBack();flash('danger','Unable to submit the service request.');}
        redirect('/account#requests');
    }

    public function preferences(): void
    {
        $account=$this->requireAccount(); verify_csrf(); $cid=(int)$account['customer_id'];
        Database::connection()->prepare('INSERT INTO notification_preferences(customer_id,email_estimates,email_invoices,email_appointments,email_marketing,sms_appointments,sms_invoices,sms_marketing) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE email_estimates=VALUES(email_estimates),email_invoices=VALUES(email_invoices),email_appointments=VALUES(email_appointments),email_marketing=VALUES(email_marketing),sms_appointments=VALUES(sms_appointments),sms_invoices=VALUES(sms_invoices),sms_marketing=VALUES(sms_marketing)')->execute([$cid,isset($_POST['email_estimates']),isset($_POST['email_invoices']),isset($_POST['email_appointments']),isset($_POST['email_marketing']),isset($_POST['sms_appointments']),isset($_POST['sms_invoices']),isset($_POST['sms_marketing'])]);
        flash('success','Communication preferences updated.'); redirect('/account#preferences');
    }

    public function referral(): void
    {
        $a=$this->requireAccount();verify_csrf();$name=trim((string)($_POST['name']??''));
        if($name===''){flash('danger','Referral name is required.');redirect('/account#referrals');}
        Database::connection()->prepare('INSERT INTO customer_referrals(referring_customer_id,referred_name,referred_email,referred_phone,notes) VALUES(?,?,?,?,?)')->execute([(int)$a['customer_id'],$name,trim((string)($_POST['email']??''))?:null,trim((string)($_POST['phone']??''))?:null,trim((string)($_POST['notes']??''))?:null]);
        flash('success','Thank you for the referral.');redirect('/account#referrals');
    }

    public function survey(): void
    {
        $a=$this->requireAccount();verify_csrf();$rating=max(1,min(5,(int)($_POST['rating']??0)));
        if($rating<1){flash('danger','Please choose a rating.');redirect('/account#feedback');}
        Database::connection()->prepare('INSERT INTO customer_satisfaction_surveys(customer_id,job_id,rating,recommend_score,comments,permission_to_contact) VALUES(?,?,?,?,?,?)')->execute([(int)$a['customer_id'],$this->nullableInt($_POST['job_id']??null),$rating,isset($_POST['recommend_score'])?(int)$_POST['recommend_score']:null,trim((string)($_POST['comments']??''))?:null,isset($_POST['permission_to_contact'])]);
        flash('success','Thank you for your feedback.');redirect('/account#feedback');
    }

    public function replyConversation(int $id): void { $a=$this->requireAccount();verify_csrf();$pdo=Database::connection();$q=$pdo->prepare('SELECT id FROM conversation_threads WHERE id=? AND customer_id=?');$q->execute([$id,(int)$a['customer_id']]);if(!$q->fetchColumn()){http_response_code(404);exit('Conversation not found');}$body=trim((string)($_POST['body']??''));if($body===''){flash('danger','Message cannot be empty.');redirect('/account');}$pdo->prepare("INSERT INTO conversation_messages(conversation_thread_id,sender_type,channel,body,is_internal) VALUES(?,'customer','portal',?,0)")->execute([$id,$body]);$pdo->prepare("UPDATE conversation_threads SET status='waiting_staff',last_message_at=NOW() WHERE id=?")->execute([$id]);flash('success','Your message was sent.');redirect('/account#messages'); }
    public function createConversation(): void { $a=$this->requireAccount();verify_csrf();$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));if($subject===''||$body===''){flash('danger','Subject and message are required.');redirect('/account#messages');}$pdo=Database::connection();$pdo->beginTransaction();try{$pdo->prepare("INSERT INTO conversation_threads(customer_id,subject,priority,status,created_by,last_message_at) VALUES(?,?,'normal','waiting_staff',NULL,NOW())")->execute([(int)$a['customer_id'],$subject]);$id=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO conversation_messages(conversation_thread_id,sender_type,channel,body,is_internal) VALUES(?,'customer','portal',?,0)")->execute([$id,$body]);$pdo->commit();flash('success','Your conversation was started.');}catch(Throwable){$pdo->rollBack();flash('danger','Unable to start conversation.');}redirect('/account#messages'); }
    public function logout(): void { verify_csrf();unset($_SESSION['customer_account']);redirect('/'); }

    private function one(PDO $pdo,string $sql,array $args=[]):array|false{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetch();}
    private function all(PDO $pdo,string $sql,array $args=[]):array{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchAll();}
    private function nullableInt(mixed $v):?int{return ($v!==null&&$v!=='')?(int)$v:null;}
    private function storePhotos(PDO $pdo,int $requestId):void
    {
        if(empty($_FILES['photos']['name'])||!is_array($_FILES['photos']['name']))return;
        $base=dirname(__DIR__,2).'/storage/customer-requests/'.$requestId;if(!is_dir($base))mkdir($base,0770,true);
        $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$finfo=new \finfo(FILEINFO_MIME_TYPE);
        foreach($_FILES['photos']['name'] as $i=>$name){if(($_FILES['photos']['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;$tmp=$_FILES['photos']['tmp_name'][$i];$size=(int)$_FILES['photos']['size'][$i];if($size>10*1024*1024)continue;$mime=$finfo->file($tmp);if(!isset($allowed[$mime]))continue;$stored=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(move_uploaded_file($tmp,$base.'/'.$stored)){$pdo->prepare('INSERT INTO customer_service_request_photos(service_request_id,original_name,stored_name,mime_type,size_bytes) VALUES(?,?,?,?,?)')->execute([$requestId,substr((string)$name,0,255),$stored,$mime,$size]);}}
    }
}
