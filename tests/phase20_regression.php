<?php
$root=dirname(__DIR__);$checks=[
 'Env exports putenv'=>str_contains(file_get_contents($root.'/app/core/Env.php'),'putenv('),
 'Teams real test'=>str_contains(file_get_contents($root.'/app/services/TeamsService.php'),'public function test'),
 'Teams health persistence'=>str_contains(file_get_contents($root.'/app/services/IntegrationHealthService.php'),'integration_health_checks'),
 'Microsoft 365 admin'=>is_file($root.'/app/controllers/Microsoft365Controller.php')&&is_file($root.'/app/views/portal/microsoft365/index.php'),
 'Module admin'=>is_file($root.'/app/controllers/ModuleController.php'),
 'Migration tracker'=>is_file($root.'/scripts/migrate.php'),
 'Phase 20 migration'=>is_file($root.'/database/migrate_phase20.sql'),
];$failed=[];foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - {$name}\n";if(!$ok)$failed[]=$name;}exit($failed?1:0);
