<?php /** @var string $content */ ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'ERP Agricola') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=16">
    <script src="<?= e(url('assets/js/modal.js')) ?>?v=12" defer></script>
    <script src="<?= e(url('assets/js/forms.js')) ?>?v=11" defer></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="auth-shell">
    <?= $content ?>
</div>
</body>
</html>
