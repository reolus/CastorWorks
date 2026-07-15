<?php
declare(strict_types=1);
$root=dirname(__DIR__);$checks=[
 'database/migrate_phase16.sql'=>['corrective_action_events','inspection_attachment_policies','certification_renewals','api_daily_metrics'],
 'app/services/AssetActivityService.php'=>['class AssetActivityService','asset_activity'],
 'app/services/InspectionAttachmentService.php'=>['class InspectionAttachmentService','thumbnail'],
 'app/controllers/AttachmentPolicyController.php'=>['Inspection Media Policies'],
 'app/controllers/ApiAnalyticsController.php'=>['api_usage_log'],
 'scripts/process-corrective-actions.php'=>['corrective_action_events'],
 'scripts/prune-inspection-attachments.php'=>['deleted_at'],
 'scripts/send-certification-renewals.php'=>['certification_renewals'],
 'app/views/partials/portal-sidebar.php'=>['Media Policies','API Analytics'],
];
$failed=[];foreach($checks as $file=>$needles){$path=$root.'/'.$file;if(!is_file($path)){$failed[]="Missing $file";continue;}$body=file_get_contents($path);foreach($needles as $n)if(!str_contains($body,$n))$failed[]="$file missing $n";}
if($failed){fwrite(STDERR,implode("\n",$failed)."\n");exit(1);}echo "Phase 16 regression checks passed.\n";
