<?php
/**
 * Choose a new password with the emailed code (CTL-AUTH-001). The code works once, expires, and locks after 5 tries.
 * Passwords are never shown again.
 *
 * @var bool $admin
 * @var string $error
 */
$self = $admin ? '/admin/reset' : '/account/reset';
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <?php if ($admin) : ?><p class="eyebrow">Staff area</p><?php endif; ?>
    <h1 id="auth-title" class="page-title">Choose a new password</h1>
    <p class="lead">If the address has an account, we sent it a 6 digit code. Enter it below with your new password.</p>
    <?php if ($error !== '') : ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e($self) ?>" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="rs-code">6 digit code</label>
        <input id="rs-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="code-input" required>
      </div>
      <div class="field">
        <label for="rs-pass">New password</label>
        <input id="rs-pass" name="password" type="password" autocomplete="new-password" minlength="10" required aria-describedby="rs-hint">
        <p id="rs-hint" class="hint">At least 10 characters. A few random words is a good choice.</p>
      </div>
      <div class="field">
        <label for="rs-pass2">Confirm new password</label>
        <input id="rs-pass2" name="password2" type="password" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn-primary btn-block">Change password</button>
    </form>
    <form method="post" action="<?= e($admin ? '/admin/resend' : '/account/resend') ?>" class="resend">
      <?= csrf_field() ?>
      <button type="submit" class="btn-quiet btn-block">Send me a new code</button>
    </form>
    <?php if (is_mock_mode()) : ?><p class="mock-note">Local development: the code is written to storage/logs/mail.log.</p><?php endif; ?>
    <p class="auth-alt"><a href="<?= e($admin ? '/admin/sign-in' : '/account/sign-in') ?>">Back to sign in</a></p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
