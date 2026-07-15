<?php
declare(strict_types=1);
$root=dirname(__DIR__);$checks=[
 'sidebar groups'=>str_contains(file_get_contents($root.'/app/views/partials/portal-sidebar.php'),'sidebar-group-toggle'),
 'corrective controller'=>is_file($root.'/app/controllers/CorrectiveActionController.php'),
 'asset history'=>is_file($root.'/app/controllers/AssetHistoryController.php'),
 'inspection attachments'=>str_contains(file_get_contents($root.'/app/controllers/InspectionController.php'),'uploadAttachment'),
 'cert documents'=>str_contains(file_get_contents($root.'/app/controllers/CertificationController.php'),'uploadDocument'),
 'phase migration'=>is_file($root.'/database/migrate_phase15.sql'),
];foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." $name\n";if(!$ok)exit(1);}echo "Phase 15 regression checks passed.\n";
