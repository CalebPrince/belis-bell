<?php
/** @var array<string,mixed> $p one product summary row */
$stockLabel = ['in_stock' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'];
$href = '/p/' . rawurlencode((string) $p['slug']);
?>
<article class="card product-card">
  <a href="<?= e($href) ?>" class="card-media" tabindex="-1" aria-hidden="true">
    <?= image_html('products/' . $p['slug'] . '/main', (string) $p['name'], '(min-width: 1024px) 20vw, 60vw', ['decorative' => true, 'placeholder_class' => 'ph-portrait']) ?>
  </a>
  <h3 class="card-title"><a href="<?= e($href) ?>"><?= e($p['name']) ?></a></h3>
  <p class="card-meta"><?= e($p['pack_size']) ?></p>
  <p class="card-price"><?= e(money((int) $p['price_pesewas'])) ?></p>
  <p class="card-stock <?= e($p['stock_status'] === 'out' ? 'is-out' : 'is-in') ?>"><?= e($stockLabel[$p['stock_status']] ?? '') ?></p>
  <?php if (!empty($p['variant_id']) && $p['stock_status'] !== 'out') : ?>
    <form method="post" action="/cart/add" class="card-form">
      <?= csrf_field() ?>
      <input type="hidden" name="variant" value="<?= e($p['variant_id']) ?>">
      <input type="hidden" name="qty" value="1">
      <input type="hidden" name="return_to" value="<?= e(current_url()) ?>">
      <button type="submit" class="btn-primary btn-block"><?= icon('cart') ?>Add to Cart</button>
    </form>
  <?php else : ?>
    <button type="button" disabled class="btn-primary btn-block"><?= icon('cart') ?><?= e($p['stock_status'] === 'out' ? 'Out of stock' : 'Unavailable') ?></button>
  <?php endif; ?>
</article>
