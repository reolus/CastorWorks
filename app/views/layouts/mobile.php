<!doctype html>
<html lang="en" data-bs-theme="<?= e($_COOKIE['rbes_theme'] ?? 'light') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#082c57">
    <title><?= e($title ?? 'Field') ?> | ServiceOS</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/mobile.css') ?>?v=0.25.0" rel="stylesheet">
</head>
<body class="mobile-field-body">
<header class="mobile-field-header">
    <a class="mobile-brand" href="/portal/mobile"><i class="fa-solid fa-mountain-sun"></i><span>ServiceOS Field</span></a>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light" href="/portal/technician/today" title="Classic view"><i class="fa-solid fa-desktop"></i></a>
        <a class="btn btn-sm btn-light" href="/portal"><i class="fa-solid fa-table-columns"></i></a>
    </div>
</header>
<main class="mobile-field-main">
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <?= $content ?>
</main>
<nav class="mobile-bottom-nav">
    <a href="/portal/mobile"><i class="fa-solid fa-route"></i><span>Route</span></a>
    <a href="/portal/timesheets"><i class="fa-solid fa-clock"></i><span>Time</span></a>
    <a href="/portal/notifications"><i class="fa-solid fa-bell"></i><span>Alerts</span></a>
    <a href="/portal/preferences"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
</nav>
<script>window.RBES={csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/mobile.js') ?>?v=0.25.0"></script>
</body>
</html>
