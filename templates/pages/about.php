<?php
/** @var list<array{icon:string,title:string,line:string}> $why */
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li aria-current="page">About</li></ol></nav>
  <p class="eyebrow">About Belis Bell</p>
  <h1 class="page-title">Cleaning supplies and more</h1>
  <p class="lead">Belis Bell supplies washroom and cleaning products to homes, businesses and institutions in Ghana.</p>
  <?php if (is_mock_mode()) : ?><p class="mock-note">Sample text. Belis Bell supplies its own story, history and team details.</p><?php endif; ?>
</div>

<section class="section-tight" aria-labelledby="values-title">
  <div class="wrap">
    <h2 id="values-title" class="section-title left">What we stand for</h2>
    <ul class="why-grid">
      <?php foreach ($why as $w) : ?>
        <li>
          <span class="why-icon"><?= icon($w['icon']) ?></span>
          <h3><?= e($w['title']) ?></h3>
          <p><?= e($w['line']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="btn-row"><a class="btn-primary" href="/contact">Contact us<?= icon('arrow-right') ?></a></p>
  </div>
</section>
