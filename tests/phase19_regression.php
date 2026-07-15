<?php
$root=dirname(__DIR__);$checks=[
 'health variable fix'=>str_contains(file_get_contents($root.'/app/controllers/HealthController.php'),'IntegrationHealthService'),
 'universal search route'=>str_contains(file_get_contents($root.'/public/index.php'),'/portal/search'),
 'timeline service'=>is_file($root.'/app/services/CustomerTimelineService.php'),
 'map controller'=>is_file($root.'/app/controllers/MapController.php'),
 'theme support'=>str_contains(file_get_contents($root.'/app/views/layouts/portal.php'),'data-theme'),
 'migration'=>is_file($root.'/database/migrate_phase19.sql'),
];foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n";if(!$ok)exit(1);}echo "Phase 19 regression checks passed.\n";
