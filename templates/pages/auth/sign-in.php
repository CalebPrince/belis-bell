<?php
/**
 * Customer and staff sign-in (PG-055, PG-057). Email and password, then a one-time code emailed to the person
 * (CTL-AUTH-001, CTL-AUTH-002). The page never says which part was wrong.
 *
 * @var bool $admin
 * @var string $error
 * @var string $oldEmail
 */
$error = $error ?? '';
$oldEmail = $oldEmail ?? '';
$self = $admin ? '/admin/sign-in' : '/account/sign-in';
?>
<div class="wrap auth">
  <section class="auth-panel card" aria-labelledby="auth-title">
    <?php if ($admin) : ?><p class="eyebrow">Staff area</p><?php endif; ?>
    <?php if ($admin) : ?>
      <h1 id="auth-title" class="page-title">Staff sign in</h1>
      <p class="lead">Sign in with your staff email. We will email you a code to finish.</p>
    <?php else : ?>
      <h1 id="auth-title" class="page-title">Welcome back</h1>
      <p class="lead">Sign in to check out, track orders and manage your details.</p>
    <?php endif; ?>

    <?php if ($error !== '') : ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e($self) ?>" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" value="<?= e($oldEmail) ?>" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-primary btn-block"><?= icon('lock') ?>Sign in</button>
    </form>

    <?php if (!$admin) : ?>
      <p class="auth-alt">New to Belis Bell? <a href="/account/register">Create an account</a></p>
    <?php else : ?>
      <p class="auth-alt"><a href="/">Back to the store</a></p>
    <?php endif; ?>
    <p class="hint">After your password you will be asked for a 6 digit code we email to you.</p>
  </section>
  <aside class="auth-side" aria-hidden="true"><?= image_html('auth/side', '', '(min-width: 900px) 40vw, 0px', ['decorative' => true, 'placeholder_class' => 'ph-auth']) ?></aside>
</div>
