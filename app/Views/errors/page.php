<?php /** @var int $status */ /** @var string $message */ ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error <?= (int) ($status ?? 500) ?> | ERP Agricola</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=16">
    <script src="<?= e(url('assets/js/modal.js')) ?>?v=12" defer></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<main class="error-shell" id="contenido">
    <dialog class="modal" data-modal role="alertdialog" aria-labelledby="error-page-title" aria-describedby="error-page-message" open>
        <div class="modal-body">
            <p class="modal-eyebrow">Error <?= (int) ($status ?? 500) ?></p>
            <h1 id="error-page-title">No se pudo completar la operación</h1>
            <p class="modal-message" id="error-page-message"><?= e($message ?? 'Ocurrió un error inesperado.') ?></p>
            <div class="modal-actions">
                <a class="button" href="<?= e(url('dashboard')) ?>">Ir al dashboard</a>
                <button type="button" class="ghost" data-history-back>Volver</button>
            </div>
        </div>
    </dialog>
</main>
</body>
</html>
