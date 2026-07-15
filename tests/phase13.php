<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fail=[];
$required=[
 'app/controllers/ConversationController.php','app/controllers/InspectionTemplateController.php','app/controllers/WorkOrderTemplateController.php','app/controllers/CustomFieldController.php','app/controllers/EquipmentCustodyController.php','app/controllers/CertificationController.php','database/migrate_phase13.sql',
 'app/views/portal/conversations/index.php','app/views/portal/inspection-templates/index.php','app/views/portal/work-order-templates/index.php','app/views/portal/custom-fields/index.php','app/views/portal/equipment-custody/index.php','app/views/portal/certifications/index.php'
];
foreach($required as $f)if(!is_file($root.'/'.$f))$fail[]='Missing '.$f;
$routes=file_get_contents($root.'/public/index.php');foreach(['/portal/conversations','/portal/inspection-templates','/portal/work-order-templates','/portal/custom-fields','/portal/equipment-custody','/portal/certifications','/api/v1/jobs/{id}/status'] as $route)if(!str_contains($routes,$route))$fail[]='Missing route '.$route;
$sql=file_get_contents($root.'/database/migrate_phase13.sql');foreach(['conversation_threads','inspection_templates','work_order_templates','custom_field_definitions','equipment_custody','employee_certifications','job_notes'] as $table)if(!str_contains($sql,$table))$fail[]='Missing schema object '.$table;
if($fail){fwrite(STDERR,"Phase 13 regression test failed:\n - ".implode("\n - ",$fail)."\n");exit(1);}echo "Phase 13 regression test passed.\n";
