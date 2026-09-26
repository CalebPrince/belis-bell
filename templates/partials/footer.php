<?php $nav = site_nav(); $contact = site_contact(); $socials = site_socials(); ?>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <div class="logo-chip">
        <img src="<?= asset('brand/logo-full.webp') ?>" alt="Belis Bell, cleaning supplies and more for homes, businesses and institutions" width="220" height="220" loading="lazy">
      </div>
      <p>Quality cleaning supplies and more for homes, businesses and institutions across Ghana.</p>
      <?php if ($socials !== []) : ?>
        <ul class="social-list" aria-label="Social media">
          <?php foreach ($socials as $s) : ?>
            <li><a href="<?= e($s['url']) ?>" rel="noopener noreferrer" target="_blank"><?= e($s['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div>
      <h2>Quick Links</h2>
      <ul>
        <?php foreach ($nav as $link) : ?>
          <li><a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div>
      <h2>Contact</h2>
      <ul class="contact-list">
        <?php if ($contact['address'] !== null) : ?><li><?= icon('map-pin') ?><span><?= e($contact['address']) ?></span></li><?php endif; ?>
        <?php if ($contact['phone'] !== null) : ?><li><?= icon('phone') ?><span><?= e($contact['phone']) ?></span></li><?php endif; ?>
        <?php if ($contact['email'] !== null) : ?><li><?= icon('mail') ?><span><?= e($contact['email']) ?></span></li><?php endif; ?>
        <?php if ($contact['hours'] !== null) : ?><li><?= icon('clock') ?><span><?= e($contact['hours']) ?></span></li><?php endif; ?>
      </ul>
      <?php if ($contact['sample']) : ?><p class="mock-note-dark">Sample contact details.</p><?php endif; ?>
    </div>
  </div>
  <div class="footer-base">
    <div class="wrap"><p>&copy; <?= e(date('Y')) ?> Belis Bell. All rights reserved.</p></div>
  </div>
</footer>
