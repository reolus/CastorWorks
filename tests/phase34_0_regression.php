<?php

declare(strict_types=1);

$root=dirname(__DIR__);$errors=[];
$required=[
'app/controllers/AgreementController.php','app/services/CommercialContractService.php','app/views/portal/agreements/index.php',
'database/migrate_phase34_0.sql','scripts/generate-contract-jobs.php','scripts/generate-contract-invoices.php',
'scripts/monitor-contract-renewals.php','scripts/monitor-contract-slas.php','release.json'
];
foreach($required as $file){if(!is_file($root.'/'.$file))$errors[]='Missing '.$file;}
$controller=@file_get_contents($root.'/app/controllers/AgreementController.php')?:'';
$service=@file_get_contents($root.'/app/services/CommercialContractService.php')?:'';
$migration=@file_get_contents($root.'/database/migrate_phase34_0.sql')?:'';
foreach(['create_contract','add_location','add_service','add_sla','add_amendment','add_po','generate_jobs','generate_invoices'] as $needle){if(!str_contains($controller,$needle))$errors[]='Controller missing '.$needle;}
foreach(['generateDueJobs','generateDueInvoices','monitorRenewals','monitorSlas'] as $needle){if(!str_contains($service,$needle))$errors[]='Service missing '.$needle;}
foreach(['commercial_contracts','commercial_contract_locations','commercial_contract_services','commercial_contract_slas','commercial_contract_amendments','contract_purchase_orders','contract_compliance_documents','contract_recurring_job_runs','contract_recurring_invoice_runs'] as $table){if(!str_contains($migration,$table))$errors[]='Migration missing '.$table;}
if($errors){fwrite(STDERR,"CastorWorks 0.34.0 regression failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "CastorWorks 0.34.0 regression passed.\n";
