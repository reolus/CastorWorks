<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class CommercialContractService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function dashboard(): array
    {
        $summary = $this->pdo->query(
            "SELECT
                COUNT(*) total,
                COALESCE(SUM(status='active'),0) active,
                COALESCE(SUM(status='draft'),0) draft,
                COALESCE(SUM(status='expired'),0) expired,
                COALESCE(SUM(status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)),0) renewals_due,
                COALESCE(SUM(status='active' AND compliance_status<>'compliant'),0) compliance_attention,
                COALESCE(SUM(status='active' AND sla_status='breached'),0) sla_breaches,
                COALESCE(SUM(CASE WHEN status='active' AND billing_frequency='monthly' THEN monthly_value WHEN status='active' THEN annual_value ELSE 0 END),0) contracted_value
             FROM commercial_contracts"
        )->fetch() ?: [];

        $contracts = $this->pdo->query(
            "SELECT cc.*,c.display_name,
                    (SELECT COUNT(*) FROM commercial_contract_locations cl WHERE cl.contract_id=cc.id AND cl.active=1) location_count,
                    (SELECT COUNT(*) FROM commercial_contract_services cs WHERE cs.contract_id=cc.id AND cs.active=1) service_count,
                    (SELECT MIN(next_service_date) FROM commercial_contract_services cs WHERE cs.contract_id=cc.id AND cs.active=1) next_service_date
             FROM commercial_contracts cc
             JOIN customers c ON c.id=cc.customer_id
             ORDER BY FIELD(cc.status,'active','draft','renewal_pending','suspended','expired','cancelled'),cc.end_date,cc.created_at DESC"
        )->fetchAll();

        $renewals = $this->pdo->query(
            "SELECT cc.id,cc.contract_number,cc.title,cc.end_date,c.display_name,DATEDIFF(cc.end_date,CURDATE()) days_remaining
             FROM commercial_contracts cc JOIN customers c ON c.id=cc.customer_id
             WHERE cc.status='active' AND cc.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 120 DAY)
             ORDER BY cc.end_date LIMIT 50"
        )->fetchAll();

        $compliance = $this->pdo->query(
            "SELECT cd.*,cc.contract_number,c.display_name
             FROM contract_compliance_documents cd
             JOIN commercial_contracts cc ON cc.id=cd.contract_id
             JOIN customers c ON c.id=cc.customer_id
             WHERE cd.status<>'current' OR (cd.expires_at IS NOT NULL AND cd.expires_at<=DATE_ADD(CURDATE(),INTERVAL 45 DAY))
             ORDER BY cd.expires_at LIMIT 50"
        )->fetchAll();

        return compact('summary','contracts','renewals','compliance');
    }

    public function createContract(array $input, int $userId): int
    {
        $customerId = (int)($input['customer_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        if ($customerId < 1 || $title === '') {
            throw new RuntimeException('Customer and contract title are required.');
        }

        $number = trim((string)($input['contract_number'] ?? '')) ?: $this->nextNumber();
        $start = $this->dateOrNull($input['start_date'] ?? null) ?? date('Y-m-d');
        $end = $this->dateOrNull($input['end_date'] ?? null);
        $annual = max(0.0, (float)($input['annual_value'] ?? 0));
        $monthly = max(0.0, (float)($input['monthly_value'] ?? ($annual > 0 ? $annual / 12 : 0)));
        $status = in_array(($input['status'] ?? 'draft'), ['draft','active','renewal_pending','suspended','expired','cancelled'], true)
            ? (string)$input['status'] : 'draft';

        $stmt = $this->pdo->prepare(
            "INSERT INTO commercial_contracts
             (contract_number,customer_id,title,description,status,start_date,end_date,auto_renew,renewal_term_months,
              notice_days,billing_frequency,billing_mode,annual_value,monthly_value,purchase_order_required,
              default_po_number,sla_status,compliance_status,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $number,$customerId,$title,trim((string)($input['description'] ?? '')),$status,$start,$end,
            !empty($input['auto_renew']) ? 1 : 0,max(1,(int)($input['renewal_term_months'] ?? 12)),
            max(0,(int)($input['notice_days'] ?? 60)),
            $input['billing_frequency'] ?? 'monthly',$input['billing_mode'] ?? 'corporate',
            $annual,$monthly,!empty($input['purchase_order_required']) ? 1 : 0,
            trim((string)($input['default_po_number'] ?? '')) ?: null,'compliant','compliant',$userId
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->snapshot($id,'created',$userId,'Initial contract version');
        $this->event($id,'contract.created','Contract created',$userId);
        return $id;
    }

    public function addLocation(int $contractId, array $input, int $userId): void
    {
        $propertyId = (int)($input['property_id'] ?? 0);
        if ($propertyId < 1) throw new RuntimeException('Property is required.');
        $stmt=$this->pdo->prepare(
            "INSERT INTO commercial_contract_locations
             (contract_id,property_id,location_code,location_name,billing_contact_name,billing_contact_email,
              site_contact_name,site_contact_email,site_contact_phone,po_number,active,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,1,?)
             ON DUPLICATE KEY UPDATE location_code=VALUES(location_code),location_name=VALUES(location_name),
             billing_contact_name=VALUES(billing_contact_name),billing_contact_email=VALUES(billing_contact_email),
             site_contact_name=VALUES(site_contact_name),site_contact_email=VALUES(site_contact_email),
             site_contact_phone=VALUES(site_contact_phone),po_number=VALUES(po_number),active=1"
        );
        $stmt->execute([$contractId,$propertyId,trim((string)($input['location_code']??''))?:null,
            trim((string)($input['location_name']??''))?:null,trim((string)($input['billing_contact_name']??''))?:null,
            trim((string)($input['billing_contact_email']??''))?:null,trim((string)($input['site_contact_name']??''))?:null,
            trim((string)($input['site_contact_email']??''))?:null,trim((string)($input['site_contact_phone']??''))?:null,
            trim((string)($input['po_number']??''))?:null,$userId]);
        $this->event($contractId,'location.added','Service location added',$userId);
    }

    public function addService(int $contractId, array $input, int $userId): void
    {
        $description=trim((string)($input['service_description']??''));
        if($description==='') throw new RuntimeException('Service description is required.');
        $frequency=in_array(($input['frequency']??'monthly'),['weekly','biweekly','monthly','quarterly','semiannual','annual','seasonal','custom'],true)
            ? (string)$input['frequency'] : 'monthly';
        $stmt=$this->pdo->prepare(
            "INSERT INTO commercial_contract_services
             (contract_id,contract_location_id,service_id,service_description,frequency,interval_value,
              next_service_date,service_window_start,service_window_end,unit_price,minimum_charge,
              estimated_duration_minutes,route_notes,active,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)"
        );
        $stmt->execute([$contractId,($input['contract_location_id']??'')!==''?(int)$input['contract_location_id']:null,
            ($input['service_id']??'')!==''?(int)$input['service_id']:null,$description,$frequency,
            max(1,(int)($input['interval_value']??1)),$this->dateOrNull($input['next_service_date']??null)??date('Y-m-d'),
            $input['service_window_start']??null,$input['service_window_end']??null,max(0,(float)($input['unit_price']??0)),
            max(0,(float)($input['minimum_charge']??0)),max(15,(int)($input['estimated_duration_minutes']??120)),
            trim((string)($input['route_notes']??''))?:null,$userId]);
        $this->event($contractId,'service.added','Recurring service added',$userId);
    }

    public function addSla(int $contractId, array $input, int $userId): void
    {
        $name=trim((string)($input['sla_name']??''));
        if($name==='') throw new RuntimeException('SLA name is required.');
        $this->pdo->prepare(
            "INSERT INTO commercial_contract_slas
             (contract_id,name,event_type,response_minutes,resolution_minutes,business_hours_only,escalation_level,active,created_by)
             VALUES (?,?,?,?,?,?,?,1,?)"
        )->execute([$contractId,$name,$input['event_type']??'service_request',max(0,(int)($input['response_minutes']??240)),
            max(0,(int)($input['resolution_minutes']??1440)),!empty($input['business_hours_only'])?1:0,
            max(0,(int)($input['escalation_level']??1)),$userId]);
        $this->event($contractId,'sla.added','SLA rule added',$userId);
    }

    public function addAmendment(int $contractId, array $input, int $userId): void
    {
        $title=trim((string)($input['amendment_title']??''));
        if($title==='') throw new RuntimeException('Amendment title is required.');
        $this->pdo->prepare(
            "INSERT INTO commercial_contract_amendments
             (contract_id,title,description,effective_date,status,created_by)
             VALUES (?,?,?,?,?,?)"
        )->execute([$contractId,$title,trim((string)($input['amendment_description']??'')),
            $this->dateOrNull($input['effective_date']??null)??date('Y-m-d'),$input['amendment_status']??'draft',$userId]);
        $this->snapshot($contractId,'amended',$userId,$title);
        $this->event($contractId,'contract.amended',$title,$userId);
    }

    public function addPurchaseOrder(int $contractId, array $input, int $userId): void
    {
        $number=trim((string)($input['po_number']??''));
        if($number==='') throw new RuntimeException('PO number is required.');
        $this->pdo->prepare(
            "INSERT INTO contract_purchase_orders
             (contract_id,contract_location_id,po_number,description,amount_limit,amount_used,starts_at,expires_at,status,created_by)
             VALUES (?,?,?,?,?,0,?,?,?,?,?)"
        )->execute([$contractId,($input['contract_location_id']??'')!==''?(int)$input['contract_location_id']:null,
            $number,trim((string)($input['po_description']??''))?:null,max(0,(float)($input['amount_limit']??0)),
            $this->dateOrNull($input['starts_at']??null),$this->dateOrNull($input['expires_at']??null),
            $input['po_status']??'active',$userId]);
        $this->event($contractId,'purchase_order.added','Purchase order '.$number.' added',$userId);
    }

    public function updateStatus(int $contractId, string $status, int $userId): void
    {
        if(!in_array($status,['draft','active','renewal_pending','suspended','expired','cancelled'],true)) {
            throw new RuntimeException('Invalid contract status.');
        }
        $this->pdo->prepare('UPDATE commercial_contracts SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$contractId]);
        $this->snapshot($contractId,'status_changed',$userId,'Status changed to '.$status);
        $this->event($contractId,'contract.status','Status changed to '.$status,$userId);
    }

    public function generateDueJobs(?int $contractId=null, int $limit=100): array
    {
        $where="cc.status='active' AND cs.active=1 AND cs.next_service_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)";
        $params=[];
        if($contractId){$where.=' AND cc.id=?';$params[]=$contractId;}
        $stmt=$this->pdo->prepare(
            "SELECT cs.*,cc.customer_id,cc.contract_number,cl.property_id,cl.po_number location_po,cc.default_po_number
             FROM commercial_contract_services cs
             JOIN commercial_contracts cc ON cc.id=cs.contract_id
             LEFT JOIN commercial_contract_locations cl ON cl.id=cs.contract_location_id
             WHERE {$where}
             ORDER BY cs.next_service_date LIMIT {$limit}"
        );
        $stmt->execute($params);
        $created=0;$skipped=0;$errors=[];
        foreach($stmt->fetchAll() as $service){
            try{
                $runKey=$service['id'].':'.$service['next_service_date'];
                $exists=$this->pdo->prepare('SELECT id FROM contract_recurring_job_runs WHERE run_key=?');$exists->execute([$runKey]);
                if($exists->fetchColumn()){$skipped++;continue;}
                $jobNumber='JOB-'.date('ymd').'-'.str_pad((string)$service['id'],4,'0',STR_PAD_LEFT);
                $insert=$this->pdo->prepare(
                    "INSERT INTO jobs(job_number,customer_id,property_id,service_summary,status,route_date,scheduled_start,scheduled_end,notes)
                     VALUES (?,?,?,?, 'scheduled', ?, ?, ?, ?)"
                );
                $date=$service['next_service_date'];
                $start=$date.' '.($service['service_window_start']?:'08:00:00');
                $end=date('Y-m-d H:i:s',strtotime($start.' +'.max(15,(int)$service['estimated_duration_minutes']).' minutes'));
                $notes='Commercial contract '.$service['contract_number'].'. PO: '.($service['location_po']?:$service['default_po_number']?:'Not required').'. '.($service['route_notes']??'');
                $insert->execute([$jobNumber,$service['customer_id'],$service['property_id'],$service['service_description'],$date,$start,$end,$notes]);
                $jobId=(int)$this->pdo->lastInsertId();
                $this->pdo->prepare('INSERT INTO contract_recurring_job_runs(run_key,contract_id,contract_service_id,job_id,scheduled_for,status) VALUES(?,?,?,?,?,\'created\')')->execute([$runKey,$service['contract_id'],$service['id'],$jobId,$date]);
                $next=$this->nextOccurrence($date,$service['frequency'],(int)$service['interval_value']);
                $this->pdo->prepare('UPDATE commercial_contract_services SET last_generated_at=NOW(),next_service_date=? WHERE id=?')->execute([$next,$service['id']]);
                $created++;
            }catch(Throwable $e){$errors[]='Service '.$service['id'].': '.$e->getMessage();}
        }
        return compact('created','skipped','errors');
    }

    public function generateDueInvoices(?int $contractId=null, int $limit=100): array
    {
        $where="cc.status='active' AND cc.next_invoice_date IS NOT NULL AND cc.next_invoice_date<=CURDATE()";
        $params=[];if($contractId){$where.=' AND cc.id=?';$params[]=$contractId;}
        $stmt=$this->pdo->prepare("SELECT cc.*,c.display_name FROM commercial_contracts cc JOIN customers c ON c.id=cc.customer_id WHERE {$where} ORDER BY cc.next_invoice_date LIMIT {$limit}");
        $stmt->execute($params);$created=0;$skipped=0;$errors=[];
        foreach($stmt->fetchAll() as $contract){
            try{
                $runKey=$contract['id'].':'.$contract['next_invoice_date'];
                $q=$this->pdo->prepare('SELECT id FROM contract_recurring_invoice_runs WHERE run_key=?');$q->execute([$runKey]);
                if($q->fetchColumn()){$skipped++;continue;}
                $amount=$contract['billing_frequency']==='annual'?(float)$contract['annual_value']:(float)$contract['monthly_value'];
                $invoiceNumber='INV-'.date('ymd').'-C'.str_pad((string)$contract['id'],4,'0',STR_PAD_LEFT);
                $due=date('Y-m-d',strtotime($contract['next_invoice_date'].' +30 days'));
                $this->pdo->prepare(
                    "INSERT INTO invoices(invoice_number,customer_id,issue_date,due_date,status,subtotal,tax_amount,total,balance_due,notes)
                     VALUES (?,?,?,?,'draft',?,0,?,?,?)"
                )->execute([$invoiceNumber,$contract['customer_id'],$contract['next_invoice_date'],$due,$amount,$amount,$amount,'Commercial contract '.$contract['contract_number']]);
                $invoiceId=(int)$this->pdo->lastInsertId();
                $this->pdo->prepare('INSERT INTO contract_recurring_invoice_runs(run_key,contract_id,invoice_id,billing_date,amount,status) VALUES(?,?,?,?,?,\'created\')')->execute([$runKey,$contract['id'],$invoiceId,$contract['next_invoice_date'],$amount]);
                $next=$this->nextInvoiceDate($contract['next_invoice_date'],$contract['billing_frequency']);
                $this->pdo->prepare('UPDATE commercial_contracts SET last_invoice_generated_at=NOW(),next_invoice_date=? WHERE id=?')->execute([$next,$contract['id']]);
                $created++;
            }catch(Throwable $e){$errors[]='Contract '.$contract['id'].': '.$e->getMessage();}
        }
        return compact('created','skipped','errors');
    }

    public function monitorRenewals(): array
    {
        $stmt=$this->pdo->query("SELECT id,contract_number,end_date,auto_renew,renewal_term_months,notice_days FROM commercial_contracts WHERE status='active' AND end_date IS NOT NULL AND end_date<=DATE_ADD(CURDATE(),INTERVAL notice_days DAY)");
        $pending=0;$renewed=0;
        foreach($stmt->fetchAll() as $row){
            if((int)$row['auto_renew']===1 && $row['end_date']<=date('Y-m-d')){
                $newEnd=date('Y-m-d',strtotime($row['end_date'].' +'.max(1,(int)$row['renewal_term_months']).' months'));
                $this->pdo->prepare("UPDATE commercial_contracts SET end_date=?,status='active',updated_at=NOW() WHERE id=?")->execute([$newEnd,$row['id']]);$renewed++;
            } else {
                $this->pdo->prepare("UPDATE commercial_contracts SET status='renewal_pending' WHERE id=? AND status='active'")->execute([$row['id']]);$pending++;
            }
        }
        return compact('pending','renewed');
    }

    public function monitorSlas(): array
    {
        $this->pdo->exec("UPDATE commercial_contracts cc SET sla_status='breached' WHERE EXISTS(SELECT 1 FROM jobs j WHERE j.customer_id=cc.customer_id AND j.status NOT IN ('completed','cancelled') AND j.scheduled_end IS NOT NULL AND j.scheduled_end<NOW()) AND cc.status='active'");
        $breached=(int)$this->pdo->query("SELECT COUNT(*) FROM commercial_contracts WHERE status='active' AND sla_status='breached'")->fetchColumn();
        return ['breached'=>$breached];
    }

    private function nextNumber(): string
    {
        $next=(int)$this->pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM commercial_contracts')->fetchColumn();
        return 'CTR-'.date('Y').'-'.str_pad((string)$next,5,'0',STR_PAD_LEFT);
    }

    private function snapshot(int $contractId,string $changeType,int $userId,string $summary):void
    {
        $q=$this->pdo->prepare('SELECT * FROM commercial_contracts WHERE id=?');$q->execute([$contractId]);$row=$q->fetch();if(!$row)return;
        $version=(int)$this->pdo->query('SELECT COALESCE(MAX(version_number),0)+1 FROM commercial_contract_versions WHERE contract_id='.(int)$contractId)->fetchColumn();
        $this->pdo->prepare('INSERT INTO commercial_contract_versions(contract_id,version_number,change_type,change_summary,snapshot_json,created_by) VALUES(?,?,?,?,?,?)')->execute([$contractId,$version,$changeType,$summary,json_encode($row,JSON_UNESCAPED_SLASHES),$userId]);
    }

    private function event(int $contractId,string $key,string $message,int $userId):void
    {
        $this->pdo->prepare('INSERT INTO commercial_contract_events(contract_id,event_key,message,created_by) VALUES(?,?,?,?)')->execute([$contractId,$key,$message,$userId]);
    }

    private function dateOrNull(mixed $value):?string
    {
        $value=trim((string)$value);if($value==='')return null;$time=strtotime($value);return $time===false?null:date('Y-m-d',$time);
    }

    private function nextOccurrence(string $date,string $frequency,int $interval):string
    {
        $interval=max(1,$interval);$spec=match($frequency){'weekly'=>"+{$interval} week",'biweekly'=>'+2 weeks','quarterly'=>"+".(3*$interval).' months','semiannual'=>"+".(6*$interval).' months','annual'=>"+{$interval} year",'seasonal'=>'+6 months','custom'=>"+{$interval} days",default=>"+{$interval} month"};
        return date('Y-m-d',strtotime($date.' '.$spec));
    }

    private function nextInvoiceDate(string $date,string $frequency):string
    {
        $spec=match($frequency){'quarterly'=>'+3 months','semiannual'=>'+6 months','annual'=>'+1 year',default=>'+1 month'};
        return date('Y-m-d',strtotime($date.' '.$spec));
    }
}
