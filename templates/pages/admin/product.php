<?php
/**
 * Create or edit a product. Staff and the owner edit content, size names and stock. Only the owner creates products,
 * adds sizes and changes prices and bulk prices, and price changes need a fresh emailed code.
 *
 * @var array<string,string> $staff
 * @var array<string,mixed>|null $product
 * @var list<array{id:int,name:string,parent_id:?int}> $categories
 * @var list<array<string,mixed>> $history
 * @var list<array<string,mixed>> $stockHistory
 * @var array<string,mixed> $old
 * @var array<string,string> $errors
 * @var bool $isOwner
 * @var bool $fresh
 * @var bool $isNew
 * @var bool $canEdit
 */
$active = 'products';
$stockLabel = Belis\Domain\CatalogueAdmin::STOCK;
$val = static fn (string $k, mixed $fallback = ''): string => is_string($old[$k] ?? null) ? $old[$k] : (string) ($fallback ?? '');
$tops = array_values(array_filter($categories, static fn (array $c): bool => $c['parent_id'] === null));
$subs = array_values(array_filter($categories, static fn (array $c): bool => $c['parent_id'] !== null));
$parentName = [];
foreach ($tops as $t) {
    $parentName[(int) $t['id']] = (string) $t['name'];
}
$selCat = (int) $val('category_id', $product['category_id'] ?? 0);
$selSub = (int) $val('subcategory_id', $product['subcategory_id'] ?? 0);
$published = $old !== [] ? ($old['is_published'] ?? '') === '1' : (int) ($product['is_published'] ?? 0) === 1;
$big = Belis\Domain\CatalogueAdmin::BIG_CHANGE_PERCENT;
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/admin">Admin</a></li><li aria-hidden="true">/</li><li><a href="/admin/products">Products</a></li><li aria-hidden="true">/</li><li aria-current="page"><?= e($isNew ? 'New product' : $product['name']) ?></li></ol></nav>
  <h1 class="page-title page-title-lg"><?= e($isNew ? 'Add a product' : $product['name']) ?></h1>
  <?php if (!$isNew) : ?><p class="lead">Web address: /p/<?= e($product['slug']) ?>. <a href="/p/<?= e(rawurlencode((string) $product['slug'])) ?>">View in the shop</a></p><?php endif; ?>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <?php if (!$canEdit) : ?><p class="mock-note">The local preview person can look but not change anything.</p><?php endif; ?>
    <?php if ($errors !== [] && !$isNew) : ?><p class="form-error" role="alert">Something needs fixing. See the messages below.</p><?php endif; ?>

    <form method="post" action="<?= e($isNew ? '/admin/products/new' : '/admin/products/' . $product['id']) ?>" class="account-main" novalidate>
      <?= csrf_field() ?>
      <section class="card form-section" aria-labelledby="pd-content">
        <h2 id="pd-content">Details</h2>
        <div class="field">
          <label for="pd-name">Name</label>
          <input id="pd-name" name="name" type="text" maxlength="160" value="<?= e($val('name', $product['name'] ?? '')) ?>" aria-invalid="<?= e(isset($errors['name']) ? 'true' : 'false') ?>" required>
          <?php if (isset($errors['name'])) : ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field-grid">
          <div class="field">
            <label for="pd-brand">Brand (optional)</label>
            <input id="pd-brand" name="brand" type="text" maxlength="80" value="<?= e($val('brand', $product['brand'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="pd-cat">Category</label>
            <select id="pd-cat" name="category_id" aria-invalid="<?= e(isset($errors['category_id']) ? 'true' : 'false') ?>" required>
              <option value="">Choose a category</option>
              <?php foreach ($tops as $c) : ?><option value="<?= e($c['id']) ?>"<?= flag($selCat === (int) $c['id'], 'selected') ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])) : ?><p class="field-error" role="alert"><?= e($errors['category_id']) ?></p><?php endif; ?>
          </div>
        </div>
        <div class="field">
          <label for="pd-sub">Subcategory (optional)</label>
          <select id="pd-sub" name="subcategory_id" aria-invalid="<?= e(isset($errors['subcategory_id']) ? 'true' : 'false') ?>">
            <option value="">None</option>
            <?php foreach ($subs as $c) : ?><option value="<?= e($c['id']) ?>"<?= flag($selSub === (int) $c['id'], 'selected') ?>><?= e(($parentName[(int) $c['parent_id']] ?? '') . ' / ' . $c['name']) ?></option><?php endforeach; ?>
          </select>
          <?php if (isset($errors['subcategory_id'])) : ?><p class="field-error" role="alert"><?= e($errors['subcategory_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="pd-summary">Short summary (one line)</label>
          <input id="pd-summary" name="summary" type="text" maxlength="255" value="<?= e($val('summary', $product['summary'] ?? '')) ?>">
          <?php if (isset($errors['summary'])) : ?><p class="field-error" role="alert"><?= e($errors['summary']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="pd-desc">Description</label>
          <textarea id="pd-desc" name="description" rows="6" maxlength="5000"><?= e($val('description', $product['description'] ?? '')) ?></textarea>
          <?php if (isset($errors['description'])) : ?><p class="field-error" role="alert"><?= e($errors['description']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="pd-usage">How to use (optional)</label>
          <textarea id="pd-usage" name="usage_notes" rows="4" maxlength="2000"><?= e($val('usage_notes', $product['usage_notes'] ?? '')) ?></textarea>
          <?php if (isset($errors['usage_notes'])) : ?><p class="field-error" role="alert"><?= e($errors['usage_notes']) ?></p><?php endif; ?>
        </div>
        <?php if (!$isNew) : ?>
          <label class="opt"><input type="checkbox" name="is_published" value="1"<?= flag($published, 'checked') ?>><span>Show in the shop</span></label>
        <?php endif; ?>
      </section>

      <?php if ($isNew) : ?>
        <section class="card form-section" aria-labelledby="pd-first">
          <h2 id="pd-first">First size and price</h2>
          <p class="hint">The product starts hidden. Add more sizes and bulk prices after saving.</p>
          <div class="field-grid">
            <div class="field">
              <label for="pd-label">Size or pack</label>
              <input id="pd-label" name="label" type="text" maxlength="80" value="<?= e($val('label')) ?>" placeholder="5 L" aria-invalid="<?= e(isset($errors['label']) ? 'true' : 'false') ?>" required>
              <?php if (isset($errors['label'])) : ?><p class="field-error" role="alert"><?= e($errors['label']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="pd-price">Price (GH₵)</label>
              <input id="pd-price" name="price" type="text" inputmode="decimal" value="<?= e($val('price')) ?>" placeholder="45.00" aria-invalid="<?= e(isset($errors['price']) ? 'true' : 'false') ?>" required>
              <?php if (isset($errors['price'])) : ?><p class="field-error" role="alert"><?= e($errors['price']) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="field">
            <label for="pd-stock">How many in stock</label>
            <input id="pd-stock" name="stock_qty" type="text" inputmode="numeric" value="<?= e($val('stock_qty')) ?>" placeholder="0" aria-invalid="<?= e(isset($errors['stock_qty']) ? 'true' : 'false') ?>" required>
            <?php if (isset($errors['stock_qty'])) : ?><p class="field-error" role="alert"><?= e($errors['stock_qty']) ?></p><?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
      <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>><?= e($isNew ? 'Create product' : 'Save details') ?></button></p>
    </form>

    <?php if (!$isNew) : ?>
      <?php foreach ($product['sizes'] as $s) : ?>
        <?php $sid = (int) $s['id']; ?>
        <section class="card form-section" aria-labelledby="sz-<?= e($sid) ?>">
          <h2 id="sz-<?= e($sid) ?>">Size: <?= e($s['label']) ?></h2>
          <form method="post" action="/admin/products/<?= e($product['id']) ?>/size/<?= e($sid) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="field-grid">
              <div class="field">
                <label for="sz-l-<?= e($sid) ?>">Size or pack</label>
                <input id="sz-l-<?= e($sid) ?>" name="label" type="text" maxlength="80" value="<?= e($s['label']) ?>" required>
              </div>
              <div class="field">
                <label for="sz-s-<?= e($sid) ?>">In stock (<?= e($stockLabel[$s['stock_status']] ?? '') ?>)</label>
                <input id="sz-s-<?= e($sid) ?>" name="stock_qty" type="text" inputmode="numeric" value="<?= e($s['stock_qty']) ?>" required>
              </div>
            </div>
            <div class="field">
              <label for="sz-r-<?= e($sid) ?>">Reason, if you change the count</label>
              <input id="sz-r-<?= e($sid) ?>" name="stock_reason" type="text" maxlength="200" placeholder="Stock take, delivery received, damaged...">
            </div>
            <?php if ($isOwner) : ?>
              <div class="field">
                <label for="sz-p-<?= e($sid) ?>">Price (GH₵)</label>
                <input id="sz-p-<?= e($sid) ?>" name="price" type="text" inputmode="decimal" value="<?= e(Belis\Domain\CatalogueAdmin::plainPrice((int) $s['price_pesewas'])) ?>">
                <?php if (!$fresh) : ?><p class="hint">Changing a price asks for an emailed code first.</p><?php endif; ?>
              </div>
              <label class="opt"><input type="checkbox" name="confirm_big" value="1"><span>I am sure about a price change of more than <?= e($big) ?> percent</span></label>
            <?php else : ?>
              <p class="card-meta">Price: <strong><?= e(money((int) $s['price_pesewas'])) ?></strong> (only the owner can change prices)</p>
            <?php endif; ?>
            <?php if (isset($errors['size_' . $sid])) : ?><p class="field-error" role="alert"><?= e($errors['size_' . $sid]) ?></p><?php endif; ?>
            <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Save size</button></p>
          </form>

          <?php if ($isOwner) : ?>
            <h3>Bulk prices</h3>
            <p class="hint">Lower prices for larger orders. Each price must be below the one before it. Leave a row empty to remove it.</p>
            <form method="post" action="/admin/products/<?= e($product['id']) ?>/tiers/<?= e($sid) ?>" class="form" novalidate>
              <?= csrf_field() ?>
              <?php for ($i = 0; $i < Belis\Domain\CatalogueAdmin::MAX_TIERS; $i++) : ?>
                <?php $t = $s['tiers'][$i] ?? null; ?>
                <div class="field-grid">
                  <div class="field"><label for="tm-<?= e($sid) ?>-<?= e($i) ?>">From quantity</label><input id="tm-<?= e($sid) ?>-<?= e($i) ?>" name="tier_min[]" type="text" inputmode="numeric" value="<?= e($t === null ? '' : $t['min_qty']) ?>"></div>
                  <div class="field"><label for="tp-<?= e($sid) ?>-<?= e($i) ?>">Price each (GH₵)</label><input id="tp-<?= e($sid) ?>-<?= e($i) ?>" name="tier_price[]" type="text" inputmode="decimal" value="<?= e($t === null ? '' : Belis\Domain\CatalogueAdmin::plainPrice((int) $t['unit_price_pesewas'])) ?>"></div>
                </div>
              <?php endfor; ?>
              <?php if (isset($errors['tiers_' . $sid])) : ?><p class="field-error" role="alert"><?= e($errors['tiers_' . $sid]) ?></p><?php endif; ?>
              <p><button type="submit" class="btn-outline"<?= flag(!$canEdit, 'disabled') ?>>Save bulk prices</button></p>
            </form>
          <?php elseif ($s['tiers'] !== []) : ?>
            <p class="card-meta">Bulk prices:
              <?php foreach ($s['tiers'] as $t) : ?><?= e($t['min_qty']) ?>+ at <?= e(money((int) $t['unit_price_pesewas'])) ?>; <?php endforeach; ?></p>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>

      <?php if ($isOwner) : ?>
        <section class="card form-section" aria-labelledby="sz-add">
          <h2 id="sz-add">Add a size</h2>
          <form method="post" action="/admin/products/<?= e($product['id']) ?>/addsize" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="field-grid">
              <div class="field"><label for="ad-label">Size or pack</label><input id="ad-label" name="label" type="text" maxlength="80" value="<?= e($val('label')) ?>"></div>
              <div class="field"><label for="ad-price">Price (GH₵)</label><input id="ad-price" name="price" type="text" inputmode="decimal" value="<?= e($val('price')) ?>"></div>
            </div>
            <div class="field">
              <label for="ad-stock">How many in stock</label>
              <input id="ad-stock" name="stock_qty" type="text" inputmode="numeric" value="<?= e($val('stock_qty')) ?>" placeholder="0">
            </div>
            <?php if (isset($errors['add'])) : ?><p class="field-error" role="alert"><?= e($errors['add']) ?></p><?php endif; ?>
            <p><button type="submit" class="btn-outline"<?= flag(!$canEdit, 'disabled') ?>>Add size</button></p>
          </form>
        </section>
      <?php endif; ?>

      <section class="card" aria-labelledby="sh-title">
        <h2 id="sh-title">Stock history</h2>
        <p class="hint">Low stock means <?= e(Belis\Domain\CatalogueAdmin::LOW_STOCK) ?> or fewer. A paid order takes its items off automatically.</p>
        <?php if ($stockHistory === []) : ?>
          <p class="empty-note">No stock changes recorded yet.</p>
        <?php else : ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th scope="col">When (UTC)</th><th scope="col">Size</th><th scope="col">Change</th><th scope="col">Now</th><th scope="col">Why</th><th scope="col">By</th></tr></thead>
              <tbody>
                <?php foreach ($stockHistory as $h) : ?>
                  <tr><td><?= e(gmdate('d M Y H:i', (int) $h['created_at'])) ?></td><td><?= e($h['label']) ?></td><td><?= e((int) $h['delta'] > 0 ? '+' . $h['delta'] : (string) $h['delta']) ?></td><td><?= e($h['qty_after']) ?></td><td><?= e($h['reason']) ?></td><td><?= e($h['email'] ?? 'Automatic') ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="card" aria-labelledby="ph-title">
        <h2 id="ph-title">Price history</h2>
        <?php if ($history === []) : ?>
          <p class="empty-note">No price changes recorded yet.</p>
        <?php else : ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th scope="col">When (UTC)</th><th scope="col">Size</th><th scope="col">Change</th><th scope="col">Was</th><th scope="col">Now</th><th scope="col">By</th></tr></thead>
              <tbody>
                <?php foreach ($history as $h) : ?>
                  <tr><td><?= e(gmdate('d M Y H:i', (int) $h['created_at'])) ?></td><td><?= e($h['label']) ?></td><td><?= e($h['kind'] === 'tiers' ? 'Bulk prices' : ($h['kind'] === 'created' ? 'Added' : 'Price')) ?></td><td><?= e($h['old_value'] ?? '') ?></td><td><?= e($h['new_value']) ?></td><td><?= e($h['email'] ?? '') ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
      <?php if ((int) ($product['is_mock'] ?? 0) === 1) : ?><p class="mock-note">This is a sample product from the mock data. Remove sample data with bin/purge-mock.php before launch.</p><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
