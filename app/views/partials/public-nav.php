<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="/"><img src="<?= asset('img/logo-primary.png') ?>" alt="Rock Bluffs Exterior Services"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="publicNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link <?= active_nav('/') ?>" href="/">Home</a></li>
        <li class="nav-item"><a class="nav-link <?= active_nav('/services') ?>" href="/services">Services</a></li>
        <li class="nav-item"><a class="nav-link <?= active_nav('/about') ?>" href="/about">About</a></li>
        <li class="nav-item"><a class="nav-link <?= active_nav('/contact') ?>" href="/contact">Contact</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-warning fw-bold px-4" href="/quote">Request a Quote</a></li>
        <li class="nav-item"><a class="btn btn-outline-primary" href="/login"><i class="fa-solid fa-lock me-1"></i> Portal</a></li>
      </ul>
    </div>
  </div>
</nav>
