<?php
/**
 * Request a quote (signed-in customers). Text only: a list of items with quantities and a message. No files.
 *
 * @var array<string,mixed> $old
 * @var array<string,string> $errors
 * @var bool $canSend
 */
$val = static fn (string $k): string => is_string($old[$k] ?? null) ? $old[$k] : '';
$names = is_array($old['item_name'] ?? null) ? array_values($old['item_name']) : [];
$qtys = is_array($old['item_qty'] ?? null) ? array_values($old['item_qty']) : [];
$notes = is_array($old['item_note'] ?? null) ? array_values($old['item_note']) : [];
$rows = 6;
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/for-businesses">For Businesses</a></li><li aria-hidden="true">/</li><li aria-current="page">Request a quote</li></ol></nav>
  <h1 class="page-title page-title-lg">Request a quote</h1>
  <p class="lead">List what you need and roughly how many. Our team replies here with prices and delivery details, and you can ask questions in the same conversation.</p>
</div>

<div class="wrap quote-wrap">
  <form method="post" action="/quote/new" class="account-main" novalidate>
    <?= csrf_field() ?>
    <?php if (isset($errors['form'])) : ?><p class="form-error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
    <?php if (!$canSend) : ?><p class="mock-note">The local preview person can look but not send anything.</p><?php endif; ?>

    <section class="card form-section" aria-labelledby="qn-about">
      <h2 id="qn-about">About you</h2>
      <div class="field-grid">
        <div class="field">
          <label for="qn-org">Organisation (optional)</label>
          <input id="qn-org" name="org_name" type="text" maxlength="120" value="<?= e($val('org_name')) ?>" autocomplete="organization">
          <?php if (isset($errors['org_name'])) : ?><p class="field-error" role="alert"><?= e($errors['org_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="qn-by">Needed by (optional)</label>
          <input id="qn-by" name="needed_by" type="text" inputmode="numeric" placeholder="2026-12-15" value="<?= e($val('needed_by')) ?>">
          <?php if (isset($errors['needed_by'])) : ?><p class="field-error" role="alert"><?= e($errors['needed_by']) ?></p><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card form-section" aria-labelledby="qn-items">
      <h2 id="qn-items">What you need</h2>
      <?php if (isset($errors['items'])) : ?><p class="field-error" role="alert"><?= e($errors['items']) ?></p><?php endif; ?>
      <?php for ($i = 0; $i < $rows; $i++) : ?>
        <div class="quote-row">
          <div class="field"><label for="qi-n-<?= e($i) ?>">Item <?= e($i + 1) ?></label><input id="qi-n-<?= e($i) ?>" name="item_name[]" type="text" maxlength="160" value="<?= e(is_string($names[$i] ?? null) ? $names[$i] : '') ?>" placeholder="Toilet tissue, 2 ply"></div>
          <div class="field"><label for="qi-q-<?= e($i) ?>">Quantity</label><input id="qi-q-<?= e($i) ?>" name="item_qty[]" type="text" inputmode="numeric" value="<?= e(is_string($qtys[$i] ?? null) ? $qtys[$i] : '') ?>" placeholder="200"></div>
          <div class="field"><label for="qi-t-<?= e($i) ?>">Note (optional)</label><input id="qi-t-<?= e($i) ?>" name="item_note[]" type="text" maxlength="200" value="<?= e(is_string($notes[$i] ?? null) ? $notes[$i] : '') ?>"></div>
        </div>
      <?php endfor; ?>
      <p class="hint">Need more than <?= e($rows) ?> lines? Put the rest in the message below.</p>
    </section>

    <section class="card form-section" aria-labelledby="qn-msg">
      <h2 id="qn-msg">Anything else</h2>
      <div class="field">
        <label for="qn-message">Message (optional)</label>
        <textarea id="qn-message" name="message" rows="5" maxlength="2000"><?= e($val('message')) ?></textarea>
        <?php if (isset($errors['message'])) : ?><p class="field-error" role="alert"><?= e($errors['message']) ?></p><?php endif; ?>
      </div>
      <p class="hint">We cannot take files here yet. Describe your requirements in words, or send a document by WhatsApp or email if we ask for it.</p>
    </section>
    <p><button type="submit" class="btn-primary"<?= flag(!$canSend, 'disabled') ?>>Send request<?= icon('arrow-right') ?></button></p>
  </form>
</div>
