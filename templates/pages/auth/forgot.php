<?php
/**
 * Start a password reset (CTL-AUTH-001). The answer is the same whether or not the address has an account.
 *
 * @var bool $admin
 */
$self = $admin ? '/admin/forgot' : '/account/forgot';
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <?php if ($admin) : ?><p class="eyebrow">Staff area</p><?php endif; ?>
    <h1 id="auth-title" class="page-title">Forgot your password?</h1>
    <p class="lead">Enter your email address. If it has an account, we email a 6 digit code to choose a new password.</p>
    <form method="post" action="<?= e($self) ?>" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="fg-email">Email address</label>
        <input id="fg-email" name="email" type="email" autocomplete="email" inputmode="email" required>
      </div>
      <button type="submit" class="btn-primary btn-block">Email me a code</button>
    </form>
    <p class="auth-alt"><a href="<?= e($admin ? '/admin/sign-in' : '/account/sign-in') ?>">Back to sign in</a></p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
