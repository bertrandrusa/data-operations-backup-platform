<?php

declare(strict_types=1);

use DataOps\Security;

$pageTitle = $pageTitle ?? 'Data Operations';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> · Backup Platform</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<?php if ($currentUser !== null): ?>
    <header class="topbar">
        <a class="brand" href="/" aria-label="Data Operations dashboard">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
            <span>Data Operations</span>
        </a>
        <div class="user-menu">
            <span class="live"><i></i> Systems online</span>
            <span class="user-email"><?= e($currentUser['email']) ?></span>
            <form action="/logout" method="post">
                <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
                <button class="text-button" type="submit">Sign out</button>
            </form>
        </div>
    </header>
<?php endif; ?>

<main class="<?= $currentUser === null ? 'auth-shell' : 'page-shell' ?>">
    <?= $content ?>
</main>

<footer class="footer">
    <span>Data Operations &amp; Backup Platform</span>
    <span>Docker · PostgreSQL · PHP/Apache · rsync</span>
</footer>
</body>
</html>

