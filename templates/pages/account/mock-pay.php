<?php
/**
 * PRETEND payment page for local development only (see Payments\MockAdapter). It has no card or mobile money
 * fields. It is not Paystack and proves nothing about Paystack.
 *
 * @var string $reference
 * @var int $amount
 */
?>
<div class="wrap auth pay-step">
  <section class="auth-panel card" aria-labelledby="mock-title">
    <p class="eyebrow">Local development only</p>
    <h1 id="mock-title" class="page-title">Pretend payment page</h1>
    <p class="lead">This stands in for Paystack while you build. Amount: <strong><?= e(money($amount)) ?></strong>.</p>
    <form method="post" action="/mock-pay/<?= e(rawurlencode($reference)) ?>" class="form">
      <?= csrf_field() ?>
      <button type="submit" name="outcome" value="success" class="btn-primary btn-block">Pretend the payment succeeded</button>
      <button type="submit" name="outcome" value="failed" class="btn-outline btn-block">Pretend the payment failed</button>
      <button type="submit" name="outcome" value="walk" class="btn-quiet btn-block">Go back without paying</button>
    </form>
  </section>
</div>
