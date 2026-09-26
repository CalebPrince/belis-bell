<?php
/**
 * Owner-only Settings page (CTL-SET-001). Step one: confirm with an emailed code. Step two: the form. Saved secrets
 * are never shown; a field left empty keeps the current value.
 *
 * @var array<string,string> $owner
 * @var bool $fresh
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $audit
 * @var string $codeError
 * @var bool $mock
 */
$sourceLabel = ['settings' => 'Saved in Settings', 'environment' => 'From the environment file', 'none' => 'Not set'];
$group = '';
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Settings</h1>
  <p class="lead">Keys and connections for Paystack, email and contact. Only the owner can open this page.</p>
</div>

<div class="wrap admin-grid">
  <nav class="account-nav card" aria-label="Admin">
    <ul>
      <li><a href="/admin"><?= icon('house') ?>Overview</a></li>
      <li><a href="/admin/settings" aria-current="page"><?= icon('lock') ?>Settings</a></li>
    </ul>
  </nav>

  <div class="account-main">
    <?php if (!$fresh) : ?>
      <section class="card" aria-labelledby="st-confirm">
        <h2 id="st-confirm">Confirm it is you</h2>
        <p>Settings hold keys that control payments and email, so we ask for a new emailed code each time you open them. The code goes to <?= e($owner['email'] ?? '') ?>.</p>
        <form method="post" action="/admin/settings/code" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-primary">Email me a code</button></form>
        <form method="post" action="/admin/settings/verify" class="form" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="st-code">6 digit code</label>
            <input id="st-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="code-input" required>
            <?php if ($codeError !== '') : ?><p class="field-error" role="alert"><?= e($codeError) ?></p><?php endif; ?>
          </div>
          <button type="submit" class="btn-primary">Confirm</button>
        </form>
        <?php if ($mock) : ?><p class="mock-note">Local development: the code is written to storage/logs/mail.log.</p><?php endif; ?>
      </section>
    <?php else : ?>
      <form method="post" action="/admin/settings" class="account-main" novalidate autocomplete="off">
        <?= csrf_field() ?>
        <?php foreach ($rows as $r) : ?>
          <?php if ($r['group'] !== $group) : ?>
            <?php if ($group !== '') : ?></section><?php endif; ?>
            <?php $group = (string) $r['group']; ?>
            <section class="card form-section" aria-labelledby="grp-<?= e(strtolower($group)) ?>">
              <h2 id="grp-<?= e(strtolower($group)) ?>"><?= e($group) ?></h2>
          <?php endif; ?>
          <div class="field setting">
            <label for="set-<?= e($r['name']) ?>"><?= e($r['label']) ?></label>
            <p class="hint"><?= e($r['help']) ?></p>
            <p class="card-meta">
              <span class="badge"><?= e($sourceLabel[$r['source']] ?? '') ?></span>
              <?php if ($r['shown'] !== '') : ?> Current: <strong><?= e($r['shown']) ?></strong><?php endif; ?>
            </p>
            <input id="set-<?= e($r['name']) ?>" name="<?= e($r['name']) ?>" type="<?= e($r['secret'] ? 'password' : 'text') ?>" value="<?= e($r['value']) ?>" autocomplete="off" spellcheck="false" placeholder="<?= e($r['secret'] ? 'Leave empty to keep the current value' : 'Leave empty to keep as is') ?>" aria-invalid="<?= e($r['error'] !== null ? 'true' : 'false') ?>">
            <?php if ($r['error'] !== null) : ?><p class="field-error" role="alert"><?= e($r['error']) ?></p><?php endif; ?>
            <?php if ($r['source'] === 'settings') : ?>
              <label class="opt"><input type="checkbox" name="clear_<?= e($r['name']) ?>" value="1"><span>Remove the saved value (use the environment file instead)</span></label>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($group !== '') : ?></section><?php endif; ?>
        <p><button type="submit" class="btn-primary">Save changes</button></p>
      </form>
      <form method="post" action="/admin/settings/test-email" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-outline">Send a test email to me</button></form>

      <section class="card" aria-labelledby="st-audit">
        <h2 id="st-audit">Recent activity</h2>
        <?php if ($audit === []) : ?>
          <p class="empty-note">Nothing recorded yet.</p>
        <?php else : ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th scope="col">When (UTC)</th><th scope="col">Who</th><th scope="col">What</th><th scope="col">Setting</th></tr></thead>
              <tbody>
                <?php foreach ($audit as $a) : ?>
                  <tr><td><?= e(gmdate('d M Y H:i', (int) $a['created_at'])) ?></td><td><?= e($a['email'] ?? '') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['target']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
