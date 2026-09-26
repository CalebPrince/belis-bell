<?php
/**
 * Product page structure from the owner's mockup (PG-039). Sizes (PG-040), bulk panel (PG-042) and extra
 * content (PG-043). All text beyond name, size and price is mock until supplied. The browser only previews
 * prices; the server sets them (CTL-BIZ-001).
 *
 * @var array<string,mixed> $product
 * @var string $summary
 * @var list<array{id:int,label:string,price_pesewas:int,stock_status:string,tiers:list<array{min_qty:int,unit_price_pesewas:int}>}> $variants
 * @var int $selected
 * @var list<array<string,mixed>> $related
 * @var list<array{label:string,value:string}> $specs
 * @var list<string> $features
 * @var list<array{icon:string,title:string,line:string}> $highlights
 * @var list<array{slug:string,label:string}> $uses
 * @var list<array{icon:string,title:string,line:string}> $trust
 * @var list<string> $deliveryReturns
 * @var string|null $whatsapp
 */
$stockLabel = ['in_stock' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'];
$slug = (string) $product['slug'];
$sel = $variants[$selected];
$photos = ['products/' . $slug . '/main'];
foreach ([2, 3, 4, 5] as $n) {
    if (image_exists('products/' . $slug . '/' . $n)) {
        $photos[] = 'products/' . $slug . '/' . $n;
    }
}
$rows = Belis\Domain\Pricing::rows((int) $sel['price_pesewas'], $sel['tiers']);
$tierData = static function (array $tiers): string {
    return implode(',', array_map(static fn (array $t): string => (int) $t['min_qty'] . ':' . (int) $t['unit_price_pesewas'], $tiers));
};
?>
<div class="wrap page-head pdp-head">
  <nav aria-label="Breadcrumb" class="crumbs">
    <ol>
      <li><a href="/">Home</a></li><li aria-hidden="true">/</li>
      <li><a href="/shop">Shop</a></li><li aria-hidden="true">/</li>
      <li><a href="/c/<?= e(rawurlencode((string) $product['category_slug'])) ?>"><?= e($product['category']) ?></a></li><li aria-hidden="true">/</li>
      <li aria-current="page"><?= e($product['name']) ?></li>
    </ol>
  </nav>
</div>

<div class="wrap pdp" data-product data-base="<?= e((int) $sel['price_pesewas']) ?>">
  <div class="pdp-gallery" data-gallery>
    <?php if (count($photos) > 1) : ?>
      <ul class="thumbs" aria-label="Product photos">
        <?php foreach ($photos as $i => $slot) : ?>
          <li><button type="button" class="thumb" data-thumb="<?= e($i) ?>" aria-label="Show photo <?= e($i + 1) ?>"<?= flag($i === 0, 'aria-current="true"') ?>><?= image_html($slot, '', '96px', ['decorative' => true, 'placeholder_class' => 'ph-square']) ?></button></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div class="main-photo">
      <?php foreach ($photos as $i => $slot) : ?>
        <figure class="photo" data-photo="<?= e($i) ?>">
          <?= image_html($slot, $product['name'] . ($i === 0 ? '' : ', photo ' . ($i + 1)), '(min-width: 1024px) 45vw, 100vw', ['priority' => $i === 0, 'placeholder_class' => 'ph-photo', 'placeholder_text' => 'Product photo']) ?>
        </figure>
      <?php endforeach; ?>
      <button type="button" class="expand-btn" data-expand aria-label="View photo larger"><?= icon('expand') ?></button>
    </div>
  </div>

  <div class="pdp-info">
    <p class="eyebrow"><a href="/c/<?= e(rawurlencode((string) $product['category_slug'])) ?>"><?= e($product['category']) ?></a></p>
    <h1 class="pdp-title"><?= e($product['name']) ?></h1>
    <?php if ($summary !== '') : ?><p class="pdp-summary"><?= e($summary) ?></p><?php endif; ?>

    <p class="pdp-price" data-price aria-live="polite"><?= e(money((int) $sel['price_pesewas'])) ?></p>
    <p class="pdp-stock <?= e($sel['stock_status'] === 'out' ? 'is-out' : 'is-in') ?>" data-stock><?= e($stockLabel[$sel['stock_status']] ?? '') ?></p>
    <?php if (is_mock_mode()) : ?><p class="mock-note">Sample sizes and prices.</p><?php endif; ?>

    <?php if (count($variants) > 1) : ?>
      <fieldset class="sizes">
        <legend>Size</legend>
        <div class="size-list">
          <?php foreach ($variants as $i => $v) : ?>
            <a class="size-opt" href="<?= e(query_url('/p/' . rawurlencode($slug), ['size' => (int) $v['id'] > 0 && $i !== 0 ? (int) $v['id'] : null])) ?>"
               data-size data-variant="<?= e((int) $v['id']) ?>" data-label="<?= e($v['label']) ?>" data-price="<?= e((int) $v['price_pesewas']) ?>" data-stock="<?= e($v['stock_status']) ?>" data-tiers="<?= e($tierData($v['tiers'])) ?>"<?= flag($i === $selected, 'aria-current="true"') ?>>
              <span class="size-name"><?= e($v['label']) ?></span>
              <span class="size-price"><?= e(money((int) $v['price_pesewas'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endif; ?>

    <form method="post" action="/cart/add" class="buy-row">
      <?= csrf_field() ?>
      <input type="hidden" name="variant" value="<?= e((int) $sel['id']) ?>" data-variant-input>
      <input type="hidden" name="return_to" value="<?= e(current_url()) ?>">
      <div class="qty" data-qty>
        <button type="button" class="qty-btn" data-step="-1" aria-label="Decrease quantity"><?= icon('minus') ?></button>
        <label for="qty" class="sr-only">Quantity</label>
        <input id="qty" type="number" name="qty" inputmode="numeric" min="1" max="100000" value="1">
        <button type="button" class="qty-btn" data-step="1" aria-label="Increase quantity"><?= icon('plus') ?></button>
      </div>
      <?php if ((int) $sel['id'] > 0 && $sel['stock_status'] !== 'out') : ?>
        <button type="submit" class="btn-primary btn-buy"><?= icon('cart') ?>Add to Cart</button>
      <?php else : ?>
        <button type="button" class="btn-primary btn-buy" disabled><?= icon('cart') ?><?= e($sel['stock_status'] === 'out' ? 'Out of stock' : 'Unavailable') ?></button>
      <?php endif; ?>
      <button type="button" class="icon-btn heart" disabled aria-label="Save for later (not built yet)"><?= icon('heart') ?></button>
    </form>
    <div class="btn-row btn-row-tight">
      <button type="button" class="btn-outline" disabled>Add to quote</button>
      <?php if ($whatsapp !== null) : ?><a class="btn-outline" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a><?php endif; ?>
    </div>
    <p class="hint">Add to quote, saving for later and bulk quotes are not built yet, so those buttons are switched off.</p>

    <ul class="trust-row trust-row-tight" aria-label="Why shop with us">
      <?php foreach ($trust as $t) : ?>
        <li><?= icon($t['icon'], 'icon icon-lg') ?><span><strong><?= e($t['title']) ?></strong><br><?= e($t['line']) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <section class="bulk" aria-labelledby="bulk-title" data-bulk>
      <h2 id="bulk-title">Buying in bulk?</h2>
      <p>Order more and pay less per unit, or ask for a quote for a larger order.</p>
      <?php if (is_mock_mode() && $rows !== []) : ?><p class="mock-note">Sample bulk prices until Belis Bell sets its own.</p><?php endif; ?>
      <?php if ($rows !== []) : ?>
        <div class="tw">
          <table class="tier-table">
            <caption class="sr-only">Bulk price per unit</caption>
            <thead><tr><th scope="col">Quantity</th><th scope="col">Price per unit</th><th scope="col">You save</th></tr></thead>
            <tbody data-tier-rows>
              <?php foreach ($rows as $r) : ?>
                <tr><th scope="row"><?= e($r['label']) ?></th><td><?= e(money((int) $r['unit_price_pesewas'])) ?></td><td><?= e($r['save_percent'] > 0 ? $r['save_percent'] . '%' : 'None') ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="bulk-est" data-bulk-est>
          <label for="bulk-qty">Quantity</label>
          <input id="bulk-qty" type="number" inputmode="numeric" min="1" max="100000" value="50">
          <p class="bulk-total" aria-live="polite">Estimated total <strong data-bulk-total><?= e(money(Belis\Domain\Pricing::total((int) $sel['price_pesewas'], $sel['tiers'], 50))) ?></strong> <span data-bulk-unit>(<?= e(money(Belis\Domain\Pricing::unitPrice((int) $sel['price_pesewas'], $sel['tiers'], 50))) ?> each)</span></p>
        </div>
        <p class="hint">An estimate only. The final price is confirmed on the quote or at checkout.</p>
      <?php endif; ?>
      <p><button type="button" class="btn-outline" disabled>Request bulk quote</button></p>
      <p class="hint">Quote requests are not built yet, so this button is switched off.</p>
    </section>

    <?php if ($highlights !== []) : ?>
      <section class="highlights" aria-label="Product highlights">
        <ul>
          <?php foreach ($highlights as $h) : ?>
            <li><span class="hl-icon"><?= icon($h['icon']) ?></span><span><strong><?= e($h['title']) ?></strong><br><?= e($h['line']) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php if (is_mock_mode()) : ?><p class="mock-note">Sample claims. Real ones come from the supplier's data sheet.</p><?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>

<div class="wrap pdp-lower">
  <div class="tabs card" data-tabs>
    <div class="tablist" role="tablist" aria-label="Product information">
      <button type="button" role="tab" id="tab-description" aria-controls="panel-description" aria-selected="true">Description</button>
      <button type="button" role="tab" id="tab-specs" aria-controls="panel-specs" aria-selected="false" tabindex="-1">Specifications</button>
      <button type="button" role="tab" id="tab-use" aria-controls="panel-use" aria-selected="false" tabindex="-1">How to Use</button>
      <button type="button" role="tab" id="tab-delivery" aria-controls="panel-delivery" aria-selected="false" tabindex="-1">Delivery &amp; Returns</button>
    </div>

    <section class="tabpanel" role="tabpanel" id="panel-description" aria-labelledby="tab-description">
      <h2>Description</h2>
      <?php if (!empty($product['description'])) : ?><p><?= e($product['description']) ?></p><?php else : ?><p class="hint">A full description will be added.</p><?php endif; ?>
      <?php if ($features !== []) : ?>
        <h3>Key Features</h3>
        <ul class="feature-list">
          <?php foreach ($features as $f) : ?><li><?= icon('check-circle') ?><span><?= e($f) ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="tabpanel" role="tabpanel" id="panel-specs" aria-labelledby="tab-specs">
      <h2>Specifications</h2>
      <?php if ($specs !== []) : ?>
        <dl class="spec-list">
          <?php foreach ($specs as $s) : ?><div><dt><?= e($s['label']) ?></dt><dd><?= e($s['value']) ?></dd></div><?php endforeach; ?>
        </dl>
      <?php else : ?><p class="hint">Specifications will be added.</p><?php endif; ?>
    </section>

    <section class="tabpanel" role="tabpanel" id="panel-use" aria-labelledby="tab-use">
      <h2>How to Use</h2>
      <?php if (!empty($product['usage_notes'])) : ?><p><?= e($product['usage_notes']) ?></p><?php else : ?><p class="hint">Directions and safety notes will be added from the supplier's data sheet.</p><?php endif; ?>
    </section>

    <section class="tabpanel" role="tabpanel" id="panel-delivery" aria-labelledby="tab-delivery">
      <h2>Delivery &amp; Returns</h2>
      <?php if ($deliveryReturns !== []) : ?>
        <?php foreach ($deliveryReturns as $para) : ?><p><?= e($para) ?></p><?php endforeach; ?>
      <?php else : ?><p class="hint">Delivery and returns information will be added.</p><?php endif; ?>
    </section>
  </div>

  <?php if ($related !== []) : ?>
    <aside class="also card" aria-labelledby="also-title">
      <div class="also-head">
        <h2 id="also-title">You May Also Like</h2>
        <a class="view-all" href="/c/<?= e(rawurlencode((string) $product['category_slug'])) ?>">View All<?= icon('arrow-right') ?></a>
      </div>
      <ul class="also-grid">
        <?php foreach (array_slice($related, 0, 2) as $p) : ?>
          <li><?php include __DIR__ . '/../partials/product_card.php'; ?></li>
        <?php endforeach; ?>
      </ul>
    </aside>
  <?php endif; ?>
</div>

<?php if ($uses !== []) : ?>
  <section class="wrap uses" aria-labelledby="uses-title">
    <div class="uses-box">
      <h2 id="uses-title">Common Uses</h2>
      <p>Where this product is commonly used.</p>
      <ul class="use-grid">
        <?php foreach ($uses as $u) : ?>
          <li class="use-tile">
            <?= image_html('uses/' . $u['slug'], $u['label'], '(min-width: 1024px) 15vw, 30vw', ['decorative' => true, 'placeholder_class' => 'ph-use']) ?>
            <span><?= e($u['label']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>

<dialog class="lightbox" data-lightbox aria-label="Product photo">
  <button type="button" class="lightbox-close" data-close aria-label="Close photo"><?= icon('x') ?></button>
  <img alt="">
</dialog>
