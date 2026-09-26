<?php
/**
 * Category list with an add form. Nothing is deleted: hide a category instead.
 *
 * @var array<string,string> $staff
 * @var list<array<string,mixed>> $tree
 * @var array<string,mixed> $old
 * @var array<string,string> $errors
 * @var bool $canEdit
 */
$active = 'categories';
$val = static fn (string $k): string => is_string($old[$k] ?? null) ? $old[$k] : '';
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Categories</h1>
  <p class="lead">The groups shoppers browse by. Add a subcategory from the category's own page.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <section class="card" aria-labelledby="ct-list">
      <h2 id="ct-list">All categories</h2>
      <?php if ($tree === []) : ?>
        <p class="empty-note">No categories yet.</p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">Category</th><th scope="col">Order</th><th scope="col">Products</th><th scope="col">Shop</th></tr></thead>
            <tbody>
              <?php foreach ($tree as $t) : ?>
                <tr>
                  <th scope="row"><a href="/admin/categories/<?= e($t['id']) ?>"><?= e($t['name']) ?></a></th>
                  <td><?= e($t['sort_order']) ?></td><td><?= e($t['products']) ?></td>
                  <td><span class="badge<?= e((int) $t['is_published'] === 1 ? ' state-paid' : '') ?>"><?= e((int) $t['is_published'] === 1 ? 'Shown' : 'Hidden') ?></span></td>
                </tr>
                <?php foreach ($t['subs'] as $s) : ?>
                  <tr>
                    <td class="sub-row"><a href="/admin/categories/<?= e($s['id']) ?>"><?= e($s['name']) ?></a></td>
                    <td><?= e($s['sort_order']) ?></td><td><?= e($s['products']) ?></td>
                    <td><span class="badge<?= e((int) $s['is_published'] === 1 ? ' state-paid' : '') ?>"><?= e((int) $s['is_published'] === 1 ? 'Shown' : 'Hidden') ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="card form-section" aria-labelledby="ct-add">
      <h2 id="ct-add">Add a category</h2>
      <?php if (!$canEdit) : ?><p class="mock-note">The local preview person can look but not change anything.</p><?php endif; ?>
      <form method="post" action="/admin/categories" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="ct-name">Name</label>
          <input id="ct-name" name="name" type="text" maxlength="120" value="<?= e($val('name')) ?>" aria-invalid="<?= e(isset($errors['name']) ? 'true' : 'false') ?>" required>
          <?php if (isset($errors['name'])) : ?><p class="field-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="ct-blurb">Short description (optional)</label>
          <input id="ct-blurb" name="blurb" type="text" maxlength="255" value="<?= e($val('blurb')) ?>">
          <?php if (isset($errors['blurb'])) : ?><p class="field-error" role="alert"><?= e($errors['blurb']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="ct-sort">Order (lower numbers come first)</label>
          <input id="ct-sort" name="sort_order" type="text" inputmode="numeric" value="<?= e($val('sort_order')) ?>" placeholder="10">
          <?php if (isset($errors['sort_order'])) : ?><p class="field-error" role="alert"><?= e($errors['sort_order']) ?></p><?php endif; ?>
        </div>
        <p class="hint">It starts hidden. Open it afterwards to show it in the shop.</p>
        <p><button type="submit" class="btn-primary"<?= flag(!$canEdit, 'disabled') ?>>Add category</button></p>
      </form>
    </section>
  </div>
</div>
