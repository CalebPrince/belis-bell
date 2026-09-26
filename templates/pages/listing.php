<?php
/**
 * @var array<string,mixed>|null $category
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $items
 * @var int $total
 * @var int $pages
 * @var int $page
 * @var string $sort
 * @var bool $inStock
 */
$base = $category === null ? '/shop' : '/c/' . rawurlencode((string) $category['slug']);
$sorts = ['featured' => 'Featured', 'price-asc' => 'Price, low to high', 'price-desc' => 'Price, high to low', 'name' => 'Name, A to Z'];
$keep = static fn (array $extra): string => query_url($base, ['sort' => $sort === 'featured' ? null : $sort, 'stock' => $inStock ? 'in' : null] + $extra);
?>
<div class="mx-auto max-w-[1280px] px-4 pt-6">
  <nav aria-label="Breadcrumb" class="text-sm text-muted">
    <ol class="flex flex-wrap gap-x-2">
      <li><a class="hover:underline" href="/">Home</a></li>
      <li aria-hidden="true">/</li>
      <?php if ($category === null) : ?>
        <li aria-current="page" class="font-semibold text-ink">All products</li>
      <?php else : ?>
        <li><a class="hover:underline" href="/shop">Shop</a></li>
        <li aria-hidden="true">/</li>
        <li aria-current="page" class="font-semibold text-ink"><?= e($category['name']) ?></li>
      <?php endif; ?>
    </ol>
  </nav>

  <h1 class="mt-3 font-display text-3xl font-bold sm:text-4xl"><?= e($category['name'] ?? 'All products') ?></h1>
  <?php if ($category !== null && $category['blurb'] !== '') : ?>
    <p class="mt-1 text-muted"><?= e($category['blurb']) ?></p>
  <?php endif; ?>
  <p class="mt-1 text-sm text-muted">Mock products and prices.</p>

  <ul class="mt-4 flex flex-wrap gap-2" aria-label="Categories">
    <li><a href="/shop" class="pill-tab" <?= flag($category === null, 'aria-current="page"') ?>>All</a></li>
    <?php foreach ($categories as $c) : ?>
      <li><a href="/c/<?= e(rawurlencode((string) $c['slug'])) ?>" class="pill-tab" <?= flag(($category['slug'] ?? null) === $c['slug'], 'aria-current="page"') ?>><?= e($c['name']) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <form method="get" action="<?= e($base) ?>" class="mt-4 flex flex-wrap items-end gap-4 rounded-2xl border border-line bg-surface p-4" aria-label="Sort and filter">
    <div>
      <label for="sort" class="block text-sm font-semibold">Sort by</label>
      <select id="sort" name="sort" class="mt-1 h-12 rounded-xl border border-line bg-canvas px-3">
        <?php foreach ($sorts as $key => $label) : ?>
          <option value="<?= e($key) ?>" <?= flag($sort === $key, 'selected') ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex h-12 items-center gap-2">
      <input id="stock" type="checkbox" name="stock" value="in" class="h-5 w-5" <?= flag($inStock, 'checked') ?>>
      <label for="stock" class="text-base">Hide out of stock</label>
    </div>
    <button type="submit" class="btn-primary">Apply</button>
  </form>

  <p class="mt-4 text-sm text-muted" aria-live="polite"><?= e($total) ?> <?= e($total === 1 ? 'product' : 'products') ?></p>

  <?php if ($items === []) : ?>
    <div class="mt-4 rounded-2xl border border-line bg-surface p-8 text-center">
      <p class="font-display text-xl font-semibold">No products match</p>
      <p class="mt-1 text-muted">Try another category, or turn off the stock filter.</p>
      <p class="mt-4"><a class="btn-primary" href="/shop">See all products</a></p>
    </div>
  <?php else : ?>
    <ul class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
      <?php foreach ($items as $p) : ?>
        <li><?php include __DIR__ . '/../partials/product_card.php'; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($pages > 1) : ?>
    <nav class="mt-8 flex items-center justify-between gap-4" aria-label="Pagination">
      <?php if ($page > 1) : ?>
        <a class="btn-quiet" href="<?= e($keep(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" rel="prev">Previous</a>
      <?php else : ?>
        <span></span>
      <?php endif; ?>
      <p class="text-sm text-muted">Page <?= e($page) ?> of <?= e($pages) ?></p>
      <?php if ($page < $pages) : ?>
        <a class="btn-quiet" href="<?= e($keep(['page' => $page + 1])) ?>" rel="next">Next</a>
      <?php else : ?>
        <span></span>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
