<?php

declare(strict_types=1);

use DataOps\Security;

$pageTitle = 'Secure sign in';
?>
<section class="login-card">
    <div class="login-intro">
        <div class="eyebrow">Operations control plane</div>
        <h1>Protect the data<br>behind the work.</h1>
        <p>Schedule incremental snapshots, verify every run, and preserve an audit trail from one focused dashboard.</p>
        <div class="login-points">
            <span><i>01</i> Versioned snapshots</span>
            <span><i>02</i> Recovery workflows</span>
            <span><i>03</i> Operational traceability</span>
        </div>
    </div>
    <div class="login-form-wrap">
        <div class="brand compact"><span class="brand-mark"><span></span><span></span><span></span></span>Data Operations</div>
        <h2>Operator sign in</h2>
        <p class="muted">Use the administrator credentials configured in your environment.</p>
        <?php if ($error): ?>
            <div class="alert error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form class="login-form" action="/login" method="post">
            <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
            <label>Email address<input type="email" name="email" autocomplete="username" required autofocus></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="primary-button full" type="submit">Open operations console <span>→</span></button>
        </form>
        <p class="security-note"><span>⌁</span> Sessions use secure cookie controls and CSRF protection.</p>
    </div>
</section>

