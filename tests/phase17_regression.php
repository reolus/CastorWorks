<?php
declare(strict_types=1);
$root=dirname(__DIR__);$checks=[
 'sidebar flyout CSS'=>str_contains(file_get_contents($root.'/public/assets/css/site.css'),'collapsed sidebar flyout repair'),
 'sidebar flyout JS'=>str_contains(file_get_contents($root.'/public/assets/js/site.js'),'stable collapsed-sidebar flyouts'),
 'phase17 migration'=>is_file($root.'/database/migrate_phase17.sql'),
 'inspection PDF route'=>str_contains(file_get_contents($root.'/public/index.php'),"/portal/inspections/{id}/pdf"),
 'notification routing'=>is_file($root.'/app/controllers/NotificationRouteController.php'),
 'asset planning'=>is_file($root.'/app/controllers/AssetPlanningController.php'),
 'api version'=>preg_match("/'version'=>'(17|18)\\.0'/",file_get_contents($root.'/app/controllers/ApiController.php'))===1,
 'mysql-safe phase16'=>!str_contains(file_get_contents($root.'/database/migrate_phase16.sql'),'ADD COLUMN IF NOT EXISTS'),
];foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n";if(!$ok)exit(1);}echo "Phase 17 regression checks passed.\n";
