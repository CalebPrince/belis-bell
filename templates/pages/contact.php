<?php
/**
 * @var array{address:?string,phone:?string,email:?string,hours:?string,sample:bool} $contact
 * @var string|null $whatsapp
 */
$tel = $contact['phone'] !== null ? Belis\Support\Site::phoneHref($contact['phone']) : null;
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li aria-current="page">Contact</li></ol></nav>
  <h1 class="page-title">Contact us</h1>
  <p class="lead">Talk to a person about an order, a quote or a product.</p>
  <?php if ($contact['sample']) : ?><p class="mock-note">Sample contact details. Belis Bell supplies the real ones.</p><?php endif; ?>
</div>

<section class="section-tight" aria-label="Contact details">
  <div class="wrap contact-grid">
    <ul class="contact-cards">
      <?php if ($contact['address'] !== null) : ?><li class="card"><?= icon('map-pin', 'icon icon-lg') ?><h2>Address</h2><p><?= e($contact['address']) ?></p></li><?php endif; ?>
      <?php if ($contact['phone'] !== null) : ?>
        <li class="card"><?= icon('phone', 'icon icon-lg') ?><h2>Phone</h2>
          <p><?php if ($tel !== null) : ?><a href="<?= e($tel) ?>"><?= e($contact['phone']) ?></a><?php else : ?><?= e($contact['phone']) ?><?php endif; ?></p></li>
      <?php endif; ?>
      <?php if ($contact['email'] !== null) : ?><li class="card"><?= icon('mail', 'icon icon-lg') ?><h2>Email</h2><p><?= e($contact['email']) ?></p></li><?php endif; ?>
      <?php if ($contact['hours'] !== null) : ?><li class="card"><?= icon('clock', 'icon icon-lg') ?><h2>Opening hours</h2><p><?= e($contact['hours']) ?></p></li><?php endif; ?>
    </ul>
    <div class="card contact-cta">
      <h2>Prefer to chat?</h2>
      <?php if ($whatsapp !== null) : ?>
        <p>Send us a message on WhatsApp.</p>
        <p><a class="btn-primary" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Chat on WhatsApp</a></p>
      <?php else : ?>
        <p>A WhatsApp link will appear here once Belis Bell adds its number.</p>
      <?php endif; ?>
      <p class="hint">A contact form is not built yet.</p>
    </div>
  </div>
</section>
