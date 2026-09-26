<?php
/**
 * Second step of sign-in for customers and staff: the emailed one-time code (CTL-AUTH-001, CTL-AUTH-002).
 * NOT BUILT: no code is sent or checked, the button is disabled and the form posts nowhere.
 *
 * @var bool $admin
 */
$self = $admin ? '/admin/verify' : '/account/verify';
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <?php if ($admin) : ?><p class="eyebrow">Staff area</p><?php endif; ?>
    <h1 id="auth-title" class="page-title">Check your email</h1>
    <p class="lead">We sent a 6 digit code to your email address. It expires in 10 minutes.</p>

    <form method="post" action="<?= e($self) ?>" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="code">Verification code</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="code-input" required>
      </div>
      <button type="submit" class="btn-primary btn-block" disabled>Verify and continue</button>
    </form>
    <p class="mock-note">Codes are not sent yet. This page shows the layout only.</p>
    <p class="auth-alt"><a href="<?= e($admin ? '/admin/sign-in' : '/account/sign-in') ?>">Start again</a></p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
