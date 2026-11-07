<?php foreach (($navigation ?? []) as $group => $items): ?>
    <div class="nav-group">
        <p class="nav-title"><?= e($group) ?></p>
        <?php foreach ($items as $item): ?>
            <a class="nav-link"<?= ($active ?? '') === $item['key'] ? ' aria-current="page"' : '' ?> href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
