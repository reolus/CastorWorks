<?php
$root=dirname(__DIR__);
$checks=[
 'app/controllers/WorkforceController.php'=>'class WorkforceController',
 'app/views/portal/workforce/index.php'=>'Workforce Management',
 'database/migrate_phase23.sql'=>'CREATE TABLE IF NOT EXISTS crews',
 'database/migrate_phase23.sql'=>'CREATE TABLE IF NOT EXISTS staff_skills',
 'public/index.php'=>'/portal/workforce',
 'app/views/partials/portal-sidebar.php'=>'Workforce',
 'VERSION'=>'0.23.0',
];
$fail=[];
foreach($checks as $file=>$needle){$body=@file_get_contents($root.'/'.$file);if($body===false||!str_contains($body,$needle))$fail[]=$file.' missing '.$needle;}
if($fail){fwrite(STDERR,"Release 0.23 regression failures:\n - ".implode("\n - ",$fail)."\n");exit(1);}echo "Release 0.23 regression tests passed.\n";
