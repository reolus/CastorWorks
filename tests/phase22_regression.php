<?php
$root=dirname(__DIR__);$checks=[
 'app/services/EntraAccessService.php'=>'class EntraAccessService',
 'app/services/EntraUserSyncService.php'=>'function syncOne',
 'app/controllers/EntraAccessController.php'=>'class EntraAccessController',
 'app/views/portal/users/entra-access.php'=>'Entra Access Mapping',
 'database/migrate_phase22.sql'=>'entra_group_role_mappings',
 'scripts/sync-entra-users.php'=>'schedule_enabled',
 'public/index.php'=>'/portal/users/microsoft/access',
];$fail=[];foreach($checks as $file=>$needle){$body=@file_get_contents($root.'/'.$file);if($body===false||!str_contains($body,$needle))$fail[]=$file;}if($fail){fwrite(STDERR,'Phase 22 regression failures: '.implode(', ',$fail).PHP_EOL);exit(1);}echo "Phase 22 regression tests passed.\n";
