<?php
/**
 * Customer registration (PG-056). A confirmation email with a code follows registration (CTL-AUTH-001).
 * The Terms and Privacy tick boxes stay hidden until those pages exist. Passwords are never shown again.
 *
 * @var array<string,string> $errors
 * @var array<string,string> $old
 */
$errors = $errors ?? [];
$old = $old ?? [];
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <h1 id="auth-title" class="page-title">Create your account</h1>
    <p class="lead">One account for orders, saved addresses and quick reorders. We will email you a code to confirm your address.</p>

    <?php if (isset($errors['form'])) : ?><p class="form-error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
    <form method="post" action="/account/register" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" autocomplete="name" value="<?= e($old['name'] ?? '') ?>" aria-invalid="<?= e(isset($errors['name']) ? 'true' : 'false') ?>" required>
        <?php if (isset($errors['name'])) : ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="<?= e($old['email'] ?? '') ?>" aria-invalid="<?= e(isset($errors['email']) ? 'true' : 'false') ?>" required>
        <?php if (isset($errors['email'])) : ?><p class="field-error" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" value="<?= e($old['phone'] ?? '') ?>" aria-invalid="<?= e(isset($errors['phone']) ? 'true' : 'false') ?>" required>
        <?php if (isset($errors['phone'])) : ?><p class="field-error" role="alert"><?= e($errors['phone']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" aria-invalid="<?= e(isset($errors['password']) ? 'true' : 'false') ?>" required aria-describedby="pw-hint">
        <?php if (isset($errors['password'])) : ?><p class="field-error" role="alert"><?= e($errors['password']) ?></p><?php endif; ?>
        <p id="pw-hint" class="hint">At least 10 characters. A few random words is a good choice.</p>
      </div>
      <div class="field">
        <label for="password2">Confirm password</label>
        <input id="password2" name="password2" type="password" autocomplete="new-password" aria-invalid="<?= e(isset($errors['password2']) ? 'true' : 'false') ?>" required>
        <?php if (isset($errors['password2'])) : ?><p class="field-error" role="alert"><?= e($errors['password2']) ?></p><?php endif; ?>
      </div>
      <button type="submit" class="btn-primary btn-block">Create account</button>
    </form>
    <p class="auth-alt">Already have an account? <a href="/account/sign-in">Sign in</a></p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
