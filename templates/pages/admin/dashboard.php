<?php
/**
 * Admin dashboard (PG-060). Staff only. Read-only sample figures in the local preview: catalogue, price, order
 * and quote management are NOT BUILT, so every action button is disabled. No write route exists.
 *
 * @var array<string,string> $staff
 * @var list<array{label:string,value:string,note:string}> $stats
 * @var list<array{ref:string,customer:string,state:string,total:int}> $orders
 * @var list<array{name:string,left:int}> $low
 */
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Dashboard</h1>
  <form method="post" action="/admin/sign-out" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-outline">Sign out</button></form>
  <p class="lead">Signed in as <?= e($staff['name'] ?? '') ?> (<?= e($staff['role'] ?? '') ?>).</p>
</div>

<div class="wrap admin-grid">
  <nav class="account-nav card" aria-label="Admin">
    <ul>
      <li><a href="/admin" aria-current="page"><?= icon('house') ?>Overview</a></li>
      <li><span class="nav-off"><?= icon('cart') ?>Orders <small>soon</small></span></li>
      <li><span class="nav-off"><?= icon('tag') ?>Products <small>soon</small></span></li>
      <li><span class="nav-off"><?= icon('mail') ?>Quotes <small>soon</small></span></li>
      <li><span class="nav-off"><?= icon('users') ?>Customers <small>soon</small></span></li>
      <?php if (($staff['role'] ?? '') === 'Owner') : ?><li><a href="/admin/settings"><?= icon('lock') ?>Settings</a></li><?php endif; ?>
    </ul>
  </nav>

  <div class="account-main">
    <ul class="stat-grid" aria-label="Key figures">
      <?php foreach ($stats as $s) : ?>
        <li class="card stat"><span class="card-meta"><?= e($s['label']) ?></span><strong><?= e($s['value']) ?></strong><span class="card-meta"><?= e($s['note']) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <section class="card" aria-labelledby="ad-orders">
      <h2 id="ad-orders">Recent orders</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o) : ?>
              <tr>
                <th scope="row"><?= e($o['ref']) ?></th>
                <td><?= e($o['customer']) ?></td>
                <td><span class="badge state-<?= e($o['state']) ?>"><?= e(Belis\Support\PreviewData::stateLabel($o['state'])) ?></span></td>
                <td><?= e(money($o['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card" aria-labelledby="ad-low">
      <h2 id="ad-low">Low stock</h2>
      <ul class="addr-list">
        <?php foreach ($low as $l) : ?><li><?= e($l['name']) ?> <span class="badge state-failed"><?= e($l['left']) ?> left</span></li><?php endforeach; ?>
      </ul>
    </section>

    <p class="mock-note">Order, product and quote management are not built yet, so nothing here can be changed. Sample figures appear only in the local preview.</p>
  </div>
</div>
