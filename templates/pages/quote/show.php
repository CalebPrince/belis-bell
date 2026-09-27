<?php
/**
 * One quote for its owner: the request, the conversation, the priced offer, and accept or decline.
 *
 * @var array<string,mixed> $quote
 */
$labels = Belis\Domain\Quotes::STATUSES;
$status = (string) $quote['status'];
$offer = $quote['offer'];
$open = $status === 'quoted' && $offer !== null && !$offer['expired'];
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/quotes">My quotes</a></li><li aria-hidden="true">/</li><li aria-current="page"><?= e($quote['ref']) ?></li></ol></nav>
  <h1 class="page-title page-title-lg">Quote <?= e($quote['ref']) ?></h1>
  <p class="lead"><span class="badge quote-<?= e($status) ?>"><?= e($labels[$status] ?? '') ?></span> Sent <?= e(gmdate('d M Y', (int) $quote['created_at'])) ?><?php if ($quote['needed_by'] !== null) : ?>, needed by <?= e(gmdate('d M Y', (int) $quote['needed_by'])) ?><?php endif; ?>.</p>
</div>

<div class="wrap quote-wrap">
  <div class="account-main">
    <?php if ($offer !== null) : ?>
      <section class="card" aria-labelledby="qs-offer">
        <h2 id="qs-offer">Our quote</h2>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">Item</th><th scope="col">Qty</th><th scope="col">Price each</th><th scope="col">Total</th></tr></thead>
            <tbody>
              <?php foreach ($offer['lines'] as $l) : ?>
                <tr><th scope="row"><?= e($l['name']) ?></th><td><?= e($l['qty']) ?></td><td><?= e(money((int) $l['unit_pesewas'])) ?></td><td><?= e(money((int) $l['unit_pesewas'] * (int) $l['qty'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <dl class="sum-list"><div class="sum-total"><dt>Total</dt><dd><?= e(money((int) $offer['total_pesewas'])) ?></dd></div></dl>
        <p class="card-meta">Valid until <?= e(gmdate('d M Y', (int) $offer['valid_until'])) ?> UTC.<?= e($offer['expired'] && $status === 'quoted' ? ' This offer has expired: ask us for a new one below.' : '') ?></p>
        <?php if ((string) ($offer['note'] ?? '') !== '') : ?><p><?= e($offer['note']) ?></p><?php endif; ?>
        <?php if ($open) : ?>
          <form method="post" action="/quotes/<?= e(rawurlencode((string) $quote['ref'])) ?>/answer" class="form-inline">
            <?= csrf_field() ?>
            <button type="submit" name="choice" value="accept" class="btn-primary">Accept this quote</button>
            <button type="submit" name="choice" value="decline" class="btn-quiet">Decline</button>
          </form>
          <p class="hint">Accepting tells our team to arrange your order with you. Nothing is charged here.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="card" aria-labelledby="qs-items">
      <h2 id="qs-items">What you asked for</h2>
      <?php if ((string) ($quote['org_name'] ?? '') !== '') : ?><p class="card-meta"><?= e($quote['org_name']) ?></p><?php endif; ?>
      <ul class="addr-list">
        <?php foreach ($quote['items'] as $i) : ?>
          <li><strong><?= e($i['name']) ?></strong> x <?= e($i['qty']) ?><?php if ((string) ($i['note'] ?? '') !== '') : ?><br><span class="card-meta"><?= e($i['note']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section id="thread" class="card" aria-labelledby="qs-thread">
      <h2 id="qs-thread">Conversation</h2>
      <?php if ($quote['messages'] === []) : ?><p class="empty-note">No messages yet.</p><?php endif; ?>
      <ul class="thread">
        <?php foreach ($quote['messages'] as $m) : ?>
          <li class="msg msg-<?= e($m['author_role']) ?>">
            <p class="msg-meta"><strong><?= e($m['author_role'] === 'system' ? 'Update' : ($m['author_role'] === 'staff' ? 'Belis Bell' : 'You')) ?></strong> <?= e(gmdate('d M Y H:i', (int) $m['created_at'])) ?> UTC</p>
            <p class="msg-body"><?= e($m['body']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($status !== 'closed') : ?>
        <form method="post" action="/quotes/<?= e(rawurlencode((string) $quote['ref'])) ?>/reply" class="form" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="qs-body">Write a message</label>
            <textarea id="qs-body" name="body" rows="4" maxlength="2000" required></textarea>
          </div>
          <p><button type="submit" class="btn-primary">Send</button></p>
        </form>
      <?php else : ?>
        <p class="hint">This quote is closed. <a href="/quote/new">Start a new request</a> if you need something else.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
