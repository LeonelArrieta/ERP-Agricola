<?php /** @var string $content */ ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'ERP Agricola') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=16">
    <script src="<?= e(url('assets/js/modal.js')) ?>?v=12" defer></script>
    <script src="<?= e(url('assets/js/a11y.js')) ?>" defer></script>
    <script src="<?= e(url('assets/js/forms.js')) ?>?v=11" defer></script>
    <script src="<?= e(url('assets/js/confirmations.js')) ?>?v=11" defer></script>
    <script src="<?= e(url('assets/js/line-items.js')) ?>?v=17" defer></script>
    <script src="<?= e(url('assets/js/labor.js')) ?>" defer></script>
    <script src="<?= e(url('assets/js/task-resources.js')) ?>" defer></script>
    <script src="<?= e(url('assets/js/sale-pricing.js')) ?>" defer></script>
    <script src="<?= e(url('assets/js/harvest-unit.js')) ?>" defer></script>
    <script src="<?= e(url('assets/js/dashboard.js')) ?>?v=11" defer></script>
    <script src="<?= e(url('assets/js/billing.js')) ?>?v=14" defer></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="topbar">
    <a class="brand" href="<?= e(url('dashboard')) ?>">ERP Agrícola<small>Gestión agrícola</small></a>
    <details class="nav-mobile">
        <summary>Menú</summary>
        <nav aria-label="Navegación principal (móvil)">
            <?php require __DIR__ . '/../partials/nav.php'; ?>
        </nav>
    </details>
    <div class="user">
        <span><?= e(trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''))) ?></span>
        <span class="rol"><?= e($user['rol'] ?? '') ?></span>
        <form method="post" action="<?= e(url('logout')) ?>">
            <input type="hidden" name="format" value="html">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
            <button class="button ghost small">Cerrar sesión</button>
        </form>
    </div>
</header>
<div class="shell">
    <aside class="sidebar">
        <nav aria-label="Navegación principal">
            <?php require __DIR__ . '/../partials/nav.php'; ?>
        </nav>
    </aside>
    <main class="content" id="contenido">
        <?php require __DIR__ . '/../partials/flash.php'; ?>
        <?= $content ?>
    </main>
</div>
<footer class="footer">
    Mini ERP Agrícola · Sistema interno de gestión · <?= e(date('Y')) ?>
</footer>
</body>
</html>
