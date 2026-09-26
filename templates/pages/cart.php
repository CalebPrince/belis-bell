<?php
/**
 * Cart page (PG-052). One Proceed to Checkout button, no mobile money button. Every figure is rebuilt on the
 * server from the database (CTL-BIZ-001).
 *
 * @var array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int} $cart
 * @var list<array<string,mixed>> $more
 * @var list<array{icon:string,title:string,line:string}> $trust
 * @var list<string> $deliveryInfo
 * @var list<array{slot:string,name:string}> $channels
 */
$stockLabel = ['in_stock' => 'In Stock', 'low' => 'Low Stock', 'out' => 'Out of Stock'];
?>
<div class="wrap page-head cart-head">
  <div>
    <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/shop">Shop</a></li><li aria-hidden="true">/</li><li aria-current="page">Cart</li></ol></nav>
    <h1 class="page-title page-title-lg">Your Shopping Cart</h1>
    <p class="lead">Review your items and proceed to checkout.</p>
  </div>
  <a class="back-link" href="/shop"><?= icon('arrow-right', 'icon flip') ?>Continue Shopping</a>
</div>

<div class="wrap cart-layout">
  <div class="cart-main">
    <?php if ($cart['lines'] === []) : ?>
      <div class="empty-note">
        <p class="empty-title">Your cart is empty</p>
        <p>Browse the shop or pick a category to get started.</p>
        <p class="btn-row btn-row-center"><a class="btn-primary" href="/shop">Shop Now<?= icon('arrow-right') ?></a><a class="btn-outline" href="/categories">Categories</a></p>
      </div>
    <?php else : ?>
      <div class="cart-table card">
        <div class="cart-row cart-headrow" aria-hidden="true"><span>Product</span><span>Price</span><span>Quantity</span><span>Total</span></div>
        <?php foreach ($cart['lines'] as $l) : ?>
          <div class="cart-row">
            <div class="cart-product">
              <form method="post" action="/cart/remove">
                <?= csrf_field() ?>
                <input type="hidden" name="variant" value="<?= e($l['variant_id']) ?>">
                <button type="submit" class="icon-btn" aria-label="Remove <?= e($l['name']) ?> from cart"><?= icon('trash') ?></button>
              </form>
              <a class="cart-photo" href="/p/<?= e(rawurlencode((string) $l['slug'])) ?>" tabindex="-1" aria-hidden="true"><?= image_html('products/' . $l['slug'] . '/main', (string) $l['name'], '80px', ['decorative' => true, 'placeholder_class' => 'ph-thumb']) ?></a>
              <div>
                <a class="cart-name" href="/p/<?= e(rawurlencode((string) $l['slug'])) ?>"><?= e($l['name']) ?></a>
                <p class="card-meta"><?= e($l['label']) ?></p>
                <p class="card-stock <?= e($l['stock_status'] === 'out' ? 'is-out' : 'is-in') ?>"><?= icon($l['stock_status'] === 'out' ? 'x' : 'check-circle', 'icon icon-sm') ?><?= e($stockLabel[$l['stock_status']] ?? '') ?></p>
              </div>
            </div>
            <p class="cart-price"><span class="sr-only">Price </span><?= e(money((int) $l['unit_pesewas'])) ?></p>
            <form method="post" action="/cart/update" class="cart-qty" data-autosubmit>
              <?= csrf_field() ?>
              <input type="hidden" name="variant" value="<?= e($l['variant_id']) ?>">
              <label class="sr-only" for="qty-<?= e($l['variant_id']) ?>">Quantity for <?= e($l['name']) ?></label>
              <input id="qty-<?= e($l['variant_id']) ?>" type="number" name="qty" min="0" max="<?= e(Belis\Domain\Cart::MAX_QTY) ?>" value="<?= e($l['qty']) ?>" inputmode="numeric">
              <button type="submit" class="btn-quiet sort-go">Update</button>
            </form>
            <p class="cart-total"><span class="sr-only">Total </span><?= e(money((int) $l['line_pesewas'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <ul class="trust-row trust-row-spread" aria-label="Why shop with us">
      <?php foreach ($trust as $t) : ?>
        <li><?= icon($t['icon'], 'icon icon-lg') ?><span><strong><?= e($t['title']) ?></strong><br><?= e($t['line']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <aside class="cart-side" aria-label="Order summary">
    <section class="summary card">
      <h2>Order Summary</h2>
      <dl class="sum-list">
        <div><dt>Subtotal (<?= e($cart['items']) ?> <?= e($cart['items'] === 1 ? 'item' : 'items') ?>)</dt><dd><?= e(money($cart['subtotal'])) ?></dd></div>
        <div><dt>Delivery Fee</dt><dd><?= e($cart['delivery'] === null ? ($cart['lines'] === [] ? 'None' : 'Set at checkout') : money($cart['delivery'])) ?></dd></div>
        <div class="sum-total"><dt>Total</dt><dd><?= e(money($cart['total'])) ?></dd></div>
      </dl>
      <?php if ($cart['lines'] === []) : ?>
        <button type="button" class="btn-primary btn-block" disabled><?= icon('lock') ?>Proceed to Checkout</button>
      <?php else : ?>
        <a class="btn-primary btn-block" href="/checkout"><?= icon('lock') ?>Proceed to Checkout<?= icon('arrow-right') ?></a>
        <p class="hint">You will sign in first if you are not signed in.</p>
      <?php endif; ?>
      <?php if (is_mock_mode()) : ?><p class="mock-note">Sample delivery fee.</p><?php endif; ?>
    </section>

    <section class="info-box" aria-label="Delivery information">
      <span class="info-icon"><?= icon('truck') ?></span>
      <div>
        <h2>Delivery Information</h2>
        <?php foreach ($deliveryInfo as $line) : ?><p><?= e($line) ?></p><?php endforeach; ?>
        <?php if ($deliveryInfo === []) : ?><p>Delivery details are confirmed at checkout.</p><?php endif; ?>
      </div>
    </section>

    <section class="info-box" aria-label="Secure payments">
      <span class="info-icon"><?= icon('shield-check') ?></span>
      <div>
        <h2>Secure Payments</h2>
        <p>Pay on Paystack's secure page with mobile money or card. We never see your payment details.</p>
        <?php if ($channels !== []) : ?>
          <ul class="pay-logos" aria-label="Payment options">
            <?php foreach ($channels as $c) : ?><li><?= image_html($c['slot'], $c['name'], '72px', ['class' => 'pay-logo']) ?></li><?php endforeach; ?>
          </ul>
        <?php elseif (is_mock_mode()) : ?>
          <p class="mock-note">Payment logos appear here once the owner supplies the official marks.</p>
        <?php endif; ?>
      </div>
    </section>
  </aside>
</div>

<?php if ($more !== []) : ?>
  <section class="wrap also-wide" aria-labelledby="also-title">
    <div class="uses-box">
      <div class="also-head"><h2 id="also-title">You May Also Like</h2><a class="view-all" href="/shop">View All Products<?= icon('arrow-right') ?></a></div>
      <ul class="product-grid product-grid-4">
        <?php foreach ($more as $p) : ?><li><?php include __DIR__ . '/../partials/product_card.php'; ?></li><?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>
