<?php $address = trim((string) $job['full_address']); ?>
<div id="mobileGpsContext" data-job-id="<?= (int)$job['id'] ?>" data-clocked-in="<?= $openTime ? '1' : '0' ?>" data-csrf="<?= e(csrf_token()) ?>" data-gps-enabled="<?= !empty($gpsPolicy['enabled']) ? '1' : '0' ?>" data-gps-interval="<?= (int)($gpsPolicy['update_interval_seconds'] ?? 60) ?>" hidden></div>
<a class="mobile-back" href="/portal/mobile"><i class="fa-solid fa-arrow-left"></i>Today's route</a>
<section class="mobile-job-header">
    <div><small><?= e($job['job_number']) ?></small><h1><?= e($job['display_name']) ?></h1><p><?= e($job['service_summary']) ?></p></div>
    <span class="badge text-bg-primary"><?= e(str_replace('_', ' ', $job['status'])) ?></span>
</section>

<div class="mobile-quick-actions">
    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($address) ?>"><i class="fa-solid fa-diamond-turn-right"></i><span>Navigate</span></a>
    <?php if ($job['phone']): ?><a href="tel:<?= e($job['phone']) ?>"><i class="fa-solid fa-phone"></i><span>Call</span></a><?php endif; ?>
    <?php if ($job['phone']): ?><a href="sms:<?= e($job['phone']) ?>"><i class="fa-solid fa-message"></i><span>Text</span></a><?php endif; ?>
    <a href="#photos"><i class="fa-solid fa-camera"></i><span>Photos</span></a>
</div>
<div id="mobileGpsStatus" class="alert alert-secondary py-2 small" role="status"><i class="fa-solid fa-location-crosshairs"></i> GPS status will appear here while clocked in.</div>

<div class="mobile-section-card">
    <h2><i class="fa-solid fa-circle-info"></i>Job details</h2>
    <p class="mb-2"><strong><?= e($address) ?></strong></p>
    <div class="mobile-detail-grid"><span>Crew</span><strong><?= e($job['crew_name'] ?: 'Individual') ?></strong><span>Vehicle</span><strong><?= e($job['unit_number'] ?: 'Not assigned') ?></strong><span>Scheduled</span><strong><?= e($job['scheduled_start'] ? date('g:i A', strtotime($job['scheduled_start'])) : 'Not scheduled') ?></strong></div>
    <?php if ($job['notes']): ?><div class="mobile-notes mt-3"><?= nl2br(e($job['notes'])) ?></div><?php endif; ?>
</div>

<div class="mobile-section-card">
    <h2><i class="fa-solid fa-stopwatch"></i>Time & arrival</h2>
    <div class="d-grid gap-2">
        <?php if (!$job['check_in_at']): ?>
        <form method="post" action="/portal/jobs/<?= $job['id'] ?>/check-in" class="mobile-location-form"><?= csrf_field() ?><input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy"><button class="btn btn-success btn-lg"><i class="fa-solid fa-location-dot"></i> Check in & start job</button></form>
        <?php else: ?><div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check"></i> Checked in <?= e(date('g:i A', strtotime($job['check_in_at']))) ?></div><?php endif; ?>
        <form method="post" action="/portal/mobile/jobs/<?= $job['id'] ?>/clock"><?= csrf_field() ?><input type="hidden" name="entry_type" value="job"><button class="btn btn-outline-primary w-100"><?= $openTime ? '<i class="fa-solid fa-stop"></i> Stop time' : '<i class="fa-solid fa-play"></i> Start time' ?></button></form>
    </div>
</div>

<div class="mobile-section-card">
    <h2><i class="fa-solid fa-list-check"></i>Checklist</h2>
    <?php if (!$checklist): ?><form method="post" action="/portal/jobs/<?= $job['id'] ?>/checklist/seed"><?= csrf_field() ?><button class="btn btn-outline-primary w-100">Prepare standard checklist</button></form><?php else: ?>
    <form method="post" action="/portal/jobs/<?= $job['id'] ?>/checklist" data-offline-form="checklist-<?= $job['id'] ?>"><?= csrf_field() ?>
        <?php foreach ($checklist as $item): ?><label class="mobile-check-item"><input type="checkbox" name="items[]" value="<?= $item['id'] ?>" <?= checked((bool) $item['completed']) ?>><span><?= e($item['label']) ?></span></label><?php endforeach; ?>
        <button class="btn btn-primary w-100 mt-2">Save checklist</button>
    </form><?php endif; ?>
</div>

<div class="mobile-section-card" id="photos">
    <h2><i class="fa-solid fa-camera"></i>Job photos</h2>
    <form method="post" action="/portal/jobs/<?= $job['id'] ?>/photos" enctype="multipart/form-data" class="mobile-photo-form"><?= csrf_field() ?><select class="form-select" name="photo_type"><option value="before">Before</option><option value="after">After</option><option value="during">During</option><option value="damage">Damage</option><option value="other">Other</option></select><input class="form-control" type="file" name="photo" accept="image/*" capture="environment" required><input class="form-control" name="caption" placeholder="Optional caption"><button class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload photo</button></form>
    <div class="mobile-photo-grid mt-3"><?php foreach ($photos as $photo): ?><figure><img src="/uploads/<?= e($photo['storage_path']) ?>" alt="<?= e($photo['photo_type']) ?> photo"><figcaption><?= e($photo['photo_type']) ?></figcaption></figure><?php endforeach; ?></div>
</div>

<div class="mobile-section-card">
    <h2><i class="fa-solid fa-pen"></i>Field notes</h2>
    <form method="post" action="/portal/mobile/jobs/<?= $job['id'] ?>/notes" data-draft-key="mobile-note-<?= $job['id'] ?>"><?= csrf_field() ?><textarea class="form-control" name="mobile_note" rows="4" placeholder="Record conditions, customer requests, damage, or follow-up work..."></textarea><div class="form-text draft-status">Draft saves on this device.</div><button class="btn btn-outline-primary w-100 mt-2">Save note to job</button></form>
</div>

<div class="mobile-section-card">
    <h2><i class="fa-solid fa-file-signature"></i>Customer signature</h2>
    <?php if ($job['customer_signed_at']): ?><div class="alert alert-success mb-0">Signed by <?= e($job['customer_signature_name']) ?> at <?= e($job['customer_signed_at']) ?></div><?php else: ?>
    <form method="post" action="/portal/jobs/<?= $job['id'] ?>/signature" class="mobile-signature-form"><?= csrf_field() ?><input class="form-control mb-2" name="customer_signature_name" placeholder="Customer name" required><canvas class="mobile-signature-pad"></canvas><input type="hidden" name="customer_signature_data"><div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-outline-secondary signature-clear">Clear</button><button class="btn btn-primary flex-grow-1">Save signature</button></div></form><?php endif; ?>
</div>

<?php if ($invoice): ?><div class="mobile-section-card"><h2><i class="fa-solid fa-file-invoice-dollar"></i>Invoice</h2><div class="mobile-detail-grid"><span>Invoice</span><strong><?= e($invoice['invoice_number']) ?></strong><span>Balance</span><strong><?= money((float) $invoice['balance_due']) ?></strong></div><a class="btn btn-outline-primary w-100 mt-3" href="/portal/invoices/<?= $invoice['id'] ?>">Open invoice & payment</a></div><?php endif; ?>

<div class="mobile-section-card mobile-complete-card <?= $completion['ready'] ? 'ready' : '' ?>">
    <h2><i class="fa-solid fa-flag-checkered"></i>Complete job</h2>
    <?php if (!$completion['ready']): ?><div class="alert alert-warning"><strong>Still required:</strong> <?= e(implode(', ', $completion['missing'])) ?></div><?php else: ?><div class="alert alert-success">All completion requirements are satisfied.</div><?php endif; ?>
    <form method="post" action="/portal/mobile/jobs/<?= $job['id'] ?>/complete"><?= csrf_field() ?><textarea name="completion_summary" class="form-control mb-2" rows="3" placeholder="Completion summary or follow-up recommendations"></textarea><?php if (!$completion['ready'] && Auth::can('owner','crew_leader')): ?><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="override_requirements" value="1"> Override incomplete requirements</label><?php endif; ?><button class="btn btn-success btn-lg w-100" <?= !$completion['ready'] && !Auth::can('owner','crew_leader') ? 'disabled' : '' ?>><i class="fa-solid fa-circle-check"></i> Complete job</button></form>
</div>
