<section class="hero-section">
  <div class="hero-overlay"></div>
  <div class="container position-relative py-5">
    <div class="row align-items-center min-vh-75">
      <div class="col-lg-7 text-white py-5">
        <span class="eyebrow">LOCAL. PROFESSIONAL. RELIABLE.</span>
        <h1>Professional Exterior Cleaning for Homes & Businesses</h1>
        <p class="lead">Window cleaning, house washing, pressure washing, and more throughout Plattsmouth, Beaver Lake, and surrounding areas in Cass County.</p>
        <div class="d-flex flex-wrap gap-3 mt-4"><a class="btn btn-warning btn-lg fw-bold" href="/quote">Request a Free Quote</a><a class="btn btn-primary btn-lg" href="tel:+14025476970"><i class="fa-solid fa-phone me-2"></i>Call (402) 547-6970</a></div>
      </div>
      <div class="col-lg-5 d-none d-lg-block"><div class="hero-card"><h3>Clear results. Honest work.</h3><p>Locally owned and backed by the Rock Bluffs Group, LLC.</p>
<ul><li><i class="fa-solid fa-check"></i> Residential & commercial</li><li><i class="fa-solid fa-check"></i> Professional equipment</li>
<li><i class="fa-solid fa-check"></i> Quality work</li>
<li><i class="fa-solid fa-check"></i> Straightforward pricing</li><li><i class="fa-solid fa-check"></i> Flexible schedules</li></ul></div></div>
    </div>
  </div>
</section>
<section class="service-band py-5"><div class="container"><div class="section-heading text-center"><span>OUR SERVICES</span><h2>Exterior care without the weekend sacrifice</h2></div><div class="row g-4 mt-2"><?php foreach ($services as $service): ?><div class="col-md-6 col-xl"><div class="service-card h-100"><div class="service-icon"><i class="fa-solid <?= e($service['icon']) ?>"></i></div><h3><?= e($service['name']) ?></h3><p><?= e($service['description']) ?></p><a href="/services">Learn more <i class="fa-solid fa-arrow-right ms-1"></i></a></div></div><?php endforeach; ?></div></div></section>
<section class="why-section py-5"><div class="container"><div class="row g-5 align-items-center"><div class="col-lg-6"><div class="image-panel"><img src="<?= asset('img/logo-primary.png') ?>" alt="Rock Bluffs Exterior Services"></div></div><div class="col-lg-6"><span class="eyebrow text-primary">THE ROCK BLUFFS STANDARD</span><h2 class="display-6 fw-bold">A cleaner property, handled professionally</h2><p class="lead text-secondary">Clear communication, careful work, and a finished result worth showing the neighbors.</p><div class="row g-3 mt-2"><div class="col-sm-6"><div class="feature-item"><i class="fa-solid fa-shield-halved"></i><div><strong>Professional service</strong><small>Organized, careful, and dependable.</small></div></div></div><div class="col-sm-6"><div class="feature-item"><i class="fa-solid fa-calendar-check"></i><div><strong>Easy scheduling</strong><small>Quotes and appointments without the ritual suffering.</small></div></div></div><div class="col-sm-6"><div class="feature-item"><i class="fa-solid fa-house-circle-check"></i><div><strong>Property protection</strong><small>Your home is treated with respect.</small></div></div></div><div class="col-sm-6"><div class="feature-item"><i class="fa-solid fa-location-dot"></i><div><strong>Locally operated</strong><small>Serving Plattsmouth and Cass County.</small></div></div></div></div></div></div></div></section>
<section class="cta-section"><div class="container py-5 text-center text-white"><h2>Ready to make the outside of your property look new again?</h2><p>Tell us what needs attention. We will prepare a straightforward quote.</p><a class="btn btn-warning btn-lg fw-bold" href="/quote">Get My Free Quote</a></div></section>
