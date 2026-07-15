<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$required=[
 'app/services/CrewOperationsService.php',
 'app/controllers/CrewCalendarController.php',
 'app/controllers/CrewReportController.php',
 'app/controllers/DispatchController.php',
 'app/controllers/JobController.php',
 'app/controllers/TechnicianController.php',
 'app/views/portal/workforce/calendar.php',
 'app/views/portal/workforce/report.php',
 'database/migrate_phase24.sql',
 'scripts/migrate.php',
];
$failed=[];
foreach($required as $file){if(!is_file($root.'/'.$file))$failed[]="Missing {$file}";}
$routes=is_file($root.'/public/index.php')?file_get_contents($root.'/public/index.php'):'';
foreach(['/portal/workforce/calendar','/portal/workforce/report'] as $route){if(!str_contains((string)$routes,$route))$failed[]="Missing route {$route}";}
$migration=is_file($root.'/database/migrate_phase24.sql')?file_get_contents($root.'/database/migrate_phase24.sql'):'';
foreach(['job_crew_assignment_history','job_required_skills'] as $table){if(!str_contains((string)$migration,$table))$failed[]="Missing migration object {$table}";}
if($failed){fwrite(STDERR,implode("\n",$failed)."\n");exit(1);}echo "ServiceOS 0.24.0 regression checks passed.\n";
