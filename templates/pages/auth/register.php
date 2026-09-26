<?php
/**
 * Customer registration (PG-056). A confirmation email follows a real registration (CTL-AUTH-001).
 * Registration is NOT BUILT: the button is disabled and the form posts nowhere. The Terms and Privacy tick
 * boxes stay hidden until those pages exist.
 */
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <h1 id="auth-title" class="page-title">Create your account</h1>
    <p class="lead">One account for orders, saved addresses and quick reorders. We will email you to confirm your address.</p>

    <form method="post" action="/account/register" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" autocomplete="name" required>
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required>
      </div>
      <div class="field">
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="pw-hint">
        <p id="pw-hint" class="hint">At least 12 characters.</p>
      </div>
      <div class="field">
        <label for="password2">Confirm password</label>
        <input id="password2" name="password2" type="password" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn-primary btn-block" disabled>Create account</button>
    </form>
    <p class="mock-note">Registration is not available yet. This page shows the layout only.</p>
    <p class="auth-alt">Already have an account? <a href="/account/sign-in">Sign in</a></p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
