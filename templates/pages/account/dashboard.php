<?php
/**
 * Account dashboard (PG-059). Shows only the signed-in person's own details, orders and addresses. Details, password
 * and saved addresses are real; the local preview person sees sample rows and cannot change anything.
 *
 * @var array<string,string> $customer
 * @var list<array<string,mixed>> $orders
 * @var list<array<string,mixed>> $addresses
 * @var list<string> $regions
 * @var bool $canEdit
 */
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li aria-current="page">My account</li></ol></nav>
  <h1 class="page-title page-title-lg">Hello, <?= e($customer['name'] ?? '') ?></h1>
  <p class="lead">Track your orders and manage your details.</p>
</div>

<div class="wrap account-grid">
  <nav class="account-nav card" aria-label="Account">
    <ul>
      <li><a href="/account" aria-current="page"><?= icon('house') ?>Overview</a></li>
      <li><a href="#orders"><?= icon('cart') ?>My orders</a></li>
      <li><a href="/quotes"><?= icon('mail') ?>My quotes</a></li>
      <li><a href="#addresses"><?= icon('map-pin') ?>Addresses</a></li>
      <li><a href="#details"><?= icon('user') ?>My details</a></li>
    </ul>
  </nav>

  <div class="account-main">
    <section id="orders" class="card" aria-labelledby="acc-orders">
      <h2 id="acc-orders">My orders</h2>
      <?php if ($orders === []) : ?>
        <p class="empty-note">You have not placed an order yet. <a href="/shop">Start shopping</a>.</p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Items</th><th scope="col">Total</th></tr></thead>
            <tbody>
              <?php foreach ($orders as $o) : ?>
                <tr>
                  <th scope="row"><a href="/order/<?= e(rawurlencode($o['ref'])) ?>?status=<?= e($o['state']) ?>"><?= e($o['ref']) ?></a></th>
                  <td><?= e($o['date']) ?></td>
                  <td><span class="badge state-<?= e($o['state']) ?>"><?= e(Belis\Support\PreviewData::stateLabel($o['state'])) ?></span></td>
                  <td><?= e($o['items']) ?></td>
                  <td><?= e(money($o['total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section id="addresses" class="card" aria-labelledby="acc-addr">
      <h2 id="acc-addr">Saved addresses</h2>
      <?php if ($addresses === []) : ?><p class="empty-note">No saved addresses yet.</p><?php endif; ?>
      <ul class="addr-list">
        <?php foreach ($addresses as $a) : ?>
          <li>
            <strong><?= e($a['label']) ?></strong><?php if ($a['default']) : ?> <span class="badge">Default</span><?php endif; ?>
            <?php foreach ($a['lines'] as $line) : ?><br><?= e($line) ?><?php endforeach; ?>
            <?php if (isset($a['id'])) : ?>
              <br>
              <?php if (!$a['default']) : ?><form method="post" action="/account/addresses/<?= e($a['id']) ?>/default" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-quiet">Make default</button></form><?php endif; ?>
              <form method="post" action="/account/addresses/<?= e($a['id']) ?>/delete" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-quiet">Remove</button></form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <details class="fold">
        <summary class="btn-quiet">Add an address</summary>
        <form method="post" action="/account/addresses" class="form" novalidate>
          <?= csrf_field() ?>
          <div class="field"><label for="ad-label">Label</label><input id="ad-label" name="label" type="text" maxlength="40" placeholder="Home" required></div>
          <div class="field-grid">
            <div class="field"><label for="ad-name">Full name</label><input id="ad-name" name="name" type="text" maxlength="120" value="<?= e($customer['name'] ?? '') ?>" required></div>
            <div class="field"><label for="ad-phone">Phone number</label><input id="ad-phone" name="phone" type="tel" inputmode="tel" value="<?= e($customer['phone'] ?? '') ?>" required></div>
          </div>
          <div class="field"><label for="ad-street">Street address or landmark</label><input id="ad-street" name="street" type="text" maxlength="200" required></div>
          <div class="field-grid">
            <div class="field"><label for="ad-city">Town or city</label><input id="ad-city" name="city" type="text" maxlength="80" required></div>
            <div class="field">
              <label for="ad-region">Region</label>
              <select id="ad-region" name="region" required>
                <option value="">Select a region</option>
                <?php foreach ($regions as $r) : ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Save address</button></p>
        </form>
      </details>
    </section>

    <section id="details" class="card" aria-labelledby="acc-details">
      <h2 id="acc-details">My details</h2>
      <dl class="detail-list">
        <div><dt>Email</dt><dd><?= e($customer['email'] ?? '') ?></dd></div>
        <div><dt>Member since</dt><dd><?= e($customer['member_since'] ?? '') ?></dd></div>
      </dl>
      <form method="post" action="/account/details" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field-grid">
          <div class="field"><label for="dt-name">Full name</label><input id="dt-name" name="name" type="text" maxlength="120" value="<?= e($customer['name'] ?? '') ?>" required></div>
          <div class="field"><label for="dt-phone">Phone number</label><input id="dt-phone" name="phone" type="tel" inputmode="tel" value="<?= e($customer['phone'] ?? '') ?>" required></div>
        </div>
        <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Save details</button></p>
      </form>
      <details class="fold">
        <summary class="btn-quiet">Change password</summary>
        <form method="post" action="/account/password" class="form" novalidate>
          <?= csrf_field() ?>
          <div class="field"><label for="pw-cur">Current password</label><input id="pw-cur" name="current" type="password" autocomplete="current-password" required></div>
          <div class="field"><label for="pw-new">New password</label><input id="pw-new" name="password" type="password" autocomplete="new-password" minlength="10" required><p class="hint">At least 10 characters.</p></div>
          <div class="field"><label for="pw-new2">Confirm new password</label><input id="pw-new2" name="password2" type="password" autocomplete="new-password" required></div>
          <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Change password</button></p>
        </form>
      </details>
      <form method="post" action="/account/sign-out" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-outline">Sign out</button></form>
    </section>

    <?php if (!$canEdit) : ?><p class="mock-note">Local preview: sample rows, and nothing here can be changed.</p><?php endif; ?>
  </div>
</div>
