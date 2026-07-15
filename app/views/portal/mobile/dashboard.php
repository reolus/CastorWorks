<section class="mobile-hero">
    <div>
        <small><?= e(date('l, F j')) ?></small>
        <h1>Today's Route</h1>
        <p><?= count($jobs) ?> assigned job<?= count($jobs) === 1 ? '' : 's' ?></p>
    </div>
    <div class="mobile-status-dot <?= $openTime ? 'active' : '' ?>"></div>
</section>

<?php if ($openTime): ?>
<div class="mobile-time-banner">
    <i class="fa-solid fa-stopwatch"></i>
    <div><strong>Clock running</strong><small><?= e($openTime['job_number'] ?: ucfirst($openTime['entry_type'])) ?> since <?= e(date('g:i A', strtotime($openTime['clock_in']))) ?></small></div>
</div>
<?php endif; ?>

<?php if (!$jobs): ?>
<div class="mobile-empty"><i class="fa-regular fa-calendar-check"></i><h2>No jobs assigned today</h2><p>Your route is clear. Try not to look too disappointed.</p></div>
<?php endif; ?>

<div class="mobile-route-list">
<?php foreach ($jobs as $index => $job): ?>
    <?php $address = trim(($job['address1'] ?? '') . ' ' . ($job['city'] ?? '') . ' ' . ($job['state'] ?? '') . ' ' . ($job['postal_code'] ?? '')); ?>
    <article class="mobile-job-card status-<?= e($job['status']) ?>">
        <div class="mobile-route-number"><?= $index + 1 ?></div>
        <div class="mobile-job-card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div><small><?= e($job['scheduled_start'] ? date('g:i A', strtotime($job['scheduled_start'])) : 'Unscheduled') ?></small><h2><?= e($job['display_name']) ?></h2></div>
                <span class="badge text-bg-<?= $job['status'] === 'completed' ? 'success' : ($job['status'] === 'in_progress' ? 'warning' : 'primary') ?>"><?= e(str_replace('_', ' ', $job['status'])) ?></span>
            </div>
            <p class="mobile-service-summary"><?= e($job['service_summary']) ?></p>
            <p class="mobile-address"><i class="fa-solid fa-location-dot"></i><?= e($address) ?></p>
            <div class="mobile-job-meta">
                <?php if ($job['crew_name']): ?><span><i class="fa-solid fa-people-group"></i><?= e($job['crew_name']) ?></span><?php endif; ?>
                <?php if ($job['unit_number']): ?><span><i class="fa-solid fa-truck"></i><?= e($job['unit_number']) ?></span><?php endif; ?>
            </div>
            <div class="mobile-action-row">
                <a class="btn btn-outline-primary" href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($address) ?>"><i class="fa-solid fa-diamond-turn-right"></i>Navigate</a>
                <?php if ($job['phone']): ?><a class="btn btn-outline-secondary" href="tel:<?= e($job['phone']) ?>"><i class="fa-solid fa-phone"></i>Call</a><?php endif; ?>
                <a class="btn btn-primary flex-grow-1" href="/portal/mobile/jobs/<?= $job['id'] ?>">Open</a>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
