<div class="page-title-row">
 <div><span class="text-muted">Daily field operations</span><h1>Route Planning</h1><p class="text-muted mb-0">Build, compare, and approve route plans without changing the live schedule until acceptance.</p></div>
 <div class="d-flex gap-2"><?php if($mapUrl):?><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?=e($mapUrl)?>"><i class="fa-solid fa-route me-2"></i>Open Current Route</a><?php endif?><a class="btn btn-primary" href="/portal/map?date=<?=e($date)?><?= $crewId ? '&crew_id='.(int)$crewId : '' ?>"><i class="fa-solid fa-map"></i> Dispatch Map</a></div>
</div>

<div class="portal-card p-4 mb-4">
 <form class="row g-3 align-items-end" method="get">
  <div class="col-md-4"><label class="form-label">Route date</label><input type="date" name="date" value="<?=e($date)?>" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Crew</label><select name="crew_id" class="form-select"><option value="0">All crews / ungrouped</option><?php foreach($crews as $crew):?><option value="<?=$crew['id']?>" <?=$crewId===(int)$crew['id']?'selected':''?>><?=e($crew['name'])?></option><?php endforeach?></select></div>
  <div class="col-md-auto"><button class="btn btn-outline-primary">Load route</button></div>
 </form>
</div>

<?php if($activePlan):?>
<div class="portal-card p-4 mb-4 border-warning route-proposal-card">
 <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
  <div><span class="badge text-bg-warning mb-2">Proposal v<?=e($activePlan['version_no'])?></span><h2 class="h4 mb-1">Optimization ready for review</h2><p class="text-muted mb-0">Created <?=e($activePlan['created_at'])?> by <?=e($activePlan['created_by_name']??'System')?>.</p></div>
  <div class="d-flex gap-2"><form method="post" action="/portal/routes/plans/<?=$activePlan['id']?>/reject"><?=csrf_field()?><button class="btn btn-outline-danger">Reject</button></form><form method="post" action="/portal/routes/plans/<?=$activePlan['id']?>/accept"><?=csrf_field()?><button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Accept and Apply</button></form></div>
 </div>
 <div class="row g-3 mt-2">
  <div class="col-6 col-lg-3"><div class="metric-card"><div><span>Current distance</span><strong><?=number_format((float)$activePlan['current_distance_miles'],1)?> mi</strong></div></div></div>
  <div class="col-6 col-lg-3"><div class="metric-card"><div><span>Proposed distance</span><strong><?=number_format((float)$activePlan['optimized_distance_miles'],1)?> mi</strong></div></div></div>
  <div class="col-6 col-lg-3"><div class="metric-card"><div><span>Distance saved</span><strong><?=number_format((float)$activePlan['distance_savings_miles'],1)?> mi</strong></div></div></div>
  <div class="col-6 col-lg-3"><div class="metric-card"><div><span>Drive time saved</span><strong><?=number_format((int)$activePlan['duration_savings_minutes'])?> min</strong></div></div></div>
 </div>
 <div class="table-responsive mt-3"><table class="table align-middle mb-0"><thead><tr><th>Proposed</th><th>Current</th><th>Customer</th><th>Time</th><th>Address</th><th>Lock</th></tr></thead><tbody><?php foreach($planStops as $stop):?><tr class="<?=$stop['is_locked']?'table-warning':''?>"><td><span class="badge text-bg-primary fs-6"><?=e($stop['stop_order'])?></span></td><td><?=e($stop['original_order']??'—')?></td><td><a href="/portal/jobs/<?=$stop['job_id']?>"><?=e($stop['display_name'])?></a><br><small class="text-muted"><?=e($stop['job_number'])?> · <?=e($stop['service_summary'])?></small></td><td><?=e($stop['scheduled_start']?date('g:i A',strtotime($stop['scheduled_start'])):'Unscheduled')?></td><td><?=e($stop['full_address'])?></td><td><form method="post" action="/portal/routes/plans/<?=$activePlan['id']?>/stops/<?=$stop['id']?>/lock"><?=csrf_field()?><button class="btn btn-sm <?=$stop['is_locked']?'btn-warning':'btn-outline-secondary'?>" title="Locked stops retain their current position when a new proposal is created"><i class="fa-solid fa-<?=$stop['is_locked']?'lock':'lock-open'?>"></i></button></form></td></tr><?php endforeach?></tbody></table></div>
 <p class="small text-muted mt-3 mb-0">Accepting updates live job route order. Rejecting leaves the current schedule untouched.</p>
</div>
<?php endif?>

<div class="row g-3 mb-4">
 <div class="col-6 col-lg-3"><div class="metric-card"><div><span>Total stops</span><strong><?=e($progress['summary']['total'])?></strong></div></div></div>
 <div class="col-6 col-lg-3"><div class="metric-card"><div><span>Completed</span><strong><?=e($progress['summary']['completed'])?></strong></div></div></div>
 <div class="col-6 col-lg-3"><div class="metric-card"><div><span>In progress</span><strong><?=e($progress['summary']['in_progress'])?></strong></div></div></div>
 <div class="col-6 col-lg-3"><div class="metric-card <?=$progress['summary']['late']?'border-danger':''?>"><div><span>Late</span><strong><?=e($progress['summary']['late'])?></strong></div></div></div>
</div>
<div class="d-flex justify-content-end mb-3"><form method="post" action="/portal/routes/recalculate-etas"><?=csrf_field()?><input type="hidden" name="date" value="<?=e($date)?>"><button class="btn btn-outline-primary"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recalculate ETAs</button></form></div>

<div class="portal-card mb-4">
 <div class="card-header-row"><div><h2>Current live route</h2><p>Order currently assigned to jobs for the selected day.</p></div></div>
 <div class="px-3 pb-3 d-flex flex-wrap gap-2">
  <form method="post" action="/portal/routes/optimize"><?=csrf_field()?><input type="hidden" name="date" value="<?=e($date)?>"><input type="hidden" name="crew_id" value="<?=$crewId?>"><button class="btn btn-warning" <?=$jobs?'':'disabled'?>><i class="fa-solid fa-wand-magic-sparkles"></i> Create Proposal</button></form>
 </div>
 <form method="post" action="/portal/routes"><?=csrf_field()?><input type="hidden" name="date" value="<?=e($date)?>"><input type="hidden" name="crew_id" value="<?=$crewId?>"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="width:80px">Order</th><th>Scheduled</th><th>ETA</th><th>Progress</th><th>Customer</th><th>Service</th><th>Address</th><th>Crew / technician</th></tr></thead><tbody><?php $progressById=[];foreach($progress['rows'] as $pr)$progressById[(int)$pr['id']]=$pr;foreach($jobs as $index=>$job):$pr=$progressById[(int)$job['id']]??[];?><tr><td><span class="badge text-bg-secondary fs-6"><?=e($job['route_order']?:$index+1)?></span><input type="hidden" name="job_ids[]" value="<?=e($job['id'])?>"></td><td><?=e($job['scheduled_start']?date('g:i A',strtotime($job['scheduled_start'])):'Unscheduled')?></td><td><?php if(!empty($pr['estimated_arrival'])):?><strong class="<?=$pr['eta_status']==='late'?'text-danger':'text-success'?>"><?=e(date('g:i A',strtotime($pr['estimated_arrival'])))?></strong><?php if(!empty($pr['eta_late_minutes'])):?><br><small class="text-danger"><?=e($pr['eta_late_minutes'])?> min late</small><?php endif;else:?><span class="text-muted">Not calculated</span><?php endif?></td><td><?php if($job['status']==='completed'):?><span class="badge text-bg-success">Completed</span><?php elseif($job['status']==='in_progress'):?><span class="badge text-bg-primary">In progress</span><?php elseif(!empty($pr['actual_arrival'])):?><span class="badge text-bg-info">Arrived</span><?php else:?><span class="badge text-bg-secondary">Pending</span><?php endif?></td><td><a href="/portal/jobs/<?=e($job['id'])?>"><?=e($job['display_name'])?></a></td><td><?=e($job['service_summary'])?></td><td><?=e($job['full_address'])?></td><td><?=e($job['crew_name']?:($job['technician_name']?:'Unassigned'))?></td></tr><?php endforeach;if(!$jobs):?><tr><td colspan="8" class="text-center text-muted py-5">No jobs are scheduled for this date.</td></tr><?php endif?></tbody></table></div><?php if($jobs):?><div class="p-3 border-top text-end"><button class="btn btn-outline-success"><i class="fa-solid fa-floppy-disk me-2"></i>Save Current Order</button></div><?php endif?></form>
</div>

<?php if($plans):?>
<div class="portal-card"><div class="card-header-row"><div><h2>Plan history</h2><p>Recent versions for this date and crew selection.</p></div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Version</th><th>Status</th><th>Distance</th><th>Savings</th><th>Created</th><th>Accepted</th></tr></thead><tbody><?php foreach($plans as $plan):?><tr><td>v<?=e($plan['version_no'])?></td><td><span class="badge text-bg-<?=$plan['status']==='accepted'?'success':($plan['status']==='rejected'?'secondary':'warning')?>"><?=e($plan['status'])?></span></td><td><?=number_format((float)$plan['optimized_distance_miles'],1)?> mi</td><td><?=number_format((float)$plan['distance_savings_miles'],1)?> mi / <?=number_format((int)$plan['duration_savings_minutes'])?> min</td><td><?=e($plan['created_at'])?></td><td><?=e($plan['accepted_at']??'—')?></td></tr><?php endforeach?></tbody></table></div></div>
<?php endif?>
