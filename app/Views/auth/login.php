<main class="auth-card" id="contenido">
    <p class="eyebrow">Mini ERP</p>
    <h1>ERP Agricola</h1>
    <p>Inicia sesión para continuar.</p>
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <form class="stacked" method="post" action="<?= e(url('login')) ?>">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= e($oldEmail ?? '') ?>" required autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <input type="hidden" name="format" value="html">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
        <button>Ingresar</button>
    </form>
</main>
