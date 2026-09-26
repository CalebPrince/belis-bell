<?php
/**
 * Staff order list. Filters are plain GET fields. Customer details are shown to staff only.
 *
 * @var array<string,string> $staff
 * @var array{items:list<array<string,mixed>>,total:int,pages:int,page:int} $result
 * @var array{status:string,fulfilment:string,q:string,page:int} $filters
 * @var bool $preview
 */
$active = 'orders';
$stateLabel = ['paid' => 'Paid', 'pending' => 'Payment pending', 'failed' => 'Payment failed', 'cancelled' => 'Cancelled'];
$fulLabel = Belis\Domain\Orders::FULFILMENT;
$page = $result['page'];
$pages = $result['pages'];
$keep = ['status' => $filters['status'], 'fulfilment' => $filters['fulfilment'], 'q' => $filters['q']];
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Orders</h1>
  <p class="lead"><?= e($result['total']) ?> <?= e($result['total'] === 1 ? 'order' : 'orders') ?><?php if ($filters['status'] !== '' || $filters['fulfilment'] !== '' || $filters['q'] !== '') : ?> match your filters<?php endif; ?>.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>

  <div class="account-main">
    <form method="get" action="/admin/orders" class="card filter-bar" role="search" aria-label="Filter orders">
      <div class="field">
        <label for="of-q">Order number, email or name</label>
        <input id="of-q" name="q" type="search" value="<?= e($filters['q']) ?>" maxlength="60">
      </div>
      <div class="field">
        <label for="of-status">Payment</label>
        <select id="of-status" name="status">
          <option value="">All</option>
          <?php foreach ($stateLabel as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($filters['status'] === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="of-ful">Packing and delivery</label>
        <select id="of-ful" name="fulfilment">
          <option value="">All</option>
          <?php foreach ($fulLabel as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($filters['fulfilment'] === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions"><button type="submit" class="btn-primary">Filter</button><a class="btn-quiet" href="/admin/orders">Clear</a></div>
    </form>

    <section class="card" aria-labelledby="ol-title">
      <h2 id="ol-title" class="sr-only">Order list</h2>
      <?php if ($result['items'] === []) : ?>
        <p class="empty-note">No orders found.</p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">Order</th><th scope="col">Placed (UTC)</th><th scope="col">Customer</th><th scope="col">Payment</th><th scope="col">Packing</th><th scope="col">Total</th></tr></thead>
            <tbody>
              <?php foreach ($result['items'] as $o) : ?>
                <tr>
                  <th scope="row"><a href="/admin/orders/<?= e(rawurlencode((string) $o['ref'])) ?>"><?= e($o['ref']) ?></a><?php if ((int) $o['needs_review'] === 1) : ?> <span class="badge state-failed">Review</span><?php endif; ?></th>
                  <td><?= e(gmdate('d M Y H:i', (int) $o['created_at'])) ?></td>
                  <td><?= e($o['ship_name']) ?><br><span class="card-meta"><?= e($o['email']) ?></span></td>
                  <td><span class="badge state-<?= e($o['status']) ?>"><?= e($stateLabel[$o['status']] ?? '') ?></span></td>
                  <td><?= e($o['status'] === 'paid' ? ($fulLabel[$o['fulfilment']] ?? '') : 'Not paid') ?></td>
                  <td><?= e(money((int) $o['total_pesewas'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1) : ?>
          <nav class="pager" aria-label="Pages">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
              <a class="pg" href="<?= e(query_url('/admin/orders', $keep + ['page' => (string) $i])) ?>"<?= flag($i === $page, 'aria-current="page"') ?>><?= e($i) ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php if ($preview) : ?><p class="mock-note">Sample orders for the local preview. Real orders appear when signed in as staff.</p><?php endif; ?>
  </div>
</div>
