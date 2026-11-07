<?php foreach (($flashes ?? []) as $flash): ?>
    <?php $type = (string) ($flash['type'] ?? 'info'); ?>
    <?php if ($type === 'error'): ?>
        <?= error_modal($flash['message'] ?? '') ?>
        <noscript><div class="flash flash-error" role="alert"><?= e($flash['message'] ?? '') ?></div></noscript>
    <?php else: ?>
        <div class="flash flash-<?= e($type) ?>" role="<?= $type === 'warning' ? 'alert' : 'status' ?>"><?= e($flash['message'] ?? '') ?></div>
    <?php endif; ?>
<?php endforeach; ?>
