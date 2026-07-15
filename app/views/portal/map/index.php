<div class="page-title-row dispatch-map-title">
 <div><span class="text-muted">Geographic operations</span><h1>Dispatch Map</h1><p class="text-muted mb-0">Scheduled work, crews, vehicles, properties, and route previews for <?=e($date)?>.</p></div>
 <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="/portal/map-providers"><i class="fa-solid fa-location-crosshairs"></i> Geocoding queue</a><a class="btn btn-primary" href="/portal/dispatch?date=<?=e($date)?>"><i class="fa-solid fa-table-columns"></i> Dispatch board</a></div>
</div>

<?php if($routePlan):?><div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>Route proposal v<?=e($routePlan['version_no'])?></strong> saves an estimated <?=number_format((float)$routePlan['distance_savings_miles'],1)?> miles and <?=number_format((int)$routePlan['duration_savings_minutes'])?> minutes.</div><a class="btn btn-sm btn-warning" href="/portal/routes?date=<?=e($date)?><?= $crewId ? '&crew_id='.(int)$crewId : '' ?>">Review proposal</a></div><?php endif?>

<div class="portal-card dispatch-map-toolbar mb-3">
 <form method="get" action="/portal/map" class="row g-2 align-items-end">
  <div class="col-sm-4 col-lg-2"><label class="form-label">Date</label><input class="form-control" type="date" name="date" value="<?=e($date)?>"></div>
  <div class="col-sm-4 col-lg-3"><label class="form-label">Crew</label><select class="form-select" name="crew_id"><option value="0">All crews</option><?php foreach($crews as $crew):?><option value="<?=$crew['id']?>" <?=$crewId===(int)$crew['id']?'selected':''?>><?=e($crew['name'])?></option><?php endforeach?></select></div>
  <div class="col-sm-4 col-lg-3"><label class="form-label">Job status</label><select class="form-select" name="status"><option value="">All statuses</option><?php foreach($statuses as $row):?><option value="<?=e($row['status'])?>" <?=$statusFilter===$row['status']?'selected':''?>><?=e(ucwords(str_replace('_',' ',$row['status'])))?></option><?php endforeach?></select></div>
  <div class="col-lg-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter"></i> Apply</button></div>
  <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary w-100" id="fitDispatchMap"><i class="fa-solid fa-expand"></i> Fit markers</button></div>
 </form>
 <div class="dispatch-map-layers mt-3" aria-label="Map layers">
  <label><input type="checkbox" data-map-layer="jobs" checked> Jobs <span class="badge text-bg-primary"><?=count($jobs)?></span></label>
  <label><input type="checkbox" data-map-layer="properties"> Properties <span class="badge text-bg-secondary"><?=count($properties)?></span></label>
  <label><input type="checkbox" data-map-layer="crews" checked> Crews</label>
  <label><input type="checkbox" data-map-layer="vehicles" checked> Vehicles</label>
  <label><input type="checkbox" id="showRoutePreview" checked> Route preview</label>
 </div>
</div>

<div class="row g-3 mb-3">
 <div class="col-6 col-xl-3"><div class="metric-card"><div><span>Mapped properties</span><strong><?=number_format((int)($geocodeStats['mapped']??0))?></strong></div><i class="fa-solid fa-map-pin text-primary"></i></div></div>
 <div class="col-6 col-xl-3"><div class="metric-card"><div><span>Unmapped properties</span><strong><?=number_format((int)($geocodeStats['unmapped']??0))?></strong></div><i class="fa-solid fa-location-question text-warning"></i></div></div>
 <div class="col-6 col-xl-3"><div class="metric-card"><div><span>Jobs shown</span><strong><?=number_format(count($jobs))?></strong></div><i class="fa-solid fa-briefcase text-success"></i></div></div>
 <div class="col-6 col-xl-3"><div class="metric-card"><div><span>Geocode failures</span><strong><?=number_format((int)($geocodeStats['failed']??0))?></strong></div><i class="fa-solid fa-triangle-exclamation text-danger"></i></div></div>
</div>

<div class="portal-card p-0 overflow-hidden dispatch-map-shell">
 <div id="dispatchMap" aria-label="Dispatch map"></div>
 <aside class="dispatch-map-legend"><strong>Job status</strong><span><i class="legend-dot status-scheduled"></i> Scheduled</span><span><i class="legend-dot status-in-progress"></i> In progress</span><span><i class="legend-dot status-completed"></i> Completed</span><span><i class="legend-dot status-other"></i> Other</span><small>Drag a property marker to correct its saved location.</small></aside>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<link rel="stylesheet" href="<?=asset('css/dispatch-map.css')?>?v=32.3">
<script>window.ServiceOSDispatchMap=<?=json_encode(['properties'=>$properties,'jobs'=>$jobs,'crews'=>$crews,'vehicles'=>$vehicles,'csrf'=>csrf_token(),'date'=>$date,'routePlan'=>$routePlan,'routePlanStops'=>$routePlanStops],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="<?=asset('js/dispatch-map.js')?>?v=32.3"></script>
