<?php
/**
 * Admin dashboard (PG-060). Staff only. Read-only sample figures in the local preview: catalogue, price, order
 * and quote management are NOT BUILT, so every action button is disabled. No write route exists.
 *
 * @var array<string,string> $staff
 * @var list<array{label:string,value:string,note:string}> $stats
 * @var list<array{ref:string,customer:string,state:string,total:int}> $orders
 * @var list<array{name:string,left:int}> $low
 * @var bool $showOrders
 * @var string $roleName
 */
$active = 'overview';
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Dashboard</h1>
  <form method="post" action="/admin/sign-out" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn-outline">Sign out</button></form>
  <p class="lead">Signed in as <?= e($staff['name'] ?? '') ?> (<?= e($staff['role'] ?? '') ?>).</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>

  <div class="account-main">
    <?php if (!$showOrders) : ?>
      <p class="empty-note"><?= e($roleName === '' && ($staff['role'] ?? '') !== 'Owner' ? 'You have no role yet. Ask the owner to give you one.' : 'Use the links on the left for your work.') ?></p>
    <?php endif; ?>
    <ul class="stat-grid" aria-label="Key figures">
      <?php foreach ($stats as $s) : ?>
        <li class="card stat"><span class="card-meta"><?= e($s['label']) ?></span><strong><?= e($s['value']) ?></strong><span class="card-meta"><?= e($s['note']) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <?php if ($showOrders) : ?>
    <section class="card" aria-labelledby="ad-orders">
      <h2 id="ad-orders">Recent orders</h2>
      <p><a href="/admin/orders">See all orders</a></p>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o) : ?>
              <tr>
                <th scope="row"><a href="/admin/orders/<?= e(rawurlencode($o['ref'])) ?>"><?= e($o['ref']) ?></a></th>
                <td><?= e($o['customer']) ?></td>
                <td><span class="badge state-<?= e($o['state']) ?>"><?= e(Belis\Support\PreviewData::stateLabel($o['state'])) ?></span></td>
                <td><?= e(money($o['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($low !== []) : ?>
    <section class="card" aria-labelledby="ad-low">
      <h2 id="ad-low">Low stock</h2>
      <ul class="addr-list">
        <?php foreach ($low as $l) : ?><li><?= e($l['name']) ?> <span class="badge state-failed"><?= e($l['left']) ?> left</span></li><?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <p class="mock-note">Product and quote management are not built yet. Low stock is not tracked because stock is a label, not a count. Sample figures appear only in the local preview.</p>
  </div>
</div>
