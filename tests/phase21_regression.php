<?php
$root=dirname(__DIR__);$checks=[
 'app/services/EntraUserSyncService.php'=>'class EntraUserSyncService',
 'app/controllers/EntraUserController.php'=>'class EntraUserController',
 'app/views/portal/users/entra.php'=>'Microsoft 365 Users',
 'database/migrate_phase21.sql'=>'entra_sync_runs',
 'scripts/sync-entra-users.php'=>'syncAll',
];$fail=[];foreach($checks as $file=>$needle){$body=@file_get_contents($root.'/'.$file);if($body===false||!str_contains($body,$needle))$fail[]=$file;}if($fail){fwrite(STDERR,'Phase 21 regression failures: '.implode(', ',$fail).PHP_EOL);exit(1);}echo "Phase 21 regression tests passed.\n";
