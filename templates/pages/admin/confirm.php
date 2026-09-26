<?php
/**
 * Fresh emailed code before a sensitive action such as changing a price.
 *
 * @var array<string,string> $staff
 * @var string $next
 * @var string $error
 * @var bool $mock
 */
$active = '';
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Confirm it is you</h1>
  <p class="lead">Prices and keys are sensitive, so we ask for a new emailed code first. It stays valid for 10 minutes.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <section class="card" aria-labelledby="cf-title">
      <h2 id="cf-title">Email code</h2>
      <p>The code goes to <?= e($staff['email'] ?? '') ?>.</p>
      <form method="post" action="/admin/confirm/code" class="inline-form"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>"><button type="submit" class="btn-primary">Email me a code</button></form>
      <form method="post" action="/admin/confirm" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="cf-code">6 digit code</label>
          <input id="cf-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="code-input" required>
          <?php if ($error !== '') : ?><p class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="btn-primary">Confirm and continue</button>
      </form>
      <?php if ($mock) : ?><p class="mock-note">Local development: the code is written to storage/logs/mail.log.</p><?php endif; ?>
    </section>
  </div>
</div>
