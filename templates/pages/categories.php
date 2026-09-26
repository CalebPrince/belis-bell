<?php
/** @var list<array<string,mixed>> $categories */
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li aria-current="page">Categories</li></ol></nav>
  <h1 class="page-title">Categories</h1>
  <p class="section-sub left">Pick a category to see its products.</p>
</div>
<section class="section-tight" aria-label="All categories">
  <div class="wrap">
    <?php if ($categories === []) : ?>
      <p class="empty-note">No categories yet.</p>
    <?php else : ?>
      <ul class="cat-grid">
        <?php foreach ($categories as $cat) : ?>
          <li>
            <a class="cat-card" href="/c/<?= e(rawurlencode((string) $cat['slug'])) ?>">
              <span class="cat-media"><?= image_html('categories/' . $cat['slug'], (string) $cat['name'], '(min-width: 1024px) 16vw, 45vw', ['decorative' => true, 'placeholder_class' => 'ph-square']) ?></span>
              <span class="cat-name"><?= e($cat['name']) ?></span>
              <span class="cat-go"><?= icon('arrow-right') ?><span class="sr-only">Browse <?= e($cat['name']) ?></span></span>
            </a>
            <p class="cat-blurb"><?= e($cat['blurb']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
