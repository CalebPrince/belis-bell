<?php
/**
 * Shop page (PG-045) and category page (PG-050): hero, filter panel, results, pagination and a closing strip.
 *
 * @var array<string,mixed>|null $category
 * @var array<string,mixed>|null $sub
 * @var list<array<string,mixed>> $subcategories
 * @var array{all:int,categories:list<array<string,mixed>>,in_stock:int,out_of_stock:int,brands:list<array<string,mixed>>,lowest:int,highest:int} $facets
 * @var array{sort:string,page:int,avail:?string,brand:?string,min:?int,max:?int,sub:?string} $filters
 * @var list<array<string,mixed>> $items
 * @var int $total
 * @var int $pages
 * @var int $page
 * @var string $heroSlot
 * @var list<array{icon:string,title:string,line:string}> $trust
 * @var list<array{icon:string,title:string,line:string}> $strip
 */
$base = $category === null ? '/shop' : '/c/' . rawurlencode((string) $category['slug']);
$sorts = ['featured' => 'Featured', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'name' => 'Name: A to Z'];
$current = [
    'sort' => $filters['sort'] === 'featured' ? null : $filters['sort'],
    'avail' => $filters['avail'], 'brand' => $filters['brand'], 'min' => $filters['min'], 'max' => $filters['max'], 'sub' => $filters['sub'],
];
$keep = static fn (array $extra): string => query_url($base, $extra + $current);
$hasFilters = $filters['avail'] !== null || $filters['brand'] !== null || $filters['min'] !== null || $filters['max'] !== null;
$from = $total === 0 ? 0 : ($page - 1) * Belis\Domain\Catalogue::PAGE_SIZE + 1;
$to = min($total, $page * Belis\Domain\Catalogue::PAGE_SIZE);
$window = static function (int $page, int $pages): array {
    $set = array_unique(array_filter([1, 2, $pages - 1, $pages, $page - 1, $page, $page + 1], static fn (int $n): bool => $n >= 1 && $n <= $pages));
    sort($set);
    $out = [];
    $prev = 0;
    foreach ($set as $n) {
        if ($prev !== 0 && $n - $prev > 1) {
            $out[] = 0;
        }
        $out[] = $n;
        $prev = $n;
    }
    return $out;
};
$title = $category === null ? 'Shop Our Products' : (string) $category['name'];
$intro = $category === null ? 'Quality cleaning products and everyday essentials for homes, businesses and institutions across Ghana.' : (string) $category['blurb'];
?>
<section class="hero hero-page" aria-labelledby="page-title">
  <div class="bleed" aria-hidden="true">
    <?= image_html($heroSlot, '', '100vw', ['decorative' => true, 'priority' => true, 'class' => 'bleed-img', 'placeholder_class' => 'bleed-ph']) ?>
  </div>
  <div class="wrap hero-inner">
    <div class="hero-copy">
      <nav aria-label="Breadcrumb" class="crumbs">
        <ol>
          <li><a href="/">Home</a></li><li aria-hidden="true">/</li>
          <?php if ($category === null) : ?>
            <li aria-current="page">Shop</li>
          <?php else : ?>
            <li><a href="/shop">Shop</a></li><li aria-hidden="true">/</li>
            <li aria-current="page"><?= e($category['name']) ?></li>
          <?php endif; ?>
        </ol>
      </nav>
      <?php if ($category === null) : ?><p class="eyebrow">Cleaning Supplies &amp; More</p><?php endif; ?>
      <h1 id="page-title" class="page-title page-title-lg"><?= e($title) ?></h1>
      <?php if ($intro !== '') : ?><p class="lead"><?= e($intro) ?></p><?php endif; ?>
      <ul class="trust-row" aria-label="Why shop with us">
        <?php foreach ($trust as $t) : ?>
          <li><?= icon($t['icon'], 'icon icon-lg') ?><span><strong><?= e($t['title']) ?></strong><br><?= e($t['line']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<div class="wrap shop">
  <aside class="filters" aria-label="Filters">
    <details class="filters-box" data-filters open>
      <summary class="filters-toggle"><?= icon('filter') ?>Filters<?php if ($hasFilters) : ?> (on)<?php endif; ?></summary>
      <div class="filters-body">
        <h2 class="filters-title"><?= e($category === null ? 'Categories' : $category['name']) ?></h2>
        <ul class="filter-list">
          <?php if ($category === null) : ?>
            <li><a href="/shop" aria-current="page"><span>All Products</span><span class="count"><?= e($facets['all']) ?></span></a></li>
            <?php foreach ($facets['categories'] as $c) : ?>
              <li><a href="/c/<?= e(rawurlencode((string) $c['slug'])) ?>"><span><?= e($c['name']) ?></span><span class="count"><?= e($c['n']) ?></span></a></li>
            <?php endforeach; ?>
          <?php else : ?>
            <li><a href="<?= e(query_url($base, ['sort' => $current['sort']])) ?>"<?= flag($sub === null, 'aria-current="page"') ?>><span>All <?= e($category['name']) ?></span><span class="count"><?= e($facets['all']) ?></span></a></li>
            <?php foreach ($subcategories as $s) : ?>
              <li><a href="<?= e(query_url($base, ['sort' => $current['sort'], 'sub' => $s['slug']])) ?>"<?= flag(($sub['slug'] ?? null) === $s['slug'], 'aria-current="page"') ?>><span><?= e($s['name']) ?></span><span class="count"><?= e($s['n']) ?></span></a></li>
            <?php endforeach; ?>
            <li class="filter-other"><a href="/shop">All categories</a></li>
          <?php endif; ?>
        </ul>

        <form method="get" action="<?= e($base) ?>" class="filter-form">
          <?php if ($current['sort'] !== null) : ?><input type="hidden" name="sort" value="<?= e($current['sort']) ?>"><?php endif; ?>
          <?php if ($filters['sub'] !== null) : ?><input type="hidden" name="sub" value="<?= e($filters['sub']) ?>"><?php endif; ?>

          <fieldset>
            <legend>Price Range (GHS)</legend>
            <div class="price-fields">
              <label>Min<input type="number" inputmode="numeric" min="0" name="min" placeholder="<?= e(intdiv($facets['lowest'], 100)) ?>" value="<?= e($filters['min'] ?? '') ?>"></label>
              <label>Max<input type="number" inputmode="numeric" min="0" name="max" placeholder="<?= e((int) ceil($facets['highest'] / 100)) ?>" value="<?= e($filters['max'] ?? '') ?>"></label>
            </div>
          </fieldset>

          <fieldset>
            <legend>Availability</legend>
            <label class="opt"><input type="radio" name="avail" value=""<?= flag($filters['avail'] === null, 'checked') ?>><span>Any</span></label>
            <label class="opt"><input type="radio" name="avail" value="in"<?= flag($filters['avail'] === 'in', 'checked') ?>><span>In Stock</span><span class="count"><?= e($facets['in_stock']) ?></span></label>
            <label class="opt"><input type="radio" name="avail" value="out"<?= flag($filters['avail'] === 'out', 'checked') ?>><span>Out of Stock</span><span class="count"><?= e($facets['out_of_stock']) ?></span></label>
          </fieldset>

          <?php if ($facets['brands'] !== []) : ?>
            <fieldset>
              <legend>Brand</legend>
              <label class="opt"><input type="radio" name="brand" value=""<?= flag($filters['brand'] === null, 'checked') ?>><span>All Brands</span></label>
              <?php foreach ($facets['brands'] as $b) : ?>
                <label class="opt"><input type="radio" name="brand" value="<?= e($b['name']) ?>"<?= flag($filters['brand'] === $b['name'], 'checked') ?>><span><?= e($b['name']) ?></span><span class="count"><?= e($b['n']) ?></span></label>
              <?php endforeach; ?>
            </fieldset>
            <?php if (is_mock_mode()) : ?><p class="mock-note">Sample brand names.</p><?php endif; ?>
          <?php endif; ?>

          <button type="submit" class="btn-navy btn-block">Apply</button>
          <a class="clear-link" href="<?= e(query_url($base, ['sort' => $current['sort'], 'sub' => $filters['sub']])) ?>"><?= icon('refresh') ?>Clear Filters</a>
        </form>
      </div>
    </details>
  </aside>

  <div class="shop-main">
    <div class="results-bar">
      <p aria-live="polite">Showing <?= e($from) ?> to <?= e($to) ?> of <?= e($total) ?> <?= e($total === 1 ? 'product' : 'products') ?></p>
      <form method="get" action="<?= e($base) ?>" class="sort-form" data-autosubmit>
        <?php foreach (['avail', 'brand', 'min', 'max', 'sub'] as $k) : ?><?php if ($current[$k] !== null) : ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($current[$k]) ?>"><?php endif; ?><?php endforeach; ?>
        <label for="sort">Sort by</label>
        <select id="sort" name="sort">
          <?php foreach ($sorts as $key => $label) : ?><option value="<?= e($key) ?>"<?= flag($filters['sort'] === $key, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn-quiet sort-go">Sort</button>
      </form>
    </div>
    <?php if (is_mock_mode()) : ?><p class="mock-note">Mock products and prices.</p><?php endif; ?>

    <?php if ($items === []) : ?>
      <div class="empty-note">
        <p class="empty-title">No products match</p>
        <p>Try another category or clear the filters.</p>
        <p><a class="btn-primary" href="<?= e($base) ?>">Clear Filters</a></p>
      </div>
    <?php else : ?>
      <ul class="product-grid">
        <?php foreach ($items as $p) : ?>
          <li><?php include __DIR__ . '/../partials/product_card.php'; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($pages > 1) : ?>
      <nav class="pager" aria-label="Pagination">
        <?php if ($page > 1) : ?><a class="pg" href="<?= e($keep(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left') ?></a><?php endif; ?>
        <?php foreach ($window($page, $pages) as $n) : ?>
          <?php if ($n === 0) : ?><span class="pg-gap" aria-hidden="true">...</span>
          <?php else : ?><a class="pg" href="<?= e($keep(['page' => $n > 1 ? $n : null])) ?>"<?= flag($n === $page, 'aria-current="page"') ?> aria-label="Page <?= e($n) ?>"><?= e($n) ?></a><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($page < $pages) : ?><a class="pg" href="<?= e($keep(['page' => $page + 1])) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right') ?></a><?php endif; ?>
      </nav>
    <?php endif; ?>

    <?php if ($strip !== []) : ?>
      <section class="strip" aria-label="<?= e($category === null ? 'Why shop with Belis Bell' : 'Why choose our ' . $category['name']) ?>">
        <?php if ($category !== null) : ?><h2>Why Choose Our <?= e($category['name']) ?>?</h2><?php endif; ?>
        <ul>
          <?php foreach ($strip as $s) : ?>
            <li><?= icon($s['icon'], 'icon icon-lg') ?><span><strong><?= e($s['title']) ?></strong><br><?= e($s['line']) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php if (is_mock_mode()) : ?><p class="mock-note">Sample wording until Belis Bell confirms each promise.</p><?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
