(() => {
  const host = document.getElementById('dispatchMap');
  const data = window.ServiceOSDispatchMap;
  if (!host || !data || !window.L) return;

  const saved = (() => { try { return JSON.parse(localStorage.getItem('serviceos.dispatchMap.viewport') || 'null'); } catch (_) { return null; } })();
  const map = L.map(host, { preferCanvas: true }).setView(saved?.center || [40.999, -95.89], saved?.zoom || 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
  map.on('moveend zoomend', () => localStorage.setItem('serviceos.dispatchMap.viewport', JSON.stringify({ center: [map.getCenter().lat, map.getCenter().lng], zoom: map.getZoom() })));

  const layers = {
    jobs: L.markerClusterGroup({ disableClusteringAtZoom: 15 }),
    properties: L.markerClusterGroup({ disableClusteringAtZoom: 16 }),
    crews: L.layerGroup(),
    vehicles: L.layerGroup(),
    routes: L.layerGroup(),
  };
  layers.jobs.addTo(map); layers.crews.addTo(map); layers.vehicles.addTo(map); layers.routes.addTo(map);
  const allBounds = [];
  const esc = (value) => { const div = document.createElement('div'); div.textContent = value ?? ''; return div.innerHTML; };
  const valid = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;

  data.properties.forEach((p) => {
    const lat = Number(p.latitude), lng = Number(p.longitude); if (!valid(lat, lng)) return;
    allBounds.push([lat, lng]);
    const marker = L.marker([lat, lng], { draggable: true, title: p.display_name });
    marker.bindPopup(`<strong>${esc(p.display_name)}</strong><br>${esc(p.label || 'Property')}<br>${esc(p.address1)}, ${esc(p.city)}<br><small>${esc(p.geocode_status || '')}</small><br><a href="/portal/customers/${Number(p.customer_id)}">Open customer</a>`);
    marker.on('dragend', async () => {
      const point = marker.getLatLng();
      if (!window.confirm(`Save corrected coordinates for ${p.display_name}?`)) { marker.setLatLng([lat, lng]); return; }
      try {
        const body = new URLSearchParams({ csrf_token: data.csrf, latitude: point.lat, longitude: point.lng });
        const response = await fetch(`/portal/properties/${Number(p.id)}/coordinates`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body });
        const result = await response.json(); if (!response.ok || !result.ok) throw new Error(result.error || 'Save failed.');
        marker.bindTooltip('Coordinates saved', { permanent: false }).openTooltip();
      } catch (error) { marker.setLatLng([lat, lng]); window.alert(error.message || 'Unable to save coordinates.'); }
    });
    layers.properties.addLayer(marker);
  });

  const statusClass = (status) => status === 'completed' ? 'completed' : (status === 'in_progress' || status === 'active' ? 'in-progress' : (status === 'scheduled' ? 'scheduled' : 'other'));
  const routeGroups = new Map();
  data.jobs.forEach((job) => {
    const lat = Number(job.latitude), lng = Number(job.longitude); if (!valid(lat, lng)) return;
    allBounds.push([lat, lng]);
    const kind = statusClass(job.status);
    const marker = L.marker([lat, lng], { icon: L.divIcon({ className: 'dispatch-job-icon-wrap', html: `<span class="dispatch-job-icon ${kind}"><i class="fa-solid fa-briefcase"></i></span>`, iconSize: [34, 34], iconAnchor: [17, 17] }) });
    marker.bindPopup(`<strong>${esc(job.job_number)}</strong><br>${esc(job.display_name)}<br>${esc(job.service_summary || '')}<br>${esc(job.address1 || '')}, ${esc(job.city || '')}<br><span class="badge text-bg-secondary">${esc(job.status)}</span><br>${job.crew_name ? `Crew: ${esc(job.crew_name)}<br>` : ''}${job.unit_number ? `Vehicle: ${esc(job.unit_number)}<br>` : ''}<a href="/portal/jobs/${Number(job.id)}">Open job</a>`);
    layers.jobs.addLayer(marker);
    const key = job.crew_id ? `crew-${job.crew_id}` : (job.assigned_user_id ? `user-${job.assigned_user_id}` : 'unassigned');
    if (!routeGroups.has(key)) routeGroups.set(key, []);
    routeGroups.get(key).push({ lat, lng, order: Number(job.route_order || 999), time: job.scheduled_start || '', label: job.crew_name || job.technician_name || 'Unassigned' });
  });
  routeGroups.forEach((stops) => {
    if (stops.length < 2) return;
    stops.sort((a, b) => a.order - b.order || String(a.time).localeCompare(String(b.time)));
    const line = L.polyline(stops.map((s) => [s.lat, s.lng]), { weight: 4, opacity: .68, dashArray: '8 7' }).bindTooltip(stops[0].label);
    layers.routes.addLayer(line);
  });

  if (data.routePlan && Array.isArray(data.routePlanStops) && data.routePlanStops.length > 1) {
    const proposed = data.routePlanStops
      .map((stop) => ({ ...stop, lat: Number(stop.latitude), lng: Number(stop.longitude) }))
      .filter((stop) => valid(stop.lat, stop.lng))
      .sort((a, b) => Number(a.stop_order) - Number(b.stop_order));
    if (proposed.length > 1) {
      const proposalLine = L.polyline(proposed.map((stop) => [stop.lat, stop.lng]), { weight: 6, opacity: .88, color: '#f0ad00' })
        .bindTooltip(`Proposed route v${data.routePlan.version_no}`);
      layers.routes.addLayer(proposalLine);
      proposed.forEach((stop) => L.circleMarker([stop.lat, stop.lng], { radius: 11, weight: 3, color: '#f0ad00', fillColor: '#fff', fillOpacity: 1 })
        .bindTooltip(`${stop.stop_order}. ${esc(stop.display_name)}${Number(stop.is_locked) ? ' (locked)' : ''}`, { permanent: false })
        .addTo(layers.routes));
    }
  }

  data.crews.forEach((crew) => {
    const lat = Number(crew.current_latitude), lng = Number(crew.current_longitude); if (!valid(lat, lng)) return;
    allBounds.push([lat, lng]);
    L.marker([lat, lng], { icon: L.divIcon({ className: '', html: '<span class="dispatch-asset-icon crew"><i class="fa-solid fa-people-group"></i></span>', iconSize: [38,38], iconAnchor: [19,19] }) }).bindPopup(`<strong>${esc(crew.name)}</strong><br>Last update: ${esc(crew.last_location_update || 'Unknown')}`).addTo(layers.crews);
  });
  data.vehicles.forEach((vehicle) => {
    const lat = Number(vehicle.current_latitude), lng = Number(vehicle.current_longitude); if (!valid(lat, lng)) return;
    allBounds.push([lat, lng]);
    L.marker([lat, lng], { icon: L.divIcon({ className: '', html: '<span class="dispatch-asset-icon vehicle"><i class="fa-solid fa-truck"></i></span>', iconSize: [38,38], iconAnchor: [19,19] }) }).bindPopup(`<strong>Vehicle ${esc(vehicle.unit_number)}</strong><br>${esc([vehicle.make,vehicle.model].filter(Boolean).join(' '))}<br>${esc(vehicle.status)}<br>Last update: ${esc(vehicle.last_location_update || 'Unknown')}`).addTo(layers.vehicles);
  });

  document.querySelectorAll('[data-map-layer]').forEach((input) => input.addEventListener('change', () => {
    const layer = layers[input.dataset.mapLayer]; if (!layer) return;
    input.checked ? layer.addTo(map) : map.removeLayer(layer);
  }));
  const routeToggle = document.getElementById('showRoutePreview');
  routeToggle?.addEventListener('change', () => routeToggle.checked ? layers.routes.addTo(map) : map.removeLayer(layers.routes));
  const fit = () => { if (allBounds.length) map.fitBounds(allBounds, { padding: [35,35], maxZoom: 16 }); };
  document.getElementById('fitDispatchMap')?.addEventListener('click', fit);
  if (!saved) fit();
})();
