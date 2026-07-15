<?php
$root=dirname(__DIR__);$checks=[
'app/controllers/MobileController.php'=>'final class MobileController',
'app/views/layouts/mobile.php'=>'mobile-field-body',
'app/views/portal/mobile/dashboard.php'=>'Today\'s Route',
'app/views/portal/mobile/job.php'=>'Complete job',
'public/assets/css/mobile.css'=>'mobile-bottom-nav',
'public/assets/js/mobile.js'=>'serviceWorker',
'public/index.php'=>"/portal/mobile",
'database/migrate_phase25.sql'=>'mobile_push_subscriptions',
];$failed=[];foreach($checks as $file=>$needle){$path=$root.'/'.$file;if(!is_file($path)||!str_contains((string)file_get_contents($path),$needle))$failed[]=$file;}if($failed){fwrite(STDERR,'Phase 25 regression failed: '.implode(', ',$failed).PHP_EOL);exit(1);}echo "Phase 25 regression passed.\n";
