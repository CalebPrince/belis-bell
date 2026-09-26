<?php
/**
 * @var list<array{slot:string,icon:string,title:string,line:string,alt:string,focus:string}> $audiences
 * @var string|null $whatsapp
 * @var array{address:?string,phone:?string,email:?string,hours:?string,sample:bool} $contact
 */
$steps = [
    ['Tell us what you need', 'Add products to a list or describe what your organisation buys. You can attach a requirements sheet.'],
    ['We send a quote', 'Our team replies with prices and delivery details. You can ask questions in the same conversation.'],
    ['Approve and receive', 'Approve the quote, and we prepare and deliver your order. Repeat orders can reuse a past quote.'],
];
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li aria-current="page">For Businesses</li></ol></nav>
  <p class="eyebrow">For banks, schools, government and private companies</p>
  <h1 class="page-title">Supplies for your organisation</h1>
  <p class="lead">Regular supply of washroom and cleaning products, with a quote for each order.</p>
  <?php if (is_mock_mode()) : ?><p class="mock-note">Sample text. Belis Bell supplies the final wording.</p><?php endif; ?>
</div>

<section class="section-tight" aria-labelledby="how-title">
  <div class="wrap">
    <h2 id="how-title" class="section-title left">How a quote works</h2>
    <ol class="steps">
      <?php foreach ($steps as $i => [$title, $line]) : ?>
        <li><span class="step-n"><?= e($i + 1) ?></span><h3><?= e($title) ?></h3><p><?= e($line) ?></p></li>
      <?php endforeach; ?>
    </ol>
    <div class="btn-row">
      <button type="button" class="btn-primary" disabled>Request a quote</button>
      <?php if ($whatsapp !== null) : ?><a class="btn-outline" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a><?php endif; ?>
      <a class="btn-outline" href="/contact">Contact us</a>
    </div>
    <p class="hint">Quote requests are not built yet, so the button is switched off.</p>
  </div>
</section>

<section class="section" aria-labelledby="who-title">
  <div class="wrap">
    <h2 id="who-title" class="section-title left">Who we supply</h2>
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
