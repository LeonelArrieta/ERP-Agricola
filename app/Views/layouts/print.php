<?php /** @var string $content */ ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Comprobante') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/invoice.css')) ?>?v=1">
    <script src="<?= e(url('assets/js/print.js')) ?>" defer></script>
</head>
<body class="invoice-body">
<div class="invoice-toolbar">
    <button type="button" class="invoice-print-button" data-print>Imprimir</button>
    <a class="invoice-print-link" href="<?= e(url('app/comprobantes/' . (int) $voucher['id_comprobante_fiscal'] . '/pdf')) ?>">Descargar PDF</a>
</div>
<?= $content ?>
</body>
</html>
