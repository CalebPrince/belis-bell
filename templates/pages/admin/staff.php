<?php
/**
 * Owner-only staff accounts. Adding or switching accounts needs a fresh emailed code.
 *
 * @var array<string,string> $staff
 * @var list<array<string,mixed>> $members
 * @var array<string,mixed> $old
 * @var array<string,string> $errors
 * @var bool $canEdit
 * @var int $myId
 * @var bool $fresh
 */
$active = 'staff';
$val = static fn (string $k): string => is_string($old[$k] ?? null) ? $old[$k] : '';
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Staff</h1>
  <p class="lead">People who can sign in to this area. Only the owner sees this page.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <?php if (!$canEdit) : ?><p class="mock-note">The local preview person can look but not change anything.</p><?php endif; ?>
    <section class="card" aria-labelledby="sf-list">
      <h2 id="sf-list">Accounts</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Last sign-in (UTC)</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
          <tbody>
            <?php foreach ($members as $m) : ?>
              <tr>
                <th scope="row"><?= e($m['name']) ?><br><span class="card-meta"><?= e($m['email']) ?></span></th>
                <td><?= e($m['role'] === 'owner' ? 'Owner' : ($m['staff_role'] === null ? 'No role yet' : ucfirst((string) $m['staff_role']))) ?></td>
                <td><?= e($m['last_signin'] === null ? 'Never' : gmdate('d M Y H:i', (int) $m['last_signin'])) ?></td>
                <td><span class="badge<?= e((int) $m['is_active'] === 1 ? ' state-paid' : '') ?>"><?= e((int) $m['is_active'] === 1 ? 'On' : 'Off') ?></span></td>
                <td>
                  <?php if ($m['role'] === 'staff' && (int) $m['id'] !== $myId) : ?>
                    <form method="post" action="/admin/staff/<?= e($m['id']) ?>/role" class="inline-form">
                      <?= csrf_field() ?>
                      <label class="sr-only" for="role-<?= e($m['id']) ?>">Role for <?= e($m['name']) ?></label>
                      <select id="role-<?= e($m['id']) ?>" name="staff_role">
                        <?php foreach (['content' => 'Content', 'fulfilment' => 'Fulfilment', 'sales' => 'Sales'] as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($m['staff_role'] === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn-quiet"<?= flag(!$canEdit, 'disabled') ?>>Set role</button>
                    </form>
                    <form method="post" action="/admin/staff/<?= e($m['id']) ?>/active" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="active" value="<?= e((int) $m['is_active'] === 1 ? '0' : '1') ?>">
                      <button type="submit" class="btn-quiet"<?= flag(!$canEdit, 'disabled') ?>><?= e((int) $m['is_active'] === 1 ? 'Switch off' : 'Switch on') ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$fresh && $canEdit) : ?><p class="hint">Changes ask for an emailed code first.</p><?php endif; ?>
    </section>

    <section class="card form-section" aria-labelledby="sf-add">
      <h2 id="sf-add">Add a staff member</h2>
      <form method="post" action="/admin/staff" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="sf-name">Full name</label>
          <input id="sf-name" name="name" type="text" maxlength="120" value="<?= e($val('name')) ?>" required>
          <?php if (isset($errors['name'])) : ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="sf-email">Email address</label>
          <input id="sf-email" name="email" type="email" inputmode="email" value="<?= e($val('email')) ?>" required>
          <?php if (isset($errors['email'])) : ?><p class="field-error" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="sf-role">Role</label>
          <select id="sf-role" name="staff_role" required>
            <option value="">Choose a role</option>
            <?php foreach (['content' => 'Content: products and categories', 'fulfilment' => 'Fulfilment: orders and delivery progress', 'sales' => 'Sales: orders (quotes later)'] as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($val('staff_role') === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <?php if (isset($errors['staff_role'])) : ?><p class="field-error" role="alert"><?= e($errors['staff_role']) ?></p><?php endif; ?>
        </div>
        <p class="hint">They get an email explaining how to choose a password with a code. Each role sees only its own pages. Only the owner changes prices, settings, staff, roles and refunds.</p>
        <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Add staff member</button></p>
      </form>
    </section>
  </div>
</div>
