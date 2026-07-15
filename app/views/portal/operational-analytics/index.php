<?php
$s = $summary ?? [];
$revenue = (float) ($s['revenue'] ?? 0);
$miles = (float) ($s['actual_miles'] ?? 0);
$routeHours = (
    (int) ($s['drive_minutes'] ?? 0)
    + (int) ($s['work_minutes'] ?? 0)
    + (int) ($s['idle_minutes'] ?? 0)
) / 60;
$cards = [
    ['Routes', (string) ($s['routes'] ?? 0)],
    ['Revenue', money($revenue)],
    ['Actual miles', number_format($miles, 1)],
    ['Revenue / mile', $miles > 0 ? money($revenue / $miles) : '$0.00'],
    ['Route hours', number_format($routeHours, 1)],
    ['Revenue / hour', $routeHours > 0 ? money($revenue / $routeHours) : '$0.00'],
    ['Fuel estimate', money($s['fuel_cost'] ?? 0)],
    ['Efficiency', number_format((float) ($s['efficiency'] ?? 0), 1) . '%'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
 <div>
  <h1 class="h3 mb-1">Operational Analytics</h1>
  <p class="text-muted mb-0">Route efficiency, travel, labor, fuel, and revenue performance.</p>
 </div>
 <form class="d-flex gap-2" method="get">
  <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
  <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
  <button class="btn btn-primary">Apply</button>
 </form>
</div>

<div class="row g-3 mb-4">
 <?php foreach ($cards as $card): ?>
  <div class="col-sm-6 col-xl-3">
   <div class="card h-100"><div class="card-body">
    <div class="small text-uppercase text-muted fw-semibold"><?= e($card[0]) ?></div>
    <div class="fs-3 fw-bold mt-1"><?= e((string) $card[1]) ?></div>
   </div></div>
  </div>
 <?php endforeach ?>
</div>

<div class="card mb-4">
 <div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-3">
   <h2 class="h5 mb-0">Route results</h2>
   <form method="post" action="/portal/operational-analytics/rebuild" class="d-flex gap-2">
    <?= csrf_field() ?>
    <input class="form-control form-control-sm" type="date" name="date" value="<?= e($to) ?>">
    <button class="btn btn-sm btn-outline-primary">Rebuild date</button>
   </form>
  </div>
  <div class="table-responsive">
   <table class="table align-middle">
    <thead><tr><th>Date</th><th>Crew / Technician</th><th>Jobs</th><th>Miles</th><th>Drive</th><th>Work</th><th>Idle</th><th>Revenue</th><th>Efficiency</th></tr></thead>
    <tbody>
    <?php foreach ($routes as $r): ?>
     <tr>
      <td><?= e($r['route_date']) ?></td>
      <td><?= e($r['crew_name'] ?? $r['technician_name'] ?? 'Unassigned') ?><?php if (!empty($r['unit_number'])): ?><br><small class="text-muted"><?= e($r['unit_number']) ?></small><?php endif ?></td>
      <td><?= e((string) $r['jobs_completed']) ?> / <?= e((string) $r['jobs_total']) ?></td>
      <td><?= number_format((float) $r['actual_miles'], 1) ?></td>
      <td><?= number_format((int) $r['drive_minutes'] / 60, 1) ?>h</td>
      <td><?= number_format((int) $r['work_minutes'] / 60, 1) ?>h</td>
      <td><?= number_format((int) $r['idle_minutes'] / 60, 1) ?>h</td>
      <td><?= money($r['revenue']) ?></td>
      <td><?= number_format((float) $r['efficiency_score'], 1) ?>%</td>
     </tr>
    <?php endforeach ?>
    <?php if (!$routes): ?><tr><td colspan="9" class="text-center text-muted py-4">No analytics have been built for this period.</td></tr><?php endif ?>
    </tbody>
   </table>
  </div>
 </div>
</div>

<div class="row g-4">
 <div class="col-xl-6"><div class="card h-100"><div class="card-body">
  <h2 class="h5">Crew scorecards</h2>
  <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Crew</th><th>Revenue</th><th>Miles</th><th>Efficiency</th></tr></thead><tbody>
  <?php foreach ($crews as $r): ?><tr><td><?= e($r['statistic_date']) ?></td><td><?= e($r['crew_name']) ?></td><td><?= money($r['revenue']) ?></td><td><?= number_format((float) $r['miles'], 1) ?></td><td><?= number_format((float) $r['efficiency_score'], 1) ?>%</td></tr><?php endforeach ?>
  </tbody></table></div>
 </div></div></div>
 <div class="col-xl-6"><div class="card h-100"><div class="card-body">
  <h2 class="h5">Technician scorecards</h2>
  <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Technician</th><th>Jobs</th><th>Revenue</th><th>Efficiency</th></tr></thead><tbody>
  <?php foreach ($technicians as $r): ?><tr><td><?= e($r['statistic_date']) ?></td><td><?= e($r['technician_name']) ?></td><td><?= e((string) $r['jobs_completed']) ?></td><td><?= money($r['revenue']) ?></td><td><?= number_format((float) $r['efficiency_score'], 1) ?>%</td></tr><?php endforeach ?>
  </tbody></table></div>
 </div></div></div>
</div>
