<?php
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 20);
$total = (int) ($pagination['total'] ?? 0);
$pages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$query = $_GET;
$window = 2;
$from = max(1, $page - $window);
$to = min($pages, $page + $window);
$link = static function (int $number) use ($query): string {
    $query['page'] = $number;

    return '?' . http_build_query($query);
};
?>
<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Paginación">
        <?php if ($page > 1): ?>
            <a href="<?= e($link($page - 1)) ?>" rel="prev">Anterior</a>
        <?php endif; ?>
        <?php if ($from > 1): ?>
            <a href="<?= e($link(1)) ?>">1</a>
            <?php if ($from > 2): ?><span class="ellipsis" aria-hidden="true">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($number = $from; $number <= $to; $number++): ?>
            <?php if ($number === $page): ?>
                <span aria-current="page"><?= e((string) $number) ?></span>
            <?php else: ?>
                <a href="<?= e($link($number)) ?>"><?= e((string) $number) ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($to < $pages): ?>
            <?php if ($to < $pages - 1): ?><span class="ellipsis" aria-hidden="true">…</span><?php endif; ?>
            <a href="<?= e($link($pages)) ?>"><?= e((string) $pages) ?></a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
            <a href="<?= e($link($page + 1)) ?>" rel="next">Siguiente</a>
        <?php endif; ?>
        <span class="pagination-total"><?= e((string) $total) ?> registros</span>
    </nav>
<?php endif; ?>
