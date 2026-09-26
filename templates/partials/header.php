<?php $nav = site_nav(); $here = current_path(); ?>
<?php if (is_mock_mode()) : ?>
<div class="mock-strip">Sample store: products, prices, contact details and figures are mock data.</div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap header-row">
    <a href="/" class="logo-link" aria-label="Belis Bell home">
      <img src="<?= asset('brand/logo-mark.webp') ?>" alt="" width="48" height="48" class="logo-mark">
      <span class="logo-word"><span class="lw-blue">Belis</span> <span class="lw-green">Bell</span></span>
    </a>

    <nav class="primary-nav" aria-label="Main">
      <ul>
        <?php foreach ($nav as $link) : ?>
          <li><a href="<?= e($link['href']) ?>"<?= flag($here === $link['href'], 'aria-current="page"') ?>><?= e($link['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <form class="header-search" role="search" aria-label="Search products">
      <label for="site-search" class="sr-only">Search products</label>
      <input id="site-search" type="search" disabled placeholder="Search products (not built yet)">
      <?= icon('search') ?>
    </form>

    <div class="header-tools">
      <button type="button" class="icon-btn icon-search" disabled aria-label="Search (not built yet)"><?= icon('search') ?></button>
      <a class="icon-btn" href="<?= e(account_href()) ?>" aria-label="Account"><?= icon('user') ?></a>
      <a class="icon-btn cart-btn" href="/cart" aria-label="Cart, <?= e(cart_count()) ?> items"><?= icon('cart') ?><span class="cart-count"><?= e(cart_count()) ?></span></a>
      <details class="mobile-menu">
        <summary class="icon-btn" aria-label="Menu"><?= icon('menu') ?></summary>
        <nav class="mobile-panel" aria-label="Mobile">
          <ul>
            <?php foreach ($nav as $link) : ?>
              <li><a href="<?= e($link['href']) ?>"<?= flag($here === $link['href'], 'aria-current="page"') ?>><?= e($link['label']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
      </details>
    </div>
  </div>
</header>
