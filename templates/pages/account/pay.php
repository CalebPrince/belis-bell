<?php
/**
 * The step between checkout and Paystack. The order exists and is unpaid. The button is a normal link to the
 * provider's own page (only Paystack's hosts, or the local pretend page, are ever linked to).
 *
 * @var array<string,mixed> $order
 * @var string $url
 * @var bool $mock
 */
?>
<div class="wrap auth pay-step">
  <section class="auth-panel card" aria-labelledby="pay-title">
    <p class="eyebrow">Order <?= e($order['ref']) ?></p>
    <h1 id="pay-title" class="page-title">Continue to payment</h1>
    <p class="lead">Your order is saved and waiting for payment of <strong><?= e(money((int) $order['total_pesewas'])) ?></strong>. You will pay on Paystack's secure page, then come back here.</p>
    <p><a class="btn-primary btn-block" href="<?= e($url) ?>"><?= icon('lock') ?>Continue to Paystack<?= icon('arrow-right') ?></a></p>
    <?php if ($mock) : ?><p class="mock-note">Local development: this goes to a pretend payment page, not Paystack.</p><?php endif; ?>
    <p class="hint">Your order is confirmed only after Paystack tells us the payment went through. If you close the page, find the order under My account.</p>
    <p class="auth-alt"><a href="/order/<?= e(rawurlencode((string) $order['ref'])) ?>">View this order</a></p>
  </section>
</div>
