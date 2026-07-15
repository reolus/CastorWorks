<?php

declare(strict_types=1);
$root=dirname(__DIR__);$errors=[];
$required=['app/controllers/AiAssistantController.php','app/services/AiProviderService.php','app/services/AiContextService.php','app/services/AiOperationalService.php','app/services/AiRedactionService.php','app/views/portal/ai/index.php','database/migrate_phase33_0.sql','database/migrate_phase33_1.sql','scripts/apply-release-0.33.1.php'];
foreach($required as $f)if(!is_file($root.'/'.$f))$errors[]='Missing '.$f;
foreach(glob($root.'/app/{controllers,services}/Ai*.php',GLOB_BRACE)?:[] as $f){exec('php -l '.escapeshellarg($f).' 2>&1',$out,$code);if($code!==0)$errors[]='Syntax: '.basename($f);}
if(!str_contains(file_get_contents($root.'/database/migrate_phase33_1.sql')?:'','ai_generated_drafts'))$errors[]='Draft migration missing';
if($errors){fwrite(STDERR,"CastorWorks 0.33.1 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "CastorWorks 0.33.1 regression passed.\n";
