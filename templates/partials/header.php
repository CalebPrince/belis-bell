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

    <div class="header-tools">
      <button type="button" class="icon-btn" disabled aria-label="Search (not built yet)"><?= icon('search') ?></button>
      <button type="button" class="icon-btn" disabled aria-label="Account (not built yet)"><?= icon('user') ?></button>
      <button type="button" class="icon-btn cart-btn" disabled aria-label="Cart, 0 items (not built yet)"><?= icon('cart') ?><span class="cart-count">0</span></button>
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
