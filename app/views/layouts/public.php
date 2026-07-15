<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? '') ?> | <?= e(app_name()) ?></title>
  <meta name="description" content="Professional exterior cleaning for homes and businesses in Plattsmouth and Cass County, Nebraska.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/site.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/customer-account.css') ?>" rel="stylesheet">
</head>
<body>
<?php require dirname(__DIR__) . '/partials/public-nav.php'; ?>
<main><?= $content ?></main>
<footer class="public-footer py-5 mt-5"><div class="container"><div class="row g-4 align-items-center"><div class="col-lg-5"><img src="<?= asset('img/logo-white.png') ?>" alt="Rock Bluffs Exterior Services"><p class="mt-3 mb-0">Professional exterior cleaning for homes and businesses in Plattsmouth and Cass County, Nebraska.</p></div><div class="col-lg-3"><h5>Services</h5><a href="/services">Window Cleaning</a><a href="/services">House Washing</a><a href="/services">Pressure Washing</a></div><div class="col-lg-4"><h5>Contact</h5><p class="mb-1"><i class="fa-solid fa-location-dot me-2"></i>Plattsmouth, Nebraska</p><p class="mb-1"><i class="fa-solid fa-envelope me-2"></i>exteriorservices@rockbluffs.com</p><p class="small mt-4 mb-0">An operating division of Rock Bluffs, LLC.</p></div></div></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/site.js') ?>"></script>
</body></html>
