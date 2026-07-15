<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
 <div><h1 class="h3 mb-1"><i class="fa-solid fa-user-shield me-2"></i>Entra Access Mapping</h1><p class="text-muted mb-0">Map Microsoft groups to portal roles and control synchronization behavior.</p></div>
 <a class="btn btn-outline-secondary" href="/portal/users/microsoft"><i class="fa-brands fa-microsoft"></i> Microsoft users</a>
</div>
<?php if($error):?><div class="alert alert-danger"><strong>Graph error:</strong> <?=e($error)?><div class="small mt-1">Group discovery requires <code>Group.Read.All</code> or <code>Directory.Read.All</code> application permission with admin consent.</div></div><?php endif;?>
<div class="row g-3 mb-3">
 <div class="col-xl-7">
  <div class="card h-100"><div class="card-header"><strong>Group-to-role mappings</strong></div><div class="card-body">
   <form method="get" class="row g-2 mb-3"><div class="col-md-9"><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Search Microsoft groups"></div><div class="col-md-3"><button class="btn btn-outline-primary w-100"><i class="fa-solid fa-search"></i> Search</button></div></form>
   <form method="post" action="/portal/users/microsoft/access/mappings" class="row g-2 align-items-end mb-3"><?=csrf_field()?>
    <div class="col-md-5"><label class="form-label">Microsoft group</label><select class="form-select" name="entra_group_id" required><option value="">Select group</option><?php foreach($groups as $g):?><option value="<?=e($g['id'])?>" data-name="<?=e($g['displayName'])?>"><?=e($g['displayName'])?></option><?php endforeach?></select><input type="hidden" name="entra_group_name" id="entraGroupName"></div>
    <div class="col-md-3"><label class="form-label">Portal role</label><select class="form-select" name="portal_role"><?php foreach(['technician'=>'Technician','crew_leader'=>'Crew leader','office'=>'Office','estimator'=>'Estimator','administrator'=>'Administrator','owner'=>'Owner'] as $v=>$l):?><option value="<?=e($v)?>"><?=e($l)?></option><?php endforeach?></select></div>
    <div class="col-md-2"><label class="form-label">Priority</label><input class="form-control" type="number" min="1" name="priority" value="100"></div>
    <div class="col-md-2"><input type="hidden" name="active" value="1"><button class="btn btn-primary w-100">Add</button></div>
   </form>
   <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Group</th><th>Role</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($mappings as $m):?><tr><td><?=e($m['entra_group_name'])?><div class="small text-muted"><?=e($m['entra_group_id'])?></div></td><td><span class="badge text-bg-primary"><?=e(str_replace('_',' ',$m['portal_role']))?></span></td><td><?=e($m['priority'])?></td><td><?=$m['active']?'<span class="badge text-bg-success">Active</span>':'<span class="badge text-bg-secondary">Disabled</span>'?></td><td class="text-end"><form method="post" action="/portal/users/microsoft/access/mappings/<?=e($m['id'])?>/delete" onsubmit="return confirm('Remove this mapping?')"><?=csrf_field()?><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button></form></td></tr><?php endforeach?><?php if(!$mappings):?><tr><td colspan="5" class="text-muted text-center py-3">No mappings configured. Unmapped users retain their existing role or default to Technician.</td></tr><?php endif?></tbody></table></div>
  </div></div>
 </div>
 <div class="col-xl-5">
  <div class="card h-100"><div class="card-header"><strong>Synchronization policy</strong></div><div class="card-body"><form method="post" action="/portal/users/microsoft/access/settings"><?=csrf_field()?>
   <div class="mb-3"><label class="form-label">Department filter</label><input class="form-control" name="department_filter" value="<?=e($settings['department_filter'] ?? '')?>" placeholder="Example: Exterior Services"><div class="form-text">Leave blank to include all departments.</div></div>
   <div class="mb-3"><label class="form-label">Required group ID</label><input class="form-control" name="group_filter_id" value="<?=e($settings['group_filter_id'] ?? '')?>" placeholder="Optional Entra group object ID"></div>
   <?php foreach(['import_enabled_only'=>'Import enabled accounts only','disable_missing'=>'Disable portal users missing from Entra','sync_managers'=>'Synchronize manager information','sync_group_roles'=>'Apply group role mappings','schedule_enabled'=>'Enable scheduled synchronization'] as $k=>$label):?><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="<?=e($k)?>" id="<?=e($k)?>" value="1" <?=!empty($settings[$k])?'checked':''?>><label class="form-check-label" for="<?=e($k)?>"><?=e($label)?></label></div><?php endforeach?>
   <div class="mt-3"><label class="form-label">Scheduled sync time</label><input class="form-control" type="time" name="schedule_time" value="<?=e(substr((string)($settings['schedule_time'] ?? '02:00:00'),0,5))?>"></div>
   <button class="btn btn-primary mt-3"><i class="fa-solid fa-floppy-disk"></i> Save policy</button>
  </form></div></div>
 </div>
</div>
<div class="alert alert-info mb-0"><strong>Role priority:</strong> Lower priority numbers are evaluated first. The first matching active group determines the user’s portal role.</div>
<script>document.querySelector('[name="entra_group_id"]')?.addEventListener('change',function(){document.getElementById('entraGroupName').value=this.selectedOptions[0]?.dataset.name||'';});</script>
