<?php
/**
 * Checkout (PG-053). Sign-in is required (no guest checkout), so this page only opens for a signed-in customer.
 * One button, Pay with Paystack, which redirects to Paystack's own page: there is no payment method section and
 * no card or mobile money field here. Paystack and orders are NOT BUILT, so the button is disabled. Totals are
 * rebuilt on the server (CTL-BIZ-001).
 *
 * @var array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int} $cart
 * @var array<string,string> $customer
 * @var list<string> $regions
 * @var list<array{key:string,name:string,fee:int,line:string}> $options
 * @var int $fee
 * @var int $total
 * @var list<array{slot:string,name:string}> $channels
 */
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/cart">Cart</a></li><li aria-hidden="true">/</li><li aria-current="page">Checkout</li></ol></nav>
  <h1 class="page-title page-title-lg">Checkout</h1>
  <p class="lead">Signed in as <?= e($customer['email'] ?? '') ?>.</p>
</div>

<div class="wrap cart-layout">
  <form method="post" action="/checkout" class="checkout-main" novalidate>
    <?= csrf_field() ?>
    <section class="card form-section" aria-labelledby="ck-contact">
      <h2 id="ck-contact">Contact</h2>
      <div class="field-grid">
        <div class="field"><label for="ck-name">Full name</label><input id="ck-name" name="name" type="text" autocomplete="name" value="<?= e($customer['name'] ?? '') ?>" required></div>
        <div class="field"><label for="ck-phone">Phone number</label><input id="ck-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" value="<?= e($customer['phone'] ?? '') ?>" required></div>
      </div>
    </section>

    <section class="card form-section" aria-labelledby="ck-address">
      <h2 id="ck-address">Delivery address</h2>
      <div class="field"><label for="ck-street">Street address or landmark</label><input id="ck-street" name="street" type="text" autocomplete="street-address" required></div>
      <div class="field-grid">
        <div class="field"><label for="ck-city">Town or city</label><input id="ck-city" name="city" type="text" autocomplete="address-level2" required></div>
        <div class="field">
          <label for="ck-region">Region</label>
          <select id="ck-region" name="region" autocomplete="address-level1" required>
            <option value="">Select a region</option>
            <?php foreach ($regions as $r) : ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field"><label for="ck-notes">Delivery notes (optional)</label><textarea id="ck-notes" name="notes" rows="3" maxlength="500"></textarea></div>
    </section>

    <section class="card form-section" aria-labelledby="ck-delivery">
      <h2 id="ck-delivery">Delivery method</h2>
      <?php foreach ($options as $i => $o) : ?>
        <label class="opt opt-card">
          <input type="radio" name="delivery" value="<?= e($o['key']) ?>"<?= flag($i === 0, 'checked') ?>>
          <span><strong><?= e($o['name']) ?></strong><br><span class="card-meta"><?= e($o['line']) ?></span></span>
          <span class="opt-price"><?= e(money($o['fee'])) ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (is_mock_mode()) : ?><p class="mock-note">Sample delivery methods and fees.</p><?php endif; ?>
    </section>
  </form>

  <aside class="cart-side" aria-label="Order summary">
    <section class="summary card">
      <h2>Order Summary</h2>
      <ul class="sum-items">
        <?php foreach ($cart['lines'] as $l) : ?>
          <li>
            <span class="cart-photo"><?= image_html('products/' . $l['slug'] . '/main', (string) $l['name'], '56px', ['decorative' => true, 'placeholder_class' => 'ph-thumb']) ?></span>
            <span class="sum-name"><?= e($l['name']) ?><br><span class="card-meta"><?= e($l['label']) ?> x <?= e($l['qty']) ?></span></span>
            <span class="sum-line"><?= e(money((int) $l['line_pesewas'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="sum-list">
        <div><dt>Subtotal</dt><dd><?= e(money($cart['subtotal'])) ?></dd></div>
        <div><dt>Delivery Fee</dt><dd><?= e(money($fee)) ?></dd></div>
        <div class="sum-total"><dt>Total</dt><dd><?= e(money($total)) ?></dd></div>
      </dl>
      <button type="button" class="btn-primary btn-block" disabled><?= icon('lock') ?>Pay with Paystack</button>
      <p class="hint">You will be taken to Paystack's secure page to pay. We never see your card or mobile money details.</p>
      <?php if (is_mock_mode()) : ?><p class="mock-note">Paystack is not connected yet, so nothing can be paid or ordered.</p><?php endif; ?>
      <?php if ($channels !== []) : ?>
        <ul class="pay-logos" aria-label="Accepted on Paystack">
          <?php foreach ($channels as $c) : ?><li><?= image_html($c['slot'], $c['name'], '72px', ['class' => 'pay-logo']) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
