<?php
/**
 * Staff product list. Nothing is deleted here: hide a product instead.
 *
 * @var array<string,string> $staff
 * @var array{items:list<array<string,mixed>>,total:int,pages:int,page:int} $result
 * @var array{q:string,category:int,published:string,page:int} $filters
 * @var list<array{id:int,name:string,parent_id:?int}> $categories
 * @var bool $isOwner
 */
$active = 'products';
$stockLabel = Belis\Domain\CatalogueAdmin::STOCK;
$keep = ['q' => $filters['q'], 'category' => $filters['category'] > 0 ? (string) $filters['category'] : '', 'published' => $filters['published']];
$page = $result['page'];
$pages = $result['pages'];
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Products</h1>
  <p class="lead"><?= e($result['total']) ?> <?= e($result['total'] === 1 ? 'product' : 'products') ?>.
    <?php if ($isOwner) : ?><a class="btn-primary" href="/admin/products/new">Add a product</a><?php endif; ?></p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <form method="get" action="/admin/products" class="card filter-bar" role="search" aria-label="Filter products">
      <div class="field">
        <label for="pf-q">Name or brand</label>
        <input id="pf-q" name="q" type="search" value="<?= e($filters['q']) ?>" maxlength="60">
      </div>
      <div class="field">
        <label for="pf-cat">Category</label>
        <select id="pf-cat" name="category">
          <option value="">All</option>
          <?php foreach ($categories as $c) : ?><option value="<?= e($c['id']) ?>"<?= flag($filters['category'] === (int) $c['id'], 'selected') ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="pf-pub">In the shop</label>
        <select id="pf-pub" name="published">
          <option value="">All</option>
          <option value="1"<?= flag($filters['published'] === '1', 'selected') ?>>Shown</option>
          <option value="0"<?= flag($filters['published'] === '0', 'selected') ?>>Hidden</option>
        </select>
      </div>
      <div class="filter-actions"><button type="submit" class="btn-primary">Filter</button><a class="btn-quiet" href="/admin/products">Clear</a></div>
    </form>

    <section class="card" aria-labelledby="pl-title">
      <h2 id="pl-title" class="sr-only">Product list</h2>
      <?php if ($result['items'] === []) : ?>
        <p class="empty-note">No products found.</p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col">Sizes</th><th scope="col">Price</th><th scope="col">Stock</th><th scope="col">Shop</th></tr></thead>
            <tbody>
              <?php foreach ($result['items'] as $p) : ?>
                <tr>
                  <th scope="row"><a href="/admin/products/<?= e($p['id']) ?>"><?= e($p['name']) ?></a><?php if ($p['brand'] !== null && $p['brand'] !== '') : ?><br><span class="card-meta"><?= e($p['brand']) ?></span><?php endif; ?></th>
                  <td><?= e($p['category']) ?></td>
                  <td><?= e($p['sizes']) ?></td>
                  <td><?= e($p['low'] === null ? 'No price' : ((int) $p['low'] === (int) $p['high'] ? money((int) $p['low']) : money((int) $p['low']) . ' to ' . money((int) $p['high']))) ?></td>
                  <td><?= e($stockLabel[$p['stock_status']] ?? '') ?></td>
                  <td><span class="badge<?= e((int) $p['is_published'] === 1 ? ' state-paid' : '') ?>"><?= e((int) $p['is_published'] === 1 ? 'Shown' : 'Hidden') ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1) : ?>
          <nav class="pager" aria-label="Pages">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
              <a class="pg" href="<?= e(query_url('/admin/products', $keep + ['page' => (string) $i])) ?>"<?= flag($i === $page, 'aria-current="page"') ?>><?= e($i) ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
