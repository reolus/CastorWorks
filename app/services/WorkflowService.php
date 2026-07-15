<?php
namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class WorkflowService
{
    public static function catalog(): array
    {
        return [
            'events'=>['lead.created'=>'New lead','estimate.created'=>'Estimate created','estimate.accepted'=>'Estimate accepted','job.scheduled'=>'Job scheduled','job.completed'=>'Job completed','invoice.created'=>'Invoice created','invoice.overdue'=>'Invoice overdue','payment.received'=>'Payment received','customer.message'=>'Customer message','recurring.due'=>'Recurring service due'],
            'actions'=>['send_email'=>'Send email','send_sms'=>'Send SMS','notify_teams'=>'Notify Teams','portal_notification'=>'Portal notification','update_status'=>'Update entity status','delay'=>'Wait / delay'],
            'conditions'=>['always'=>'Always','field_equals'=>'Field equals','field_not_equals'=>'Field does not equal','field_gte'=>'Field is at least','field_lte'=>'Field is at most','field_contains'=>'Field contains'],
        ];
    }

    public static function templates(): array
    {
        return [
            'estimate_followup'=>['name'=>'Estimate follow-up','event_key'=>'estimate.created','description'=>'Email the customer after an estimate is created.','steps'=>[['action_key'=>'delay','config'=>['minutes'=>2880]],['action_key'=>'send_email','config'=>['recipient'=>'{{customer.email}}','subject'=>'Your Rock Bluffs estimate','message'=>'Your estimate is ready. Please contact us with any questions.']]]],
            'appointment_reminder'=>['name'=>'Appointment reminder','event_key'=>'job.scheduled','description'=>'Send an appointment reminder before a scheduled job.','steps'=>[['action_key'=>'delay','config'=>['minutes'=>60]],['action_key'=>'send_sms','config'=>['recipient'=>'{{customer.phone}}','message'=>'Reminder: Rock Bluffs Exterior Services has your appointment scheduled.']]]],
            'overdue_collection'=>['name'=>'Overdue invoice escalation','event_key'=>'invoice.overdue','description'=>'Notify the customer and office about an overdue invoice.','steps'=>[['action_key'=>'send_email','config'=>['recipient'=>'{{customer.email}}','subject'=>'Invoice reminder','message'=>'Your invoice remains unpaid. Please review your customer portal.']],['action_key'=>'notify_teams','config'=>['title'=>'Overdue invoice','message'=>'Invoice {{invoice.invoice_number}} is overdue.']]]],
            'review_request'=>['name'=>'Post-service review request','event_key'=>'job.completed','description'=>'Ask the customer for feedback after completion.','steps'=>[['action_key'=>'delay','config'=>['minutes'=>1440]],['action_key'=>'send_email','config'=>['recipient'=>'{{customer.email}}','subject'=>'How did we do?','message'=>'Thank you for choosing Rock Bluffs Exterior Services. We would appreciate your feedback.']]]],
        ];
    }

    public static function saveWorkflow(?int $id,array $input,?int $userId): int
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $name=trim((string)($input['name']??''));$event=trim((string)($input['event_key']??''));
            if($name===''||$event==='')throw new RuntimeException('Workflow name and trigger are required.');
            $active=isset($input['active'])?1:0;$priority=(int)($input['priority']??100);$description=trim((string)($input['description']??''));
            if($id===null){$s=$pdo->prepare('INSERT INTO workflow_rules(name,event_key,description,priority,active,created_by,version) VALUES(?,?,?,?,?,?,1)');$s->execute([$name,$event,$description,$priority,$active,$userId]);$id=(int)$pdo->lastInsertId();}
            else{$s=$pdo->prepare('UPDATE workflow_rules SET name=?,event_key=?,description=?,priority=?,active=?,version=version+1 WHERE id=?');$s->execute([$name,$event,$description,$priority,$active,$id]);$pdo->prepare('DELETE FROM workflow_steps WHERE workflow_rule_id=?')->execute([$id]);}
            $actions=$input['step_action']??[];$conditions=$input['step_condition']??[];$fields=$input['step_field']??[];$values=$input['step_value']??[];$configs=$input['step_config']??[];
            $insert=$pdo->prepare('INSERT INTO workflow_steps(workflow_rule_id,sort_order,action_key,condition_key,condition_field,condition_value,config_json) VALUES(?,?,?,?,?,?,?)');
            foreach($actions as $i=>$action){$action=trim((string)$action);if($action==='')continue;$cfg=json_decode((string)($configs[$i]??'{}'),true);if(!is_array($cfg))throw new RuntimeException('Step '.($i+1).' contains invalid JSON.');$insert->execute([$id,$i+1,$action,(string)($conditions[$i]??'always'),trim((string)($fields[$i]??'')),trim((string)($values[$i]??'')),json_encode($cfg,JSON_UNESCAPED_SLASHES)]);}
            $version=(int)$pdo->query('SELECT version FROM workflow_rules WHERE id='.(int)$id)->fetchColumn();
            $snapshot=self::snapshot($id,$pdo);$pdo->prepare('INSERT INTO workflow_versions(workflow_rule_id,version,snapshot_json,created_by) VALUES(?,?,?,?)')->execute([$id,$version,json_encode($snapshot,JSON_UNESCAPED_SLASHES),$userId]);
            $pdo->commit();return $id;
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public static function createFromTemplate(string $key,?int $userId): int
    {
        $t=self::templates()[$key]??null;if(!$t)throw new RuntimeException('Unknown workflow template.');
        $input=['name'=>$t['name'],'event_key'=>$t['event_key'],'description'=>$t['description'],'priority'=>100,'active'=>'1','step_action'=>[],'step_condition'=>[],'step_field'=>[],'step_value'=>[],'step_config'=>[]];
        foreach($t['steps'] as $s){$input['step_action'][]=$s['action_key'];$input['step_condition'][]='always';$input['step_field'][]='';$input['step_value'][]='';$input['step_config'][]=json_encode($s['config'],JSON_UNESCAPED_SLASHES);}
        return self::saveWorkflow(null,$input,$userId);
    }

    public static function trigger(string $event,?string $entityType=null,?int $entityId=null,array $context=[]): int
    {
        $pdo=Database::connection();$s=$pdo->prepare('SELECT id FROM workflow_rules WHERE event_key=? AND active=1 ORDER BY priority,id');$s->execute([$event]);$n=0;foreach($s->fetchAll() as $r){if(self::queueRule((int)$r['id'],$entityType,$entityId,$context))$n++;}return $n;
    }

    public static function queueRule(int $id,?string $entityType=null,?int $entityId=null,array $context=[]): int
    {
        $pdo=Database::connection();$s=$pdo->prepare('SELECT id,event_key FROM workflow_rules WHERE id=? AND active=1');$s->execute([$id]);$r=$s->fetch();if(!$r)return 0;
        $pdo->prepare('INSERT INTO workflow_runs(workflow_rule_id,event_key,entity_type,entity_id,context_json,current_step,next_attempt_at) VALUES(?,?,?,?,?,1,NOW())')->execute([$id,$r['event_key'],$entityType,$entityId,json_encode($context,JSON_UNESCAPED_SLASHES)]);return (int)$pdo->lastInsertId();
    }

    private static function snapshot(int $id,$pdo): array
    {
        $s=$pdo->prepare('SELECT * FROM workflow_rules WHERE id=?');$s->execute([$id]);$rule=$s->fetch();$s=$pdo->prepare('SELECT * FROM workflow_steps WHERE workflow_rule_id=? ORDER BY sort_order,id');$s->execute([$id]);return ['rule'=>$rule,'steps'=>$s->fetchAll()];
    }
}
