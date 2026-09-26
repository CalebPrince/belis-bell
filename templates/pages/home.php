<?php
/** @var list<array<string,mixed>> $categories */
/** @var list<array<string,mixed>> $products */
$stockLabel = ['in_stock' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'];
?>
<section class="mx-auto grid max-w-[1280px] gap-4 px-4 pt-6 lg:grid-cols-[1.5fr_1fr]" aria-labelledby="hero-title">
  <div class="hero-panel">
    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/80">Cleaning supplies and more</p>
    <h1 id="hero-title" class="mt-3 max-w-[14ch] font-display text-4xl font-bold leading-[1.05] sm:text-5xl">A cleaner washroom, delivered.</h1>
    <p class="mt-4 max-w-[46ch] text-base text-white/90">Everyday supplies for homes and businesses, and quote-based supply for banks, schools and government offices.</p>
    <div class="mt-6 flex flex-wrap gap-3">
      <a href="#categories" class="btn-light">Shop products</a>
      <a href="#ways" class="btn-outline-light">Buy for business</a>
    </div>
    <p class="mt-6 text-xs text-white/70">Sample image area. Real photography is added before launch.</p>
  </div>
  <div class="rounded-2xl border border-line bg-surface p-4">
    <h2 class="font-display text-xl font-semibold">What to explore now</h2>
    <div class="mt-3 grid grid-cols-2 gap-3">
      <a href="#categories" class="tile tile-blue"><span class="tile-title">Washroom refill packs</span><span class="tile-sub">Soap, tissue and towels</span></a>
      <a href="#categories" class="tile tile-navy"><span class="tile-title">Cleaning chemicals</span><span class="tile-sub">Bleach and disinfectant</span></a>
      <a href="#categories" class="tile tile-green"><span class="tile-title">Cleaning equipment</span><span class="tile-sub">Mops, buckets, brushes</span></a>
      <a href="#ways" class="tile tile-ink"><span class="tile-title">Buy for business</span><span class="tile-sub">Request a quote</span></a>
    </div>
  </div>
</section>

<?php if ($categories !== []) : ?>
<nav class="mx-auto mt-6 max-w-[1280px] px-4" aria-label="Category shortcuts">
  <ul class="flex gap-2 overflow-x-auto pb-1">
    <?php foreach ($categories as $cat) : ?>
      <li><a href="#categories" class="pill-tab"><?= e($cat['name']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>
<?php endif; ?>

<section id="products" class="mx-auto mt-10 max-w-[1280px] px-4" aria-labelledby="products-title">
  <div class="flex items-end justify-between gap-4">
    <h2 id="products-title" class="font-display text-2xl font-semibold sm:text-3xl">Top sellers</h2>
    <p class="text-sm text-muted">Mock products and prices</p>
  </div>
  <?php if ($products === []) : ?>
    <p class="mt-4 rounded-xl border border-line bg-surface p-6 text-muted">No products yet. Run the mock seed to see sample products.</p>
  <?php else : ?>
  <ul class="mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3" tabindex="0" aria-label="Top sellers, scroll sideways">
    <?php foreach ($products as $p) : ?>
      <li class="w-[220px] shrink-0 snap-start sm:w-[240px]">
        <article class="card h-full">
          <div class="ph" role="img" aria-label="Sample product image"><span>Sample photo</span></div>
          <h3 class="mt-3 line-clamp-2 min-h-[3rem] font-display text-base font-semibold"><?= e($p['name']) ?></h3>
          <p class="text-sm text-muted"><?= e($p['pack_size']) ?></p>
          <p class="mt-2 font-display text-xl font-bold"><?= e(money((int) $p['price_pesewas'])) ?></p>
          <p class="mt-1 text-sm <?= e($p['stock_status'] === 'out' ? 'text-danger' : 'text-leaf') ?>"><?= e($stockLabel[$p['stock_status']] ?? '') ?></p>
          <button type="button" disabled class="btn-primary mt-3 w-full">Add to cart</button>
        </article>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<section id="ways" class="mt-12 bg-band py-12" aria-labelledby="ways-title">
  <div class="mx-auto max-w-[1280px] px-4">
    <h2 id="ways-title" class="text-center font-display text-3xl font-semibold">Three ways to shop with Belis Bell</h2>
    <p class="mx-auto mt-2 max-w-[56ch] text-center text-muted">Buy what you need, ask for a quote for bigger orders, or start with a ready-made washroom pack.</p>
    <div class="mt-8 grid gap-4 md:grid-cols-3">
      <a href="#categories" class="way way-blue"><h3>Shop products</h3><p>Browse the full range and pay online.</p></a>
      <a href="#ways" class="way way-navy"><h3>Buy for business</h3><p>Request a quote for banks, schools, government and private companies.</p></a>
      <a href="#categories" class="way way-green"><h3>Washroom solutions</h3><p>Curated packs to fit out a whole washroom.</p></a>
    </div>
  </div>
</section>

<section id="categories" class="mx-auto mt-12 max-w-[1280px] px-4" aria-labelledby="cats-title">
  <h2 id="cats-title" class="font-display text-2xl font-semibold sm:text-3xl">Core supply categories</h2>
  <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <?php foreach ($categories as $cat) : ?>
      <li>
        <a href="#products" class="card block h-full transition hover:-translate-y-0.5 motion-reduce:transition-none">
          <div class="ph ph-short" aria-hidden="true"><span>Sample</span></div>
          <h3 class="mt-3 font-display text-lg font-semibold"><?= e($cat['name']) ?></h3>
          <p class="text-sm text-muted"><?= e($cat['blurb']) ?></p>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if (is_mock_mode()) : ?>
<section class="mx-auto mt-12 max-w-[1280px] px-4" aria-label="Trust figure (sample)">
  <div class="rounded-2xl bg-navybg p-8 text-center text-white">
    <p class="font-display text-4xl font-bold">1,200+ deliveries</p>
    <p class="mt-1 text-white/80">Sample figure. Belis Bell supplies the real number before launch.</p>
  </div>
</section>
<?php endif; ?>
