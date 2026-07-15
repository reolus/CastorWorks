<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="text-muted">Team &amp; Access</div>
        <h1 class="h3 mb-1">Workforce Management</h1>
        <p class="text-muted mb-0">Build crews, track qualifications, and see staffing constraints before dispatch becomes interpretive dance.</p>
    </div>
    <a href="/portal/availability" class="btn btn-outline-primary"><i class="fa-solid fa-calendar-check me-2"></i>Availability Calendar</a>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#crews" type="button">Crews</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#skills" type="button">Skills Matrix</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#availability" type="button">Upcoming Constraints</button></li>
</ul>

<div class="tab-content">
    <section class="tab-pane fade show active" id="crews">
        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">Create Crew</h2>
                        <form method="post" action="/portal/workforce/crews">
                            <?=csrf_field()?>
                            <div class="mb-3"><label class="form-label">Crew name</label><input class="form-control" name="name" required placeholder="Residential Crew 1"></div>
                            <div class="mb-3"><label class="form-label">Crew leader</label><select class="form-select" name="crew_leader_id"><option value="">Unassigned</option><?php foreach($users as $user):?><option value="<?=e($user['id'])?>"><?=e($user['name'])?></option><?php endforeach?></select></div>
                            <div class="mb-3"><label class="form-label">Default vehicle</label><select class="form-select" name="default_vehicle_id"><option value="">Unassigned</option><?php foreach($vehicles as $vehicle):?><option value="<?=e($vehicle['id'])?>"><?=e($vehicle['unit_number'].' '.$vehicle['make'].' '.$vehicle['model'])?></option><?php endforeach?></select></div>
                            <div class="mb-3"><label class="form-label">Default territory</label><select class="form-select" name="service_territory_id"><option value="">Unassigned</option><?php foreach($territories as $territory):?><option value="<?=e($territory['id'])?>"><?=e($territory['name'])?></option><?php endforeach?></select></div>
                            <div class="mb-3"><label class="form-label">Color label</label><input type="color" class="form-control form-control-color" name="color_label" value="#1479df"></div>
                            <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                            <button class="btn btn-primary w-100"><i class="fa-solid fa-users me-2"></i>Create Crew</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <?php if(!$crews):?>
                    <div class="alert alert-info">No crews exist yet. Create the first one on the left.</div>
                <?php endif?>
                <div class="row g-3">
                    <?php foreach($crews as $crew):?>
                    <div class="col-12">
                        <div class="card shadow-sm border-start border-4" style="border-left-color:<?=e($crew['color_label'] ?: '#1479df')?>!important">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between gap-3">
                                    <div>
                                        <h3 class="h5 mb-1"><?=e($crew['name'])?> <?php if(!(int)$crew['active']):?><span class="badge text-bg-secondary">Inactive</span><?php endif?></h3>
                                        <div class="text-muted small">
                                            Leader: <?=e($crew['leader_name'] ?: 'Unassigned')?> &middot;
                                            Vehicle: <?=e($crew['vehicle_unit'] ?: 'Unassigned')?> &middot;
                                            Territory: <?=e($crew['territory_name'] ?: 'Unassigned')?>
                                        </div>
                                    </div>
                                    <span class="badge text-bg-primary align-self-start"><?=e($crew['member_count'])?> members</span>
                                </div>
                                <hr>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach($members[(int)$crew['id']] ?? [] as $member):?>
                                    <span class="badge rounded-pill text-bg-light border text-dark d-inline-flex align-items-center gap-2 py-2 px-3">
                                        <i class="fa-solid <?=$member['is_primary']?'fa-star text-warning':'fa-user'?>"></i><?=e($member['name'])?>
                                        <form method="post" action="/portal/workforce/crews/<?=e($crew['id'])?>/members/<?=e($member['user_id'])?>/remove" class="d-inline"><?=csrf_field()?><button class="btn btn-link btn-sm p-0 text-danger" title="Remove"><i class="fa-solid fa-xmark"></i></button></form>
                                    </span>
                                    <?php endforeach?>
                                </div>
                                <form method="post" action="/portal/workforce/crews/<?=e($crew['id'])?>/members" class="row g-2 align-items-end">
                                    <?=csrf_field()?>
                                    <div class="col-md-6"><label class="form-label small">Add employee</label><select class="form-select" name="user_id" required><option value="">Choose...</option><?php foreach($users as $user):?><option value="<?=e($user['id'])?>"><?=e($user['name'])?> (<?=e(str_replace('_',' ',$user['role']))?>)</option><?php endforeach?></select></div>
                                    <div class="col-md-3"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_primary" id="primary<?=e($crew['id'])?>"><label class="form-check-label" for="primary<?=e($crew['id'])?>">Primary member</label></div></div>
                                    <div class="col-md-3"><button class="btn btn-outline-primary w-100">Add member</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach?>
                </div>
            </div>
        </div>
    </section>

    <section class="tab-pane fade" id="skills">
        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card shadow-sm mb-4"><div class="card-body">
                    <h2 class="h5">Create Skill</h2>
                    <form method="post" action="/portal/workforce/skills">
                        <?=csrf_field()?>
                        <div class="mb-3"><label class="form-label">Skill</label><input class="form-control" name="name" required placeholder="Water-fed pole operation"></div>
                        <div class="mb-3"><label class="form-label">Category</label><input class="form-control" name="category" value="Window Cleaning"></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="requires_certification" id="requiresCertification"><label class="form-check-label" for="requiresCertification">Requires certification</label></div>
                        <button class="btn btn-primary w-100">Save skill</button>
                    </form>
                </div></div>
                <div class="card shadow-sm"><div class="card-body">
                    <h2 class="h5">Assign Skill</h2>
                    <form method="post" action="/portal/workforce/skills/assign">
                        <?=csrf_field()?>
                        <div class="mb-3"><label class="form-label">Employee</label><select class="form-select" name="user_id" required><?php foreach($users as $user):?><option value="<?=e($user['id'])?>"><?=e($user['name'])?></option><?php endforeach?></select></div>
                        <div class="mb-3"><label class="form-label">Skill</label><select class="form-select" name="skill_id" required><?php foreach($skills as $skill):?><option value="<?=e($skill['id'])?>"><?=e($skill['category'].' / '.$skill['name'])?></option><?php endforeach?></select></div>
                        <div class="mb-3"><label class="form-label">Level</label><select class="form-select" name="proficiency_level"><option value="learning">Learning</option><option value="qualified" selected>Qualified</option><option value="advanced">Advanced</option><option value="trainer">Trainer</option></select></div>
                        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                        <button class="btn btn-primary w-100">Assign skill</button>
                    </form>
                </div></div>
            </div>
            <div class="col-xl-8">
                <div class="card shadow-sm"><div class="card-body">
                    <div class="table-responsive"><table class="table align-middle">
                        <thead><tr><th>Employee</th><th>Skills and proficiency</th></tr></thead>
                        <tbody>
                        <?php foreach($users as $user):?>
                            <tr><td><strong><?=e($user['name'])?></strong><div class="small text-muted"><?=e(str_replace('_',' ',$user['role']))?></div></td><td>
                            <?php if(empty($skillAssignments[(int)$user['id']])):?><span class="text-muted">No skills recorded</span><?php endif?>
                            <div class="d-flex flex-wrap gap-2">
                            <?php foreach($skillAssignments[(int)$user['id']] ?? [] as $assignment):?>
                                <span class="badge text-bg-light border text-dark py-2 px-3"><?=e($assignment['skill_name'])?> <span class="text-primary"><?=e($assignment['proficiency_level'])?></span>
                                <form method="post" action="/portal/workforce/users/<?=e($user['id'])?>/skills/<?=e($assignment['skill_id'])?>/remove" class="d-inline ms-1"><?=csrf_field()?><button class="btn btn-link btn-sm p-0 text-danger"><i class="fa-solid fa-xmark"></i></button></form></span>
                            <?php endforeach?>
                            </div></td></tr>
                        <?php endforeach?>
                        </tbody>
                    </table></div>
                </div></div>
            </div>
        </div>
    </section>

    <section class="tab-pane fade" id="availability">
        <div class="card shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Next 14 Days</h2><a href="/portal/availability" class="btn btn-sm btn-outline-primary">Manage availability</a></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Employee</th><th>Status</th><th>Hours</th><th>Notes</th></tr></thead><tbody>
            <?php if(!$upcomingAvailability):?><tr><td colspan="5" class="text-muted">No staffing constraints recorded.</td></tr><?php endif?>
            <?php foreach($upcomingAvailability as $row):?><tr><td><?=e($row['availability_date'])?></td><td><?=e($row['user_name'])?></td><td><span class="badge text-bg-<?=$row['status']==='limited'?'warning':'secondary'?>"><?=e(str_replace('_',' ',$row['status']))?></span></td><td><?=e(($row['start_time'] ?: 'All day').($row['end_time'] ? ' - '.$row['end_time'] : ''))?></td><td><?=e($row['notes'])?></td></tr><?php endforeach?>
            </tbody></table></div>
        </div></div>
    </section>
</div>
