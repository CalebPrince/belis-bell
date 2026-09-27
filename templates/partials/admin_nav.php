<?php
/**
 * Side navigation for the staff area.
 *
 * @var string $active overview, orders or settings
 * @var array<string,string> $staff
 */
?>
  <nav class="account-nav card" aria-label="Admin">
    <ul>
      <li><a href="/admin"<?= flag($active === 'overview', 'aria-current="page"') ?>><?= icon('house') ?>Overview</a></li>
      <?php if (Belis\Core\Auth::can('orders')) : ?><li><a href="/admin/orders"<?= flag($active === 'orders', 'aria-current="page"') ?>><?= icon('cart') ?>Orders</a></li><?php endif; ?>
      <?php if (Belis\Core\Auth::can('content')) : ?><li><a href="/admin/products"<?= flag($active === 'products', 'aria-current="page"') ?>><?= icon('tag') ?>Products</a></li>
      <li><a href="/admin/categories"<?= flag($active === 'categories', 'aria-current="page"') ?>><?= icon('menu') ?>Categories</a></li><?php endif; ?>
      <li><span class="nav-off"><?= icon('mail') ?>Quotes <small>soon</small></span></li>
      <li><span class="nav-off"><?= icon('users') ?>Customers <small>soon</small></span></li>
      <?php if (($staff['role'] ?? '') === 'Owner') : ?><li><a href="/admin/staff"<?= flag($active === 'staff', 'aria-current="page"') ?>><?= icon('users') ?>Staff</a></li><li><a href="/admin/audit"<?= flag($active === 'audit', 'aria-current="page"') ?>><?= icon('clock') ?>Activity log</a></li><li><a href="/admin/settings"<?= flag($active === 'settings', 'aria-current="page"') ?>><?= icon('lock') ?>Settings</a></li><?php endif; ?>
    </ul>
  </nav>
