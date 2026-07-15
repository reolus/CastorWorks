#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\TeamsService;

require dirname(__DIR__).'/app/core/Env.php';
Env::load(dirname(__DIR__).'/.env');
spl_autoload_register(function(string $class):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$relative=substr($class,strlen($prefix));$parts=explode('\\',$relative);$parts[0]=strtolower($parts[0]);$file=dirname(__DIR__).'/app/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$vendor=dirname(__DIR__).'/vendor/autoload.php';if(is_file($vendor))require_once $vendor;

$pdo=Database::connection();
$rows=$pdo->query("SELECT wr.*,w.name rule_name FROM workflow_runs wr JOIN workflow_rules w ON w.id=wr.workflow_rule_id WHERE wr.status IN ('queued','running') AND (wr.next_attempt_at IS NULL OR wr.next_attempt_at<=NOW()) ORDER BY wr.queued_at LIMIT 50")->fetchAll();

foreach($rows as $run){
    $runId=(int)$run['id'];
    try{
        $pdo->prepare("UPDATE workflow_runs SET status='running',started_at=COALESCE(started_at,NOW()),attempt_count=attempt_count+1,error_message=NULL WHERE id=?")->execute([$runId]);
        $stepsStmt=$pdo->prepare('SELECT * FROM workflow_steps WHERE workflow_rule_id=? ORDER BY sort_order,id');$stepsStmt->execute([$run['workflow_rule_id']]);$steps=$stepsStmt->fetchAll();
        $context=json_decode((string)($run['context_json']??'{}'),true);if(!is_array($context))$context=[];
        $index=max(0,(int)($run['current_step']??1)-1);
        if(!isset($steps[$index])){$pdo->prepare("UPDATE workflow_runs SET status='completed',completed_at=NOW(),result_json=? WHERE id=?")->execute([json_encode(['message'=>'Workflow completed']),$runId]);continue;}
        $step=$steps[$index];
        if(!condition_matches($step,$context)){$pdo->prepare('UPDATE workflow_runs SET current_step=current_step+1,next_attempt_at=NOW() WHERE id=?')->execute([$runId]);continue;}
        $cfg=json_decode((string)($step['config_json']??'{}'),true);if(!is_array($cfg))$cfg=[];
        $cfg=render_values($cfg,$context);
        $result=execute_action((string)$step['action_key'],$cfg,$run,$context,$pdo);
        if(($result['delayed']??false)===true){$pdo->prepare("UPDATE workflow_runs SET status='queued',next_attempt_at=?,result_json=? WHERE id=?")->execute([$result['next_attempt_at'],json_encode($result),$runId]);continue;}
        $next=$index+2;
        if(isset($steps[$index+1])){$pdo->prepare("UPDATE workflow_runs SET status='queued',current_step=?,next_attempt_at=NOW(),result_json=? WHERE id=?")->execute([$next,json_encode($result),$runId]);}
        else{$pdo->prepare("UPDATE workflow_runs SET status='completed',current_step=?,completed_at=NOW(),next_attempt_at=NULL,result_json=? WHERE id=?")->execute([$next,json_encode($result),$runId]);}
    }catch(Throwable $e){$pdo->prepare("UPDATE workflow_runs SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([$e->getMessage(),$runId]);}
}

echo 'Processed '.count($rows)." workflow run(s).\n";

function condition_matches(array $step,array $context):bool{
    $condition=(string)($step['condition_key']??'always');if($condition==='always')return true;
    $actual=context_get($context,(string)($step['condition_field']??''));$expected=(string)($step['condition_value']??'');
    return match($condition){'field_equals'=>(string)$actual===$expected,'field_not_equals'=>(string)$actual!==$expected,'field_gte'=>(float)$actual>=(float)$expected,'field_lte'=>(float)$actual<=(float)$expected,'field_contains'=>str_contains(strtolower((string)$actual),strtolower($expected)),default=>true};
}
function context_get(array $context,string $path):mixed{$value=$context;foreach(array_filter(explode('.',$path)) as $part){if(!is_array($value)||!array_key_exists($part,$value))return null;$value=$value[$part];}return $value;}
function render_values(array $values,array $context):array{array_walk_recursive($values,function(&$value)use($context){if(!is_string($value))return;$value=preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/',fn($m)=>(string)(context_get($context,$m[1])??''),$value);});return $values;}
function execute_action(string $action,array $cfg,array $run,array $context,$pdo):array{
    switch($action){
        case 'delay':$minutes=max(1,(int)($cfg['minutes']??60));return ['delayed'=>true,'next_attempt_at'=>date('Y-m-d H:i:s',time()+$minutes*60),'minutes'=>$minutes];
        case 'send_email':$to=(string)($cfg['recipient']??$cfg['to']??'');if($to==='')throw new RuntimeException('Email recipient is required.');(new EmailService())->sendMail($to,(string)($cfg['subject']??$run['rule_name']),(string)($cfg['message']??$cfg['html']??'<p>Automated ServiceOS notification.</p>'));return ['emailed'=>$to];
        case 'send_sms':$phone=(string)($cfg['recipient']??$cfg['phone']??'');if($phone==='')throw new RuntimeException('SMS recipient is required.');SmsService::send(null,null,$phone,(string)($cfg['message']??$cfg['body']??'ServiceOS notification.'));return ['sms'=>$phone];
        case 'notify_teams':(new TeamsService())->send((string)($cfg['title']??$run['rule_name']),(string)($cfg['message']??'Workflow event: '.$run['event_key']),(string)($cfg['url']??'' )?:null);return ['teams'=>true];
        case 'portal_notification':$userId=(int)($cfg['user_id']??0);if($userId<1)throw new RuntimeException('Portal notification user_id is required.');$pdo->prepare('INSERT INTO portal_user_notifications(user_id,event_key,severity,title,message,action_url) VALUES(?,?,?,?,?,?)')->execute([$userId,$run['event_key'],(string)($cfg['severity']??'normal'),(string)($cfg['title']??$run['rule_name']),(string)($cfg['message']??'Workflow notification.'),$cfg['url']??null]);return ['portal_notification'=>$userId];
        case 'update_status':$table=['job'=>'jobs','estimate'=>'estimates','invoice'=>'invoices'][$run['entity_type']??'']??null;if(!$table||empty($run['entity_id']))throw new RuntimeException('Status update requires a supported entity.');$status=(string)($cfg['status']??'');if($status==='')throw new RuntimeException('Status value is required.');$pdo->prepare("UPDATE {$table} SET status=? WHERE id=?")->execute([$status,$run['entity_id']]);return ['status'=>$status];
        default:throw new RuntimeException('Unsupported workflow action: '.$action);
    }
}
