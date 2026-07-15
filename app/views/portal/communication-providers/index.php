<div class="page-title-row">
    <div>
        <span class="text-muted">Unified communications</span>
        <h1>Communication Providers</h1>
        <p class="text-muted mb-0">Choose providers by message class, priority, limits, and fallback behavior.</p>
    </div>
    <span class="badge text-bg-<?= $fallbackEnabled ? 'success' : 'secondary' ?> align-self-start">Fallback <?= $fallbackEnabled ? 'enabled' : 'disabled' ?></span>
</div>

<?php if ($summary): ?>
<div class="row g-3 mb-4">
<?php foreach ($summary as $row): ?>
    <div class="col-md-6 col-xl-3"><div class="portal-card p-3 h-100">
        <div class="small text-muted"><code><?= e($row['provider_key']) ?></code></div>
        <div class="d-flex justify-content-between mt-2"><span>Sent</span><strong><?= e($row['sent']) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Failed</span><strong><?= e($row['failed']) ?></strong></div>
        <div class="d-flex justify-content-between"><span>SMS parts</span><strong><?= e($row['sms_parts']) ?></strong></div>
    </div></div>
<?php endforeach ?>
</div>
<?php endif ?>

<form method="post" action="/portal/communication-providers" class="mb-4">
<?= csrf_field() ?>
<?php foreach (['email'=>'Email','sms'=>'SMS','topic'=>'Publish / Subscribe'] as $channel=>$heading): ?>
<?php $channelProviders=array_values(array_filter($providers,fn($provider)=>$provider['channel']===$channel)); if(!$channelProviders) continue; ?>
<div class="portal-card p-4 mb-4">
    <h2 class="h5 mb-1"><?= e($heading) ?></h2>
    <p class="text-muted">Lower priority numbers are tried first. A limit of 0 means unlimited.</p>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Provider</th><th>Ready</th><th>On</th><th>Priority</th><th>Transactional</th><th>Marketing</th><th>Fallback</th><th>Daily</th><th>Monthly</th><th>Usage</th><th>Test</th></tr></thead>
        <tbody>
        <?php foreach($channelProviders as $provider): ?>
        <tr>
            <td><strong><?= e($provider['label']) ?></strong><br><code><?= e($provider['provider_key']) ?></code><input type="hidden" name="providers[<?= e($provider['provider_key']) ?>][notes]" value="<?= e($provider['notes']??'') ?>"></td>
            <td><span class="badge text-bg-<?= $provider['configured']?'success':'secondary' ?>"><?= $provider['configured']?'Ready':'Missing config' ?></span></td>
            <td><input class="form-check-input" type="checkbox" name="providers[<?= e($provider['provider_key']) ?>][enabled]" <?= (int)$provider['enabled']===1?'checked':'' ?>></td>
            <td><input class="form-control form-control-sm" style="width:80px" type="number" min="1" max="999" name="providers[<?= e($provider['provider_key']) ?>][priority]" value="<?= e($provider['priority']) ?>"></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="providers[<?= e($provider['provider_key']) ?>][allow_transactional]" <?= (int)$provider['allow_transactional']===1?'checked':'' ?>></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="providers[<?= e($provider['provider_key']) ?>][allow_marketing]" <?= (int)$provider['allow_marketing']===1?'checked':'' ?>></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="providers[<?= e($provider['provider_key']) ?>][allow_fallback]" <?= (int)$provider['allow_fallback']===1?'checked':'' ?>></td>
            <td><input class="form-control form-control-sm" style="width:90px" type="number" min="0" name="providers[<?= e($provider['provider_key']) ?>][daily_limit]" value="<?= e($provider['daily_limit']) ?>"></td>
            <td><input class="form-control form-control-sm" style="width:90px" type="number" min="0" name="providers[<?= e($provider['provider_key']) ?>][monthly_limit]" value="<?= e($provider['monthly_limit']) ?>"></td>
            <td class="small"><?= e($provider['daily_used']) ?> today<br><?= e($provider['monthly_used']) ?> month</td>
            <td><?php if($provider['configured']): ?><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#testProviderModal" data-provider-key="<?= e($provider['provider_key']) ?>" data-provider-label="<?= e($provider['label']) ?>" data-provider-channel="<?= e($provider['channel']) ?>">Test</button><?php endif ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endforeach ?>
<button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save routing policy</button>
</form>

<div class="row g-4 mb-4">
<div class="col-lg-5"><div class="portal-card p-4 h-100">
    <h2 class="h5">Marketing suppression</h2>
    <form method="post" action="/portal/communication-providers/suppress" class="row g-2 mb-3"><?= csrf_field() ?>
        <div class="col-12"><input class="form-control" name="destination" placeholder="Email or mobile number" required></div>
        <div class="col-4"><select class="form-select" name="channel"><option>all</option><option>email</option><option>sms</option></select></div>
        <div class="col-8"><input class="form-control" name="reason" placeholder="Reason"></div>
        <div class="col-12"><button class="btn btn-outline-danger">Add suppression</button></div>
    </form>
    <?php foreach($suppressions as $s): ?><div class="d-flex justify-content-between border-top py-2"><div><strong><?= e($s['destination']) ?></strong><br><small><?= e($s['channel']) ?> · <?= e($s['reason']) ?></small></div><form method="post" action="/portal/communication-providers/suppressions/<?= e($s['id']) ?>/delete"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Remove</button></form></div><?php endforeach ?>
</div></div>
<div class="col-lg-7"><div class="portal-card p-4 h-100">
    <h2 class="h5">Inbound SMS</h2>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Time</th><th>Provider</th><th>From</th><th>Message</th></tr></thead><tbody><?php foreach($inbound as $m): ?><tr><td><?= e($m['received_at']) ?></td><td><code><?= e($m['provider_key']) ?></code></td><td><?= e($m['sender']) ?></td><td><?= e($m['body']) ?></td></tr><?php endforeach ?><?php if(!$inbound): ?><tr><td colspan="4" class="text-muted">No inbound messages.</td></tr><?php endif ?></tbody></table></div>
</div></div>
</div>

<div class="portal-card p-4 mb-4"><h2 class="h5">Delivery receipts</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Time</th><th>Provider</th><th>Message ID</th><th>Status</th><th>Destination</th></tr></thead><tbody><?php foreach($receipts as $r): ?><tr><td><?= e($r['received_at']) ?></td><td><code><?= e($r['provider_key']) ?></code></td><td><?= e($r['provider_message_id']) ?></td><td><span class="badge text-bg-<?= $r['normalized_status']==='delivered'?'success':($r['normalized_status']==='failed'?'danger':'secondary') ?>"><?= e($r['normalized_status']) ?></span></td><td><?= e($r['destination']) ?></td></tr><?php endforeach ?><?php if(!$receipts): ?><tr><td colspan="5" class="text-muted">No receipts received.</td></tr><?php endif ?></tbody></table></div></div>

<div class="portal-card p-4"><h2 class="h5">Recent attempts</h2><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Time</th><th>Class</th><th>Channel</th><th>Provider</th><th>Recipient</th><th>Status</th><th>Delivery</th><th>Parts</th><th>Detail</th></tr></thead><tbody><?php foreach($attempts as $a): ?><tr><td><?= e($a['created_at']) ?></td><td><?= e($a['message_class']??'transactional') ?></td><td><?= e($a['channel']) ?></td><td><code><?= e($a['provider_key']) ?></code></td><td><?= e($a['recipient']) ?></td><td><?= e($a['status']) ?></td><td><?= e($a['delivery_status']??'') ?></td><td><?= e($a['sms_parts']??'') ?></td><td class="small text-muted"><?= e($a['error_message']??'') ?></td></tr><?php endforeach ?></tbody></table></div></div>

<div class="modal fade" id="testProviderModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post" action="/portal/communication-providers/test"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title fs-5">Test provider</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="provider_key" id="testProviderKey"><p class="text-muted" id="testProviderLabel"></p><label class="form-label" id="testRecipientLabel">Recipient</label><input class="form-control" name="recipient" id="testProviderRecipient" required></div><div class="modal-footer"><button class="btn btn-primary">Send test</button></div></form></div></div></div>
<script>document.getElementById('testProviderModal')?.addEventListener('show.bs.modal',e=>{const b=e.relatedTarget,c=b.dataset.providerChannel;document.getElementById('testProviderKey').value=b.dataset.providerKey;document.getElementById('testProviderLabel').textContent=b.dataset.providerLabel;document.getElementById('testRecipientLabel').textContent=c==='email'?'Email address':(c==='topic'?'Topic ARN':'Mobile number');const i=document.getElementById('testProviderRecipient');i.required=c!=='topic';i.value='';});</script>
