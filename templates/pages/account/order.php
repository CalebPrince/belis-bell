<?php
/**
 * Order confirmation (PG-058). Four states: paid, pending, failed, cancelled. Shows only the person's own
 * order. Sample order in the local preview only; real orders are NOT BUILT.
 *
 * @var array<string,mixed> $order
 */
$state = (string) $order['state'];
$headline = [
    'paid' => ['Thank you, your order is confirmed', 'We have emailed you the details and will let you know when it is on its way.'],
    'pending' => ['We are waiting for your payment', 'This can take a few minutes. We will email you as soon as Paystack confirms it. Please do not pay twice.'],
    'failed' => ['Your payment did not go through', 'You have not been charged for this order. You can go back to your cart and try again.'],
    'cancelled' => ['This order was cancelled', 'No payment was taken. You can start a new order from your cart.'],
][$state];
$subtotal = 0;
foreach ($order['lines'] as $l) {
    $subtotal += $l['qty'] * $l['unit'];
}
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/account">My account</a></li><li aria-hidden="true">/</li><li aria-current="page">Order <?= e($order['ref']) ?></li></ol></nav>
  <div class="order-banner state-<?= e($state) ?>" role="status">
    <span class="info-icon"><?= icon($state === 'paid' ? 'check-circle' : ($state === 'pending' ? 'clock' : 'x')) ?></span>
    <div>
      <h1 class="page-title"><?= e($headline[0]) ?></h1>
      <p><?= e($headline[1]) ?></p>
    </div>
  </div>
</div>

<div class="wrap cart-layout">
  <section class="card" aria-labelledby="ord-items">
    <h2 id="ord-items">Order <?= e($order['ref']) ?></h2>
    <p class="card-meta">Placed <?= e($order['placed']) ?> · <?= e(Belis\Support\PreviewData::stateLabel($state)) ?></p>
    <ul class="sum-items">
      <?php foreach ($order['lines'] as $l) : ?>
        <li>
          <span class="sum-name"><?= e($l['name']) ?><br><span class="card-meta"><?= e($l['label']) ?> x <?= e($l['qty']) ?></span></span>
          <span class="sum-line"><?= e(money($l['qty'] * $l['unit'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <dl class="sum-list">
      <div><dt>Subtotal</dt><dd><?= e(money($subtotal)) ?></dd></div>
      <div><dt>Delivery Fee</dt><dd><?= e(money((int) $order['delivery'])) ?></dd></div>
      <div class="sum-total"><dt>Total</dt><dd><?= e(money($subtotal + (int) $order['delivery'])) ?></dd></div>
    </dl>
  </section>

  <aside class="cart-side">
    <section class="info-box">
      <span class="info-icon"><?= icon('map-pin') ?></span>
      <div>
        <h2>Delivering to</h2>
        <?php foreach ($order['address'] as $line) : ?><p><?= e($line) ?></p><?php endforeach; ?>
        <p class="card-meta"><?= e($order['method']) ?></p>
      </div>
    </section>
    <p class="btn-row">
      <?php if ($state === 'failed' || $state === 'cancelled') : ?><a class="btn-primary" href="/cart">Back to cart<?= icon('arrow-right') ?></a><?php endif; ?>
      <a class="btn-outline" href="/shop">Continue Shopping</a>
      <a class="btn-quiet" href="/account">My account</a>
    </p>
    <?php if (is_mock_mode()) : ?><p class="mock-note">Sample order. Add ?status=pending, failed or cancelled to the address to see the other states.</p><?php endif; ?>
  </aside>
</div>
