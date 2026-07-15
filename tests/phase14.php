<?php
declare(strict_types=1);
$root=dirname(__DIR__);$checks=[
 'inspection controller'=>is_file($root.'/app/controllers/InspectionController.php'),
 'equipment label controller'=>is_file($root.'/app/controllers/EquipmentLabelController.php'),
 'custom field service'=>is_file($root.'/app/services/CustomFieldService.php'),
 'phase 14 migration'=>is_file($root.'/database/migrate_phase14.sql'),
 'certification worker'=>is_file($root.'/scripts/send-certification-alerts.php'),
 'inspection view'=>is_file($root.'/app/views/portal/inspections/show.php'),
 'equipment labels view'=>is_file($root.'/app/views/portal/equipment-labels/index.php'),
];
$index=file_get_contents($root.'/public/index.php');$checks['inspection routes']=str_contains($index,'InspectionController');$checks['customer reply route']=str_contains($index,'replyConversation');$checks['asset routes']=str_contains($index,'EquipmentLabelController');
$api=file_get_contents($root.'/app/controllers/ApiController.php');$checks['api pagination']=str_contains($api,'per_page');$checks['api idempotency']=str_contains($api,'Idempotency-Key');
$composer=json_decode(file_get_contents($root.'/composer.json'),true);$checks['QR composer dependency']=isset($composer['require']['endroid/qr-code']);
$failed=[];foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n";if(!$ok)$failed[]=$name;}exit($failed?1:0);
