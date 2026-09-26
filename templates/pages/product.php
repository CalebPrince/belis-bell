<?php
/**
 * @var array<string,mixed> $product
 * @var list<array<string,mixed>> $related
 * @var string|null $whatsapp
 */
$stockLabel = ['in_stock' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'];
?>
<div class="mx-auto max-w-[1280px] px-4 pt-6">
  <nav aria-label="Breadcrumb" class="text-sm text-muted">
    <ol class="flex flex-wrap gap-x-2">
      <li><a class="hover:underline" href="/">Home</a></li>
      <li aria-hidden="true">/</li>
      <li><a class="hover:underline" href="/c/<?= e(rawurlencode((string) $product['category_slug'])) ?>"><?= e($product['category']) ?></a></li>
      <li aria-hidden="true">/</li>
      <li aria-current="page" class="font-semibold text-ink"><?= e($product['name']) ?></li>
    </ol>
  </nav>

  <div class="mt-4 grid gap-8 lg:grid-cols-2">
    <div class="product-gallery">
      <?= image_html('products/' . $product['slug'] . '/main', (string) $product['name'], '(min-width: 1024px) 50vw, 100vw', ['priority' => true, 'placeholder_class' => 'ph-large', 'placeholder_text' => 'Product photo']) ?>
      <?php foreach ([2, 3, 4] as $n) : ?>
        <?php if (image_exists('products/' . $product['slug'] . '/' . $n)) : ?>
          <?= image_html('products/' . $product['slug'] . '/' . $n, $product['name'] . ', photo ' . $n, '(min-width: 1024px) 25vw, 50vw', ['class' => 'gallery-thumb']) ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div>
      <p class="text-sm font-semibold uppercase tracking-[0.12em] text-muted"><?= e($product['category']) ?></p>
      <h1 class="mt-1 font-display text-3xl font-bold sm:text-4xl"><?= e($product['name']) ?></h1>
      <p class="mt-1 text-muted">Pack size: <?= e($product['pack_size']) ?></p>

      <p class="mt-4 font-display text-4xl font-bold"><?= e(money((int) $product['price_pesewas'])) ?></p>
      <p class="mt-1 text-sm text-muted">Mock price. Delivery is calculated at checkout.</p>
      <p class="mt-2 font-semibold <?= e($product['stock_status'] === 'out' ? 'text-danger' : 'text-leaf') ?>"><?= e($stockLabel[$product['stock_status']] ?? '') ?></p>

      <div class="mt-5 flex flex-wrap gap-3">
        <button type="button" disabled class="btn-primary">Add to cart</button>
        <button type="button" disabled class="btn-quiet">Add to quote</button>
        <?php if ($whatsapp !== null) : ?>
          <a class="btn-quiet" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a>
        <?php endif; ?>
      </div>
      <p class="mt-3 text-sm text-muted">The cart and quote requests are not built yet, so these buttons are switched off.</p>

      <?php if (!empty($product['description'])) : ?>
        <h2 class="mt-8 font-display text-xl font-semibold">About this product</h2>
        <p class="mt-2 max-w-[65ch] whitespace-pre-line"><?= e($product['description']) ?></p>
      <?php endif; ?>
      <?php if (!empty($product['usage_notes'])) : ?>
        <h2 class="mt-6 font-display text-xl font-semibold">Use and safety</h2>
        <p class="mt-2 max-w-[65ch] whitespace-pre-line"><?= e($product['usage_notes']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($related !== []) : ?>
    <section class="mt-14" aria-labelledby="related-title">
      <h2 id="related-title" class="font-display text-2xl font-semibold">More in <?= e($product['category']) ?></h2>
      <ul class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <?php foreach ($related as $p) : ?>
          <li><?php include __DIR__ . '/../partials/product_card.php'; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
