<?php
/**
 * Home page structure from the owner's mockup (PG-033). Photos are named slots (docs/IMAGES.md).
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $products
 * @var list<array{icon:string,title:string,line:string}> $trust
 * @var list<array{icon:string,title:string,line:string}> $why
 * @var list<array{slot:string,icon:string,title:string,line:string,alt:string,focus:string}> $audiences
 */
?>
<section class="hero" aria-labelledby="hero-title">
  <div class="bleed" aria-hidden="true">
    <?= image_html('home/hero', '', '100vw', ['decorative' => true, 'priority' => true, 'class' => 'bleed-img', 'placeholder_class' => 'bleed-ph']) ?>
  </div>
  <div class="wrap hero-inner">
    <div class="hero-copy">
      <p class="eyebrow">Cleaning Supplies &amp; More</p>
      <h1 id="hero-title" class="hero-title">
        <span class="t-ink">Everything You</span>
        <span class="t-blue">Need for a Cleaner,</span>
        <span class="t-green">Healthier Space</span>
      </h1>
      <p class="lead">Quality cleaning products and everyday essentials for homes, businesses and institutions across Ghana.</p>
      <div class="btn-row">
        <a href="/shop" class="btn-primary">Shop Now<?= icon('arrow-right') ?></a>
        <a href="/for-businesses" class="btn-outline">For Businesses</a>
      </div>
      <ul class="trust-row" aria-label="Why shop with us">
        <?php foreach ($trust as $t) : ?>
          <li><?= icon($t['icon'], 'icon icon-lg') ?><span><strong><?= e($t['title']) ?></strong><br><?= e($t['line']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="range-title">
  <div class="wrap">
    <p class="eyebrow center">Shop by category</p>
    <h2 id="range-title" class="section-title">Explore Our Range</h2>
    <p class="section-sub">From cleaning essentials to everyday supplies.</p>
    <?php if ($categories === []) : ?>
      <p class="empty-note">No categories yet. Run the mock seed to see sample categories.</p>
    <?php else : ?>
      <ul class="cat-grid">
        <?php foreach ($categories as $cat) : ?>
          <li>
            <a class="cat-card" href="/c/<?= e(rawurlencode((string) $cat['slug'])) ?>">
              <span class="cat-media"><?= image_html('categories/' . $cat['slug'], (string) $cat['name'], '(min-width: 1024px) 16vw, 45vw', ['decorative' => true, 'placeholder_class' => 'ph-square']) ?></span>
              <span class="cat-name"><?= e($cat['name']) ?></span>
              <span class="cat-go"><?= icon('arrow-right') ?><span class="sr-only">Browse <?= e($cat['name']) ?></span></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<section class="banner banner-a" aria-labelledby="promo-title">
  <div class="bleed" aria-hidden="true">
    <?= image_html('home/promo', '', '100vw', ['decorative' => true, 'class' => 'bleed-img', 'placeholder_class' => 'bleed-ph']) ?>
  </div>
  <div class="wrap banner-inner">
    <div class="banner-copy">
      <p class="eyebrow eyebrow-light">Trusted by homes, businesses and institutions</p>
      <h2 id="promo-title" class="banner-title">Reliable Supplies.<br>Delivered to You.</h2>
      <p>We make it easy to keep your spaces clean and well stocked with high-quality products at great value.</p>
      <p><a href="/shop" class="btn-primary">Shop Now<?= icon('arrow-right') ?></a></p>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="serve-title">
  <div class="wrap">
    <p class="eyebrow center">Who we serve</p>
    <h2 id="serve-title" class="section-title">Solutions for Every Space</h2>
    <ul class="aud-grid">
      <?php foreach ($audiences as $a) : ?>
        <li class="aud-card">
          <div class="aud-media"><?= image_html($a['slot'], $a['alt'], '(min-width: 1024px) 30vw, 100vw', ['placeholder_class' => 'ph-wide', 'class' => $a['focus']]) ?></div>
          <span class="aud-icon"><?= icon($a['icon']) ?></span>
          <h3><?= e($a['title']) ?></h3>
          <p><?= e($a['line']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section id="products" class="section section-tint" aria-labelledby="popular-title">
  <div class="wrap">
    <p class="eyebrow center">Featured products</p>
    <h2 id="popular-title" class="section-title">Popular Products</h2>
    <p class="section-sub">Mock products and prices for now.</p>
    <?php if ($products === []) : ?>
      <p class="empty-note">No products yet. Run the mock seed to see sample products.</p>
    <?php else : ?>
      <div class="carousel" data-carousel>
        <button type="button" class="carousel-btn carousel-prev" data-dir="-1" aria-label="Previous products"><?= icon('chevron-left') ?></button>
        <ul class="carousel-track" data-track tabindex="0" aria-label="Popular products, scroll sideways">
          <?php foreach ($products as $p) : ?>
            <li class="carousel-item"><?php include __DIR__ . '/../partials/product_card.php'; ?></li>
          <?php endforeach; ?>
        </ul>
        <button type="button" class="carousel-btn carousel-next" data-dir="1" aria-label="Next products"><?= icon('chevron-right') ?></button>
        <div class="carousel-dots" data-dots role="group" aria-label="Pages of products"></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" aria-labelledby="why-title">
  <div class="wrap">
    <p class="eyebrow center">Why choose Belis Bell</p>
    <h2 id="why-title" class="section-title">More Than Just Products</h2>
    <p class="section-sub">We are your trusted partner for a cleaner, healthier and more productive environment.</p>
    <ul class="why-grid">
      <?php foreach ($why as $w) : ?>
        <li>
          <span class="why-icon"><?= icon($w['icon']) ?></span>
          <h3><?= e($w['title']) ?></h3>
          <p><?= e($w['line']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (is_mock_mode()) : ?><p class="mock-note">Sample wording until Belis Bell confirms each promise.</p><?php endif; ?>
  </div>
</section>

<section class="banner banner-b" aria-labelledby="cta-title">
  <div class="bleed" aria-hidden="true">
    <?= image_html('home/cta', '', '100vw', ['decorative' => true, 'class' => 'bleed-img', 'placeholder_class' => 'bleed-ph']) ?>
  </div>
  <div class="wrap banner-inner">
    <div class="banner-copy">
      <p class="eyebrow eyebrow-light">Ready to get started?</p>
      <h2 id="cta-title" class="banner-title">Clean Spaces<br>Start Here</h2>
      <p>Shop online or contact us for bulk orders and custom supply solutions.</p>
      <div class="btn-row">
        <a href="/shop" class="btn-primary">Shop Now<?= icon('arrow-right') ?></a>
        <a href="/contact" class="btn-outline btn-outline-light">Contact Us</a>
      </div>
    </div>
  </div>
</section>
