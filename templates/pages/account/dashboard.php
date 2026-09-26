<?php
/**
 * Account dashboard (PG-059). Shows only the signed-in person's own details, orders and addresses. Real accounts
 * are NOT BUILT: sample data in the local preview only, and the edit buttons are disabled.
 *
 * @var array<string,string> $customer
 * @var list<array{ref:string,date:string,state:string,total:int,items:int}> $orders
 * @var list<array{label:string,lines:list<string>,default:bool}> $addresses
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
      <li><a href="#addresses"><?= icon('map-pin') ?>Addresses</a></li>
      <li><a href="#details"><?= icon('user') ?>My details</a></li>
    </ul>
  </nav>

  <div class="account-main">
    <section id="details" class="card" aria-labelledby="acc-details">
      <h2 id="acc-details">My details</h2>
      <dl class="detail-list">
        <div><dt>Name</dt><dd><?= e($customer['name'] ?? '') ?></dd></div>
        <div><dt>Email</dt><dd><?= e($customer['email'] ?? '') ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($customer['phone'] ?? '') ?></dd></div>
        <div><dt>Member since</dt><dd><?= e($customer['member_since'] ?? '') ?></dd></div>
      </dl>
      <button type="button" class="btn-quiet" disabled>Edit details</button>
      <form method="post" action="/account/sign-out" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-outline">Sign out</button></form>
    </section>

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
      <ul class="addr-list">
        <?php foreach ($addresses as $a) : ?>
          <li>
            <strong><?= e($a['label']) ?></strong><?php if ($a['default']) : ?> <span class="badge">Default</span><?php endif; ?>
            <?php foreach ($a['lines'] as $line) : ?><br><?= e($line) ?><?php endforeach; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-quiet" disabled>Add an address</button>
    </section>

    <?php if (is_mock_mode()) : ?><p class="mock-note">Orders and saved addresses are not built yet. Sample rows appear only in the local preview.</p><?php endif; ?>
  </div>
</div>
