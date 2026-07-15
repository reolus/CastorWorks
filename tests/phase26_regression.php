<?php
$root=dirname(__DIR__);$required=['app/controllers/CustomerAccountController.php','app/views/customer/account.php','public/assets/css/customer-account.css','database/migrate_phase26.sql'];$failed=[];foreach($required as $f){if(!is_file($root.'/'.$f))$failed[]=$f;}if($failed){fwrite(STDERR,'Missing: '.implode(', ',$failed).PHP_EOL);exit(1);}echo "Phase 26 regression checks passed.\n";
