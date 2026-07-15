<?php
declare(strict_types=1);
$root=dirname(__DIR__);$errors=[];
$checks=[
 'app/controllers/WorkflowController.php'=>['createFromTemplate','Workflow Designer','queueRule'],
 'app/services/WorkflowService.php'=>['workflow_steps','workflow_versions','templates'],
 'app/views/portal/workflows/builder.php'=>['workflowStepTemplate','step_action[]'],
 'scripts/process-workflows.php'=>['next_attempt_at','condition_matches','render_values'],
 'database/migrate_phase28.sql'=>['CREATE TABLE IF NOT EXISTS workflow_steps','CREATE TABLE IF NOT EXISTS workflow_versions'],
 'public/index.php'=>['/portal/workflows/create','/portal/workflows/runs/{id}/retry'],
];
foreach($checks as $file=>$needles){$text=@file_get_contents($root.'/'.$file);if($text===false){$errors[]="Missing {$file}";continue;}foreach($needles as $needle)if(!str_contains($text,$needle))$errors[]="{$file} missing {$needle}";}
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(1);}echo "ServiceOS 0.28.0 regression checks passed.\n";
