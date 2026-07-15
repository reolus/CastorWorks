<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Marketing</h1>
        <p class="text-muted mb-0">Track campaigns, lead sources, coupons, referrals, and attributed revenue.</p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-end">
        <div><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="<?=e($from)?>"></div>
        <div><label class="form-label small">To</label><input type="date" class="form-control" name="to" value="<?=e($to)?>"></div>
        <button class="btn btn-outline-primary">Apply</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Campaigns', number_format($summary['campaigns']), 'fa-bullhorn'],
        ['Leads', number_format($summary['leads']), 'fa-user-plus'],
        ['Won leads', number_format($summary['won']), 'fa-circle-check'],
        ['Campaign cost', money($summary['cost']), 'fa-receipt'],
        ['Attributed revenue', money($summary['attributed_revenue']), 'fa-chart-line'],
        ['Estimated ROI', $summary['roi'] === null ? 'Not available' : number_format($summary['roi'], 1) . '%', 'fa-percent'],
    ];
    foreach ($cards as [$label,$value,$icon]): ?>
        <div class="col-sm-6 col-xl-2">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small"><i class="fa-solid <?=$icon?> me-1"></i><?=e($label)?></div>
                <div class="fs-4 fw-semibold mt-2"><?=e($value)?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">Create campaign</h2>
            <form method="post" action="/portal/marketing/campaigns" class="row g-3">
                <?=csrf_field()?>
                <div class="col-12"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
                <div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="campaign_type"><?php foreach(['digital','email','sms','flyer','door_hanger','referral','seasonal','other'] as $v):?><option value="<?=$v?>"><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach?></select></div>
                <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach(['planned','active','paused','completed','draft'] as $v):?><option value="<?=$v?>"><?=e(ucfirst($v))?></option><?php endforeach?></select></div>
                <div class="col-12"><label class="form-label">Channel or placement</label><input class="form-control" name="channel" placeholder="Facebook, flyer, direct mail, referral"></div>
                <div class="col-md-6"><label class="form-label">Start</label><input type="date" class="form-control" name="start_date"></div>
                <div class="col-md-6"><label class="form-label">End</label><input type="date" class="form-control" name="end_date"></div>
                <div class="col-md-6"><label class="form-label">Budget</label><input type="number" min="0" step="0.01" class="form-control" name="budget"></div>
                <div class="col-md-6"><label class="form-label">Actual cost</label><input type="number" min="0" step="0.01" class="form-control" name="actual_cost"></div>
                <div class="col-12"><label class="form-label">ZIP codes</label><input class="form-control" name="target_postal_codes" placeholder="68048, 68037"></div>
                <div class="col-12"><label class="form-label">Tracking code</label><input class="form-control" name="tracking_code" placeholder="SPRING26"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                <div class="col-12"><button class="btn btn-primary w-100">Create campaign</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Campaign performance</h2><a href="/portal/communications" class="btn btn-sm btn-outline-primary">Email and SMS delivery</a></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Campaign</th><th>Status</th><th>Cost</th><th>Leads</th><th>Won</th><th>Revenue</th><th></th></tr></thead><tbody>
            <?php foreach($campaigns as $c):?><tr>
                <td><strong><?=e($c['name'])?></strong><br><small class="text-muted"><?=e(ucwords(str_replace('_',' ',$c['campaign_type'])))?><?= $c['channel'] ? ' · '.e($c['channel']) : '' ?></small></td>
                <td><span class="badge text-bg-secondary"><?=e($c['status'])?></span></td>
                <td><?=money($c['actual_cost'])?></td><td><?=number_format((int)$c['lead_count'])?></td><td><?=number_format((int)$c['won_leads'])?></td><td><?=money($c['attributed_revenue'])?></td>
                <td><form method="post" action="/portal/marketing/campaigns/<?=$c['id']?>"><?=csrf_field()?><div class="input-group input-group-sm"><select class="form-select" name="status"><?php foreach(['planned','active','paused','completed','cancelled'] as $v):?><option value="<?=$v?>" <?=selected($c['status'],$v)?>><?=$v?></option><?php endforeach?></select><input class="form-control" style="max-width:100px" type="number" step="0.01" min="0" name="actual_cost" value="<?=e($c['actual_cost'])?>"><button class="btn btn-outline-primary">Save</button></div></form></td>
            </tr><?php endforeach?>
            <?php if(!$campaigns):?><tr><td colspan="7" class="text-center text-muted py-4">No campaigns in this date range.</td></tr><?php endif?>
            </tbody></table></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Lead sources</h2></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Source</th><th>Leads</th><th>Won</th><th>Rate</th></tr></thead><tbody><?php foreach($sources as $s):$rate=(int)$s['leads']>0?((int)$s['won']/(int)$s['leads'])*100:0;?><tr><td><?=e($s['source'])?></td><td><?=number_format((int)$s['leads'])?></td><td><?=number_format((int)$s['won'])?></td><td><?=number_format($rate,1)?>%</td></tr><?php endforeach?></tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Coupons</h2></div><div class="card-body border-bottom"><form method="post" action="/portal/marketing/coupons" class="row g-2"><?=csrf_field()?><div class="col-5"><input class="form-control" name="code" placeholder="CODE" required></div><div class="col-7"><input class="form-control" name="name" placeholder="Promotion name" required></div><div class="col-5"><select class="form-select" name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed amount</option></select></div><div class="col-4"><input type="number" min="0" step="0.01" class="form-control" name="discount_value" required></div><div class="col-3 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" checked><label class="form-check-label">Active</label></div></div><div class="col-12"><button class="btn btn-outline-primary w-100">Add coupon</button></div></form></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Code</th><th>Value</th><th>Uses</th></tr></thead><tbody><?php foreach($coupons as $c):?><tr><td><strong><?=e($c['code'])?></strong><br><small><?=e($c['name'])?></small></td><td><?=$c['discount_type']==='percent'?e($c['discount_value']).'%':money($c['discount_value'])?></td><td><?=number_format((int)$c['redemption_count'])?></td></tr><?php endforeach?></tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Recent referrals</h2></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Referrer</th><th>Referral</th><th>Status</th></tr></thead><tbody><?php foreach($referrals as $r):?><tr><td><?=e($r['referrer'])?></td><td><?=e($r['referred_name'])?><br><small><?=e($r['referred_email']?:$r['referred_phone'])?></small></td><td><span class="badge text-bg-secondary"><?=e($r['status'])?></span></td></tr><?php endforeach?><?php if(!$referrals):?><tr><td colspan="3" class="text-center text-muted py-4">No referrals yet.</td></tr><?php endif?></tbody></table></div></div></div>
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Marketing suppression list</h2><span class="text-muted small">Honor opt-outs across campaign sends.</span></div>
    <div class="card-body border-bottom">
        <form method="post" action="/portal/marketing/suppressions" class="row g-2 align-items-end">
            <?=csrf_field()?>
            <div class="col-md-4"><label class="form-label">Email address or phone</label><input class="form-control" name="destination" required></div>
            <div class="col-md-2"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="all">All</option><option value="email">Email</option><option value="sms">SMS</option></select></div>
            <div class="col-md-4"><label class="form-label">Reason</label><input class="form-control" name="reason" placeholder="Customer opt-out, invalid address"></div>
            <div class="col-md-2"><button class="btn btn-outline-danger w-100">Suppress</button></div>
        </form>
    </div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Destination</th><th>Channel</th><th>Reason</th><th>Added</th></tr></thead><tbody>
        <?php foreach($suppressions as $s):?><tr><td><?=e($s['destination'])?></td><td><?=e($s['channel'])?></td><td><?=e($s['reason'])?></td><td><?=e($s['created_at'])?></td></tr><?php endforeach?>
        <?php if(!$suppressions):?><tr><td colspan="4" class="text-center text-muted py-4">No suppressed destinations.</td></tr><?php endif?>
    </tbody></table></div>
</div>
