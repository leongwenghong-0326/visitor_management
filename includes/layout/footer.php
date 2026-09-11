<?php
declare(strict_types=1);
?>
</main>
<?php if (auth_check()): ?>
<footer class="border-top py-3 mt-auto bg-light">
    <div class="container-fluid px-3 px-lg-4 d-flex justify-content-between small text-muted">
        <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span>
        <span>Asia/Kuala_Lumpur · v<?= e(APP_VERSION) ?></span>
    </div>
</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($extraScripts)) {
    echo $extraScripts;
} ?>
</body>
</html>
