<?php
/**
 * Second step of sign-in for customers and staff: the emailed one-time code (CTL-AUTH-001, CTL-AUTH-002).
 * Codes are single use, expire, and lock after 5 wrong tries. In local development the code is written to storage/logs/mail.log.
 *
 * @var bool $admin
 * @var string $error
 */
$error = $error ?? '';
$self = $admin ? '/admin/verify' : '/account/verify';
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <?php if ($admin) : ?><p class="eyebrow">Staff area</p><?php endif; ?>
    <h1 id="auth-title" class="page-title">Check your email</h1>
    <p class="lead">We sent a 6 digit code to your email address. It works once and expires in <?= e($admin ? '5' : '10') ?> minutes.</p>

    <?php if ($error !== '') : ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e($self) ?>" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="code">Verification code</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="code-input" required>
      </div>
      <button type="submit" class="btn-primary btn-block">Verify and continue</button>
    </form>
    <form method="post" action="<?= e($admin ? '/admin/resend' : '/account/resend') ?>" class="resend">
      <?= csrf_field() ?>
      <button type="submit" class="btn-quiet btn-block">Send me a new code</button>
    </form>
    <?php if (is_mock_mode()) : ?><p class="mock-note">Local development: the code is written to storage/logs/mail.log instead of being emailed.</p><?php endif; ?>
    <p class="auth-alt"><a href="<?= e($admin ? '/admin/sign-in' : '/account/sign-in') ?>">Start again</a></p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
