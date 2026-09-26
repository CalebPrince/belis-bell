<?php
/**
 * Edit one category or subcategory, and add subcategories under a category. The web address and the parent are fixed.
 *
 * @var array<string,string> $staff
 * @var array<string,mixed> $cat
 * @var array<string,mixed> $old
 * @var array<string,string> $errors
 * @var bool $canEdit
 * @var bool $photo
 */
$active = 'categories';
$val = static fn (string $k, mixed $fallback): string => is_string($old[$k] ?? null) ? $old[$k] : (string) ($fallback ?? '');
$isSub = $cat['parent_id'] !== null;
$published = $old !== [] && isset($old['name']) ? ($old['is_published'] ?? '') === '1' : (int) $cat['is_published'] === 1;
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/admin">Admin</a></li><li aria-hidden="true">/</li><li><a href="/admin/categories">Categories</a></li><li aria-hidden="true">/</li>
    <?php if ($isSub) : ?><li><a href="/admin/categories/<?= e($cat['parent_id']) ?>"><?= e($cat['parent_name']) ?></a></li><li aria-hidden="true">/</li><?php endif; ?>
    <li aria-current="page"><?= e($cat['name']) ?></li></ol></nav>
  <h1 class="page-title page-title-lg"><?= e($cat['name']) ?></h1>
  <p class="lead"><?= e($isSub ? 'Subcategory of ' . $cat['parent_name'] : 'Category') ?>. Web address: <?= e($isSub ? '/c/' . $cat['slug'] . ' (not used for subcategories)' : '/c/' . $cat['slug']) ?>. <?= e($cat['products']) ?> <?= e((int) $cat['products'] === 1 ? 'product' : 'products') ?>.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <?php if (!$canEdit) : ?><p class="mock-note">The local preview person can look but not change anything.</p><?php endif; ?>
    <section class="card form-section" aria-labelledby="cd-title">
      <h2 id="cd-title">Details</h2>
      <form method="post" action="/admin/categories/<?= e($cat['id']) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="cd-name">Name</label>
          <input id="cd-name" name="name" type="text" maxlength="120" value="<?= e($val('name', $cat['name'])) ?>" aria-invalid="<?= e(isset($errors['name']) ? 'true' : 'false') ?>" required>
          <?php if (isset($errors['name'])) : ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="cd-blurb">Short description (optional)</label>
          <input id="cd-blurb" name="blurb" type="text" maxlength="255" value="<?= e($val('blurb', $cat['blurb'])) ?>">
          <?php if (isset($errors['blurb'])) : ?><p class="field-error" role="alert"><?= e($errors['blurb']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="cd-sort">Order (lower numbers come first)</label>
          <input id="cd-sort" name="sort_order" type="text" inputmode="numeric" value="<?= e($val('sort_order', $cat['sort_order'])) ?>">
          <?php if (isset($errors['sort_order'])) : ?><p class="field-error" role="alert"><?= e($errors['sort_order']) ?></p><?php endif; ?>
        </div>
        <label class="opt"><input type="checkbox" name="is_published" value="1"<?= flag($published, 'checked') ?>><span>Show in the shop</span></label>
        <?php if (!$isSub) : ?><p class="hint">Hiding a category removes it, and its products, from the shop pages. Its products keep their details.</p><?php endif; ?>
        <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Save</button></p>
      </form>
      <?php if (!$isSub) : ?>
        <p class="hint">Photo: <?= e($photo ? 'a photo is in place for this category.' : 'no photo yet, a placeholder is shown. Supply one for the slot categories/' . $cat['slug'] . ' (see docs/IMAGES.md).') ?></p>
      <?php endif; ?>
    </section>

    <?php if (!$isSub) : ?>
      <section class="card" aria-labelledby="cs-title">
        <h2 id="cs-title">Subcategories</h2>
        <?php if ($cat['subs'] === []) : ?>
          <p class="empty-note">No subcategories yet.</p>
        <?php else : ?>
          <ul class="addr-list">
            <?php foreach ($cat['subs'] as $s) : ?>
              <li><a href="/admin/categories/<?= e($s['id']) ?>"><?= e($s['name']) ?></a> <span class="badge<?= e((int) $s['is_published'] === 1 ? ' state-paid' : '') ?>"><?= e((int) $s['is_published'] === 1 ? 'Shown' : 'Hidden') ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <h3>Add a subcategory</h3>
        <form method="post" action="/admin/categories" class="form" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="parent_id" value="<?= e($cat['id']) ?>">
          <div class="field">
            <label for="sc-name">Name</label>
            <input id="sc-name" name="name" type="text" maxlength="120" required>
            <?php foreach (['parent', 'sub_name'] as $ek) : ?><?php if (isset($errors[$ek])) : ?><p class="field-error" role="alert"><?= e($errors[$ek]) ?></p><?php endif; ?><?php endforeach; ?>
          </div>
          <div class="field">
            <label for="sc-sort">Order</label>
            <input id="sc-sort" name="sort_order" type="text" inputmode="numeric" placeholder="10">
            <?php if (isset($errors['sub_sort_order'])) : ?><p class="field-error" role="alert"><?= e($errors['sub_sort_order']) ?></p><?php endif; ?>
          </div>
          <p><button type="submit" class="btn-outline"<?= flag(!$canEdit, 'disabled') ?>>Add subcategory</button></p>
        </form>
      </section>
    <?php endif; ?>
    <?php if ((int) $cat['is_mock'] === 1) : ?><p class="mock-note">This is a sample category from the mock data.</p><?php endif; ?>
  </div>
</div>
