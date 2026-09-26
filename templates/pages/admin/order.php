<?php
/**
 * One order for staff: customer, lines, payment history and packing or delivery progress. Opening this page is
 * written to the audit log. Staff cannot change payment status, amounts or prices here.
 *
 * @var array<string,string> $staff
 * @var array<string,mixed> $order
 * @var bool $preview
 */
$active = 'orders';
$stateLabel = ['paid' => 'Paid', 'pending' => 'Payment pending', 'failed' => 'Payment failed', 'cancelled' => 'Cancelled'];
$fulLabel = Belis\Domain\Orders::FULFILMENT;
$status = (string) $order['status'];
$ref = rawurlencode((string) $order['ref']);
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/admin">Admin</a></li><li aria-hidden="true">/</li><li><a href="/admin/orders">Orders</a></li><li aria-hidden="true">/</li><li aria-current="page"><?= e($order['ref']) ?></li></ol></nav>
  <h1 class="page-title page-title-lg">Order <?= e($order['ref']) ?></h1>
  <p class="lead">Placed <?= e(gmdate('d M Y H:i', (int) $order['created_at'])) ?> UTC. <span class="badge state-<?= e($status) ?>"><?= e($stateLabel[$status] ?? '') ?></span></p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>

  <div class="account-main">
    <?php if ((int) $order['needs_review'] === 1) : ?>
      <p class="form-error" role="alert">This order needs review: a payment answer did not match the order amount, currency or reference. Do not send goods until it is checked in the Paystack dashboard.</p>
    <?php endif; ?>

    <section class="card" aria-labelledby="ao-items">
      <h2 id="ao-items">Items</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th scope="col">Product</th><th scope="col">Size</th><th scope="col">Qty</th><th scope="col">Unit</th><th scope="col">Line total</th></tr></thead>
          <tbody>
            <?php foreach ($order['items'] as $i) : ?>
              <tr><th scope="row"><?= e($i['product_name']) ?></th><td><?= e($i['size_label']) ?></td><td><?= e($i['qty']) ?></td><td><?= e(money((int) $i['unit_pesewas'])) ?></td><td><?= e(money((int) $i['line_pesewas'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <dl class="sum-list">
        <div><dt>Subtotal</dt><dd><?= e(money((int) $order['subtotal_pesewas'])) ?></dd></div>
        <div><dt>Delivery (<?= e($order['delivery_method']) ?>)</dt><dd><?= e(money((int) $order['delivery_pesewas'])) ?></dd></div>
        <div class="sum-total"><dt>Total</dt><dd><?= e(money((int) $order['total_pesewas'])) ?></dd></div>
      </dl>
    </section>

    <section class="card" aria-labelledby="ao-ful">
      <h2 id="ao-ful">Packing and delivery</h2>
      <?php if ($status !== 'paid') : ?>
        <p class="empty-note">Only paid orders can be packed or sent. This one is <?= e(strtolower($stateLabel[$status] ?? '')) ?>.</p>
      <?php else : ?>
        <p>Now: <strong><?= e($fulLabel[$order['fulfilment']] ?? '') ?></strong></p>
        <form method="post" action="/admin/orders/<?= e($ref) ?>/fulfilment" class="form-inline">
          <?= csrf_field() ?>
          <label class="sr-only" for="ao-next">Set progress</label>
          <select id="ao-next" name="fulfilment">
            <?php foreach ($fulLabel as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($order['fulfilment'] === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="btn-primary"<?= flag($preview, 'disabled') ?>>Update</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="ao-cust">
      <h2 id="ao-cust">Customer and delivery address</h2>
      <dl class="detail-list">
        <div><dt>Account</dt><dd><?= e($order['customer_name']) ?> (<?= e($order['customer_email']) ?>)</dd></div>
        <div><dt>Deliver to</dt><dd><?= e($order['ship_name']) ?></dd></div>
        <div><dt>Address</dt><dd><?= e($order['ship_street']) ?>, <?= e($order['ship_city']) ?>, <?= e($order['ship_region']) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($order['ship_phone']) ?></dd></div>
        <?php if ((string) ($order['notes'] ?? '') !== '') : ?><div><dt>Notes</dt><dd><?= e($order['notes']) ?></dd></div><?php endif; ?>
      </dl>
      <p class="hint">Customer details are for delivering this order only. Opening this page is recorded.</p>
    </section>

    <section class="card" aria-labelledby="ao-pay">
      <h2 id="ao-pay">Payment</h2>
      <dl class="detail-list">
        <div><dt>Paystack reference</dt><dd><?= e($order['payment_reference']) ?></dd></div>
        <div><dt>Paid at</dt><dd><?= e($order['paid_at'] === null ? 'Not paid' : gmdate('d M Y H:i', (int) $order['paid_at']) . ' UTC') ?></dd></div>
      </dl>
      <?php if ($order['events'] !== []) : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">When (UTC)</th><th scope="col">Source</th><th scope="col">Result</th></tr></thead>
            <tbody>
              <?php foreach ($order['events'] as $ev) : ?>
                <tr><td><?= e(gmdate('d M Y H:i', (int) $ev['created_at'])) ?></td><td><?= e($ev['source']) ?></td><td><?= e($ev['outcome']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else : ?>
        <p class="hint">No payment answers recorded yet.</p>
      <?php endif; ?>
    </section>
    <?php if ($preview) : ?><p class="mock-note">Sample order for the local preview. Changes are switched off.</p><?php endif; ?>
  </div>
</div>
