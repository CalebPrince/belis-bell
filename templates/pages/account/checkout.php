<?php
/**
 * Checkout (PG-053). Sign-in is required (no guest checkout). One button, Pay with Paystack, which sends the customer
 * to Paystack's own page: there is no payment method section and no card or mobile money field here. Totals are
 * rebuilt on the server (CTL-BIZ-001); the figures below are only a preview of what the server will charge.
 *
 * @var array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int} $cart
 * @var array<string,string> $customer
 * @var array<string,mixed> $old
 * @var array<string,string> $errors
 * @var list<string> $regions
 * @var list<array{key:string,name:string,fee:int,line:string}> $options
 * @var string $chosen
 * @var int $fee
 * @var int $total
 * @var bool $mock
 * @var bool $canOrder
 * @var list<array{slot:string,name:string}> $channels
 * @var list<array<string,mixed>> $saved
 */
$saved = $saved ?? [];
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/cart">Cart</a></li><li aria-hidden="true">/</li><li aria-current="page">Checkout</li></ol></nav>
  <h1 class="page-title page-title-lg">Checkout</h1>
  <p class="lead">Signed in as <?= e($customer['email'] ?? '') ?>.</p>
</div>

<form method="post" action="/checkout" class="wrap cart-layout" novalidate>
  <?= csrf_field() ?>
  <div class="checkout-main">
    <?php if ($errors !== []) : ?><p class="form-error" role="alert">Please fix the highlighted details below.</p><?php endif; ?>
    <section class="card form-section" aria-labelledby="ck-contact">
      <h2 id="ck-contact">Contact</h2>
      <div class="field-grid">
        <div class="field">
          <label for="ck-name">Full name</label>
          <input id="ck-name" name="name" type="text" autocomplete="name" value="<?= e($old['name'] ?? '') ?>" aria-invalid="<?= e(isset($errors['name']) ? 'true' : 'false') ?>" required>
          <?php if (isset($errors['name'])) : ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="ck-phone">Phone number</label>
          <input id="ck-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" value="<?= e($old['phone'] ?? '') ?>" aria-invalid="<?= e(isset($errors['phone']) ? 'true' : 'false') ?>" required>
          <?php if (isset($errors['phone'])) : ?><p class="field-error" role="alert"><?= e($errors['phone']) ?></p><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card form-section" aria-labelledby="ck-address">
      <h2 id="ck-address">Delivery address</h2>
      <?php if ($saved !== []) : ?>
        <p class="hint">Saved addresses:
          <?php foreach ($saved as $a) : ?><a href="/checkout?address=<?= e($a['id']) ?>"><?= e($a['label']) ?></a> <?php endforeach; ?></p>
      <?php endif; ?>
      <div class="field">
        <label for="ck-street">Street address or landmark</label>
        <input id="ck-street" name="street" type="text" autocomplete="street-address" value="<?= e($old['street'] ?? '') ?>" aria-invalid="<?= e(isset($errors['street']) ? 'true' : 'false') ?>" required>
        <?php if (isset($errors['street'])) : ?><p class="field-error" role="alert"><?= e($errors['street']) ?></p><?php endif; ?>
      </div>
      <div class="field-grid">
        <div class="field">
          <label for="ck-city">Town or city</label>
          <input id="ck-city" name="city" type="text" autocomplete="address-level2" value="<?= e($old['city'] ?? '') ?>" aria-invalid="<?= e(isset($errors['city']) ? 'true' : 'false') ?>" required>
          <?php if (isset($errors['city'])) : ?><p class="field-error" role="alert"><?= e($errors['city']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="ck-region">Region</label>
          <select id="ck-region" name="region" autocomplete="address-level1" aria-invalid="<?= e(isset($errors['region']) ? 'true' : 'false') ?>" required>
            <option value="">Select a region</option>
            <?php foreach ($regions as $r) : ?><option value="<?= e($r) ?>"<?= flag(($old['region'] ?? '') === $r, 'selected') ?>><?= e($r) ?></option><?php endforeach; ?>
          </select>
          <?php if (isset($errors['region'])) : ?><p class="field-error" role="alert"><?= e($errors['region']) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="field">
        <label for="ck-notes">Delivery notes (optional)</label>
        <textarea id="ck-notes" name="notes" rows="3" maxlength="500"><?= e($old['notes'] ?? '') ?></textarea>
      </div>
    </section>

    <?php if ($canOrder) : ?>
      <label class="opt"><input type="checkbox" name="save_address" value="1"><span>Save this address for next time</span></label>
    <?php endif; ?>

    <section class="card form-section" aria-labelledby="ck-delivery">
      <h2 id="ck-delivery">Delivery method</h2>
      <?php if (isset($errors['delivery'])) : ?><p class="field-error" role="alert"><?= e($errors['delivery']) ?></p><?php endif; ?>
      <?php foreach ($options as $o) : ?>
        <label class="opt opt-card">
          <input type="radio" name="delivery" value="<?= e($o['key']) ?>" data-fee="<?= e($o['fee']) ?>"<?= flag($chosen === $o['key'], 'checked') ?>>
          <span><strong><?= e($o['name']) ?></strong><br><span class="card-meta"><?= e($o['line']) ?></span></span>
          <span class="opt-price"><?= e(money($o['fee'])) ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (is_mock_mode()) : ?><p class="mock-note">Sample delivery methods and fees.</p><?php endif; ?>
    </section>
  </div>

  <aside class="cart-side" aria-label="Order summary" data-checkout data-subtotal="<?= e($cart['subtotal']) ?>">
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
        <div><dt>Delivery Fee</dt><dd data-fee-out><?= e(money($fee)) ?></dd></div>
        <div class="sum-total"><dt>Total</dt><dd data-total-out><?= e(money($total)) ?></dd></div>
      </dl>
      <?php if ($canOrder) : ?>
        <button type="submit" class="btn-primary btn-block"><?= icon('lock') ?>Pay with Paystack</button>
        <p class="hint">You will be taken to Paystack's secure page to pay. We never see your card or mobile money details.</p>
        <?php if ($mock) : ?><p class="mock-note">Local development: payment is a pretend page, not Paystack.</p><?php endif; ?>
      <?php else : ?>
        <button type="button" class="btn-primary btn-block" disabled><?= icon('lock') ?>Pay with Paystack</button>
        <p class="mock-note">This is the local preview person, who cannot place orders. Sign in with a real account.</p>
      <?php endif; ?>
      <?php if ($channels !== []) : ?>
        <ul class="pay-logos" aria-label="Accepted on Paystack">
          <?php foreach ($channels as $c) : ?><li><?= image_html($c['slot'], $c['name'], '72px', ['class' => 'pay-logo']) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</form>
