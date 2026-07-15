<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$errors=[];
$required=[
 'app/services/AiPolicyService.php',
 'app/services/AiAuditReportService.php',
 'database/migrate_phase33_5.sql',
 'scripts/prune-ai-records.php',
];
foreach($required as $file){if(!is_file($root.'/'.$file))$errors[]='Missing '.$file;}
$routes=file_get_contents($root.'/public/index.php')?:'';
foreach(['/portal/ai/test-provider','/portal/ai/policy','/portal/ai/role-budget','/portal/ai/export'] as $route){if(!str_contains($routes,$route))$errors[]='Missing route '.$route;}
$migration=file_get_contents($root.'/database/migrate_phase33_5.sql')?:'';
foreach(['ai_policy_settings','ai_role_budgets','error_message','expires_at'] as $needle){if(!str_contains($migration,$needle))$errors[]='Migration missing '.$needle;}
if($errors){fwrite(STDERR,"CastorWorks 0.33.5 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "CastorWorks 0.33.5 regression passed.\n";
