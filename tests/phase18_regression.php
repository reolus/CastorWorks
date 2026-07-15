<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fail=[];$must=[
 'database/migrate_phase18.sql'=>['notification_delivery_logs','inspection_packet_archives','asset_replacement_alerts','quota_warning_percent'],
 'app/services/NotificationRouteService.php'=>['class NotificationRouteService','portal_user_notifications'],
 'app/controllers/CertificationApprovalController.php'=>['class CertificationApprovalController','certification_approval_attachments'],
 'app/controllers/InspectionArchiveController.php'=>['class InspectionArchiveController','InspectionPacketService'],
 'app/controllers/AssetAlertController.php'=>['class AssetAlertController'],
 'public/index.php'=>['/portal/certification-approvals','/portal/inspection-archives','/portal/asset-alerts','/portal/notifications'],
 'app/views/partials/portal-sidebar.php'=>['Certification Approvals','Inspection Archives','Replacement Alerts','Notification Center'],
];
foreach($must as $file=>$needles){$path=$root.'/'.$file;if(!is_file($path)){$fail[]="Missing $file";continue;}$text=file_get_contents($path);foreach($needles as $needle)if(!str_contains($text,$needle))$fail[]="$file missing $needle";}
if(str_contains(file_get_contents($root.'/database/migrate_phase18.sql'),'ADD COLUMN IF NOT EXISTS'))$fail[]='Migration contains MariaDB-only ADD COLUMN IF NOT EXISTS.';
if($fail){fwrite(STDERR,implode("\n",$fail)."\n");exit(1);}echo "Phase 18 regression checks passed.\n";
