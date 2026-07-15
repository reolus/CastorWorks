<?php

declare(strict_types=1);

$root=dirname(__DIR__); $errors=[];
$required=[
'app/controllers/AiAssistantController.php',
'app/services/AiGovernanceService.php',
'app/services/AiCostService.php',
'app/views/portal/ai/index.php',
'database/migrate_phase33_2.sql',
'scripts/apply-release-0.33.2.php',
];
foreach($required as $file){if(!is_file($root.'/'.$file))$errors[]='Missing '.$file;}
$controller=@file_get_contents($root.'/app/controllers/AiAssistantController.php')?:'';
foreach(['rejectDraft','useDraft','monthly_cost_limit_usd','estimated_cost_usd'] as $needle){if(!str_contains($controller,$needle))$errors[]='Controller missing '.$needle;}
$migration=@file_get_contents($root.'/database/migrate_phase33_2.sql')?:'';
foreach(['ai_draft_use_events','approval_required_estimate','estimated_cost_usd'] as $needle){if(!str_contains($migration,$needle))$errors[]='Migration missing '.$needle;}
if($errors){fwrite(STDERR,"CastorWorks 0.33.2 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);} echo "CastorWorks 0.33.2 regression passed.\n";
