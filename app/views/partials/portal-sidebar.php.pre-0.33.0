<?php
use App\Core\Auth;
$currentPath=parse_url($_SERVER['REQUEST_URI']??'/portal',PHP_URL_PATH)?:'/portal';
$canOps=Auth::can('owner','office','crew_leader');
$canSales=Auth::can('owner','office','estimator');
$canAdmin=Auth::can('owner','administrator');
$groups=[
 'sales'=>['label'=>'Sales & Customers','icon'=>'fa-solid fa-users','items'=>[
  ['/portal/leads','fa-solid fa-bullseye','Leads'],['/portal/marketing','fa-solid fa-bullhorn','Marketing'],['/portal/customers','fa-solid fa-users','Customers'],['/portal/estimates','fa-solid fa-file-signature','Estimates'],['/portal/agreements','fa-solid fa-file-contract','Agreements'],
 ]],
 'operations'=>['label'=>'Operations','icon'=>'fa-solid fa-briefcase','items'=>[
  ['/portal/jobs','fa-solid fa-briefcase','Jobs'],['/portal/technician/today','fa-solid fa-mobile-screen','Today'],['/portal/scheduling','fa-solid fa-calendar-days','Scheduling'],['/portal/dispatch','fa-solid fa-table-columns','Dispatch'],['/portal/routes','fa-solid fa-route','Routes'],['/portal/map','fa-solid fa-map-location-dot','Dispatch Map'],['/portal/timesheets','fa-solid fa-clock','Time Tracking'],['/portal/recurring','fa-solid fa-arrows-rotate','Recurring'],['/portal/inspection-templates','fa-solid fa-clipboard-check','Inspections'],['/portal/corrective-actions','fa-solid fa-triangle-exclamation','Corrective Actions'],['/portal/corrective-actions/sla','fa-solid fa-stopwatch','Corrective SLA'],['/portal/calendar-conflicts','fa-solid fa-calendar-exclamation','Calendar Conflicts'],
 ]],
 'scheduling'=>['label'=>'Scheduling Requests','icon'=>'fa-solid fa-calendar-plus','items'=>[
  ['/portal/scheduling-requests','fa-solid fa-calendar-plus','New Requests'],['/portal/schedule-changes','fa-solid fa-calendar-xmark','Changes & Cancellations'],['/portal/availability','fa-solid fa-user-clock','Availability'],
 ]],
 'communications'=>['label'=>'Communications','icon'=>'fa-solid fa-comments','items'=>[
  ['/portal/communications','fa-solid fa-paper-plane','Campaigns'],['/portal/conversations','fa-solid fa-comments','Conversations'],['/portal/notifications','fa-solid fa-bell','Notification Center'],['/portal/preferences','fa-solid fa-sliders','Preferences'],['/portal/notification-routes','fa-solid fa-route','Notification Routing'],['/portal/sms-templates','fa-solid fa-comment-sms','SMS Templates'],['/portal/email-templates','fa-solid fa-envelope','Email Templates'],
 ]],
 'financials'=>['label'=>'Financials','icon'=>'fa-solid fa-dollar-sign','items'=>[
  ['/portal/invoices','fa-solid fa-file-invoice-dollar','Invoices'],['/portal/billing','fa-solid fa-repeat','Recurring Billing'],['/portal/collections','fa-solid fa-hand-holding-dollar','Collections'],['/portal/accounting','fa-solid fa-file-export','Accounting'],['/portal/quickbooks','fa-solid fa-book','QuickBooks'],['/portal/quickbooks/mappings','fa-solid fa-code-branch','QB Mappings'],['/portal/reports','fa-solid fa-chart-line','Reports'],['/portal/business-intelligence','fa-solid fa-chart-pie','Business Intelligence'],['/portal/operational-analytics','fa-solid fa-route','Operational Analytics'],['/portal/job-costs','fa-solid fa-coins','Job Costs'],
 ]],
 'documents'=>['label'=>'Documents & Templates','icon'=>'fa-solid fa-folder-open','items'=>[
  ['/portal/document-versions','fa-solid fa-clock-rotate-left','Document Versions'],['/portal/inspection-templates','fa-solid fa-clipboard-check','Inspection Templates'],['/portal/attachment-policies','fa-solid fa-images','Media Policies'],['/portal/work-order-templates','fa-solid fa-list-check','Work Order Templates'],['/portal/approvals','fa-solid fa-file-circle-check','Document Approvals'],['/portal/inspection-archives','fa-solid fa-file-pdf','Inspection Archives'],['/portal/entity-approvals','fa-solid fa-user-check','Estimate & Job Approvals'],
 ]],
 'assets'=>['label'=>'Equipment & Inventory','icon'=>'fa-solid fa-boxes-stacked','show'=>$canOps,'items'=>[
  ['/portal/fleet','fa-solid fa-truck','Fleet'],['/portal/maintenance','fa-solid fa-screwdriver-wrench','Maintenance'],['/portal/purchase-orders','fa-solid fa-cart-shopping','Purchase Orders'],['/portal/inventory','fa-solid fa-boxes-stacked','Inventory'],['/portal/equipment-custody','fa-solid fa-hand-holding','Equipment Custody'],['/portal/equipment-labels','fa-solid fa-qrcode','Asset Labels'],['/portal/asset-history','fa-solid fa-clock-rotate-left','Asset History'],['/portal/asset-planning','fa-solid fa-chart-line','Replacement Planning'],['/portal/asset-alerts','fa-solid fa-triangle-exclamation','Replacement Alerts'],
 ]],
 'catalog'=>['label'=>'Services & Pricing','icon'=>'fa-solid fa-tags','show'=>$canSales,'items'=>[
  ['/portal/services','fa-solid fa-list-check','Services'],['/portal/packages','fa-solid fa-box-open','Packages'],['/portal/territories','fa-solid fa-map-location-dot','Territories'],['/portal/tax-rules','fa-solid fa-percent','Tax & Discounts'],
 ]],
 'team'=>['label'=>'Team & Access','icon'=>'fa-solid fa-user-shield','items'=>[
  ['/portal/workforce','fa-solid fa-people-group','Workforce'],
  ['/portal/users','fa-solid fa-users-gear','Team Members'],['/portal/users/microsoft','fa-brands fa-microsoft','Microsoft 365 Users'],['/portal/users/microsoft/access','fa-solid fa-user-shield','Entra Access Mapping'],['/portal/certifications','fa-solid fa-certificate','Certifications'],['/portal/certification-approvals','fa-solid fa-user-check','Certification Approvals'],['/portal/equipment-custody','fa-solid fa-hand-holding','Equipment Custody'],['/portal/api','fa-solid fa-code','API Access'],['/portal/api/analytics','fa-solid fa-chart-column','API Analytics'],
 ]],
 'system'=>['label'=>'System','icon'=>'fa-solid fa-gear','show'=>$canAdmin,'items'=>[
  ['/portal/integrations','fa-solid fa-plug','Integrations'],['/portal/map-providers','fa-solid fa-map','Map Providers'],['/portal/gps-policies','fa-solid fa-location-crosshairs','GPS Tracking'],['/portal/eta-policies','fa-solid fa-clock','ETA & Route Progress'],['/portal/communication-providers','fa-solid fa-tower-broadcast','Communication Providers'],['/portal/microsoft365','fa-brands fa-microsoft','Microsoft 365'],['/portal/modules','fa-solid fa-cubes','Modules'],['/portal/custom-fields','fa-solid fa-sliders','Custom Fields'],['/portal/workflows','fa-solid fa-diagram-project','Workflow Automation'],['/portal/executive-reports','fa-solid fa-envelope-open-text','Executive Reports'],['/portal/webhooks','fa-solid fa-rotate','Webhook Replay'],['/portal/health','fa-solid fa-heart-pulse','System Health'],['/portal/backups','fa-solid fa-database','Backups'],['/portal/observability','fa-solid fa-chart-simple','Monitoring'],['/portal/graph-diagnostics','fa-brands fa-microsoft','Graph Diagnostics'],['/portal/system/upgrade','fa-solid fa-arrow-up-from-bracket','System Upgrade'],
 ]],
];
function sidebar_group_active(array $items,string $path):bool{foreach($items as $item){if($path===$item[0]||str_starts_with($path,$item[0].'/'))return true;}return false;}
?>
<aside class="portal-sidebar" id="portalSidebar">
 <div class="sidebar-brand"><img src="<?=asset('img/logo-white.png')?>" alt="Rock Bluffs Exterior Services"></div>
 <nav class="sidebar-nav" aria-label="Portal navigation">
  <a class="sidebar-dashboard <?=active_nav('/portal')?>" href="/portal"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
  <?php foreach($groups as $key=>$group): if(isset($group['show'])&&!$group['show'])continue;$open=sidebar_group_active($group['items'],$currentPath);?>
  <section class="sidebar-group <?=$open?'is-open':''?>" data-sidebar-group="<?=e($key)?>">
   <button type="button" class="sidebar-group-toggle" aria-expanded="<?=$open?'true':'false'?>">
    <i class="<?=e($group['icon'])?> group-icon"></i><span><?=e($group['label'])?></span><i class="fa-solid fa-chevron-down group-chevron"></i>
   </button>
   <div class="sidebar-submenu">
    <?php foreach($group['items'] as [$url,$icon,$label]):?>
     <a class="<?=active_nav($url)?>" href="<?=e($url)?>"><i class="<?=e($icon)?>"></i><span><?=e($label)?></span></a>
    <?php endforeach?>
   </div>
  </section>
  <?php endforeach?>
 <a class="sidebar-nav-link" href="/portal/mobile"><i class="fa-solid fa-mobile-screen-button"></i><span class="sidebar-label">Field Mobile</span></a></nav>
 <div class="sidebar-footer-actions">
  <button type="button" class="sidebar-collapse-btn" id="sidebarCollapse" title="Collapse sidebar"><i class="fa-solid fa-angles-left"></i><span>Collapse menu</span></button>
  <form action="/logout" method="post" class="sidebar-logout"><?=csrf_field()?><button class="btn btn-link"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></button></form>
 </div>
</aside>
