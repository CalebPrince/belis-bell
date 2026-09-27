<?php
/**
 * One quote for staff: the request, the thread, a reply box, status buttons and the priced offer form. Opening this
 * page and every action are written to the audit log.
 *
 * @var array<string,string> $staff
 * @var array<string,mixed> $quote
 */
$active = 'quotes';
$labels = Belis\Domain\Quotes::STATUSES;
$status = (string) $quote['status'];
$ref = rawurlencode((string) $quote['ref']);
$offer = $quote['offer'];
$canOffer = in_array($status, ['new', 'in_progress', 'quoted'], true);
$prev = [];
if ($offer !== null) {
    foreach ($offer['lines'] as $i => $l) {
        $prev[$i] = $l['unit_pesewas'];
    }
}
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/admin">Admin</a></li><li aria-hidden="true">/</li><li><a href="/admin/quotes">Quotes</a></li><li aria-hidden="true">/</li><li aria-current="page"><?= e($quote['ref']) ?></li></ol></nav>
  <h1 class="page-title page-title-lg">Quote <?= e($quote['ref']) ?></h1>
  <p class="lead"><span class="badge quote-<?= e($status) ?>"><?= e($labels[$status] ?? '') ?></span> Sent <?= e(gmdate('d M Y H:i', (int) $quote['created_at'])) ?> UTC. Owner: <?= e($quote['assigned_name'] ?? 'Unassigned') ?>.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <section class="card" aria-labelledby="aq-cust">
      <h2 id="aq-cust">Customer</h2>
      <dl class="detail-list">
        <div><dt>Name</dt><dd><?= e($quote['customer_name']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($quote['customer_email']) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($quote['customer_phone']) ?></dd></div>
        <?php if ((string) ($quote['org_name'] ?? '') !== '') : ?><div><dt>Organisation</dt><dd><?= e($quote['org_name']) ?></dd></div><?php endif; ?>
        <?php if ($quote['needed_by'] !== null) : ?><div><dt>Needed by</dt><dd><?= e(gmdate('d M Y', (int) $quote['needed_by'])) ?></dd></div><?php endif; ?>
      </dl>
      <p class="hint">Customer details are for answering this quote only. Opening this page is recorded.</p>
      <?php if ($status !== 'closed') : ?>
        <form method="post" action="/admin/quotes/<?= e($ref) ?>/status" class="form-inline">
          <?= csrf_field() ?>
          <?php if ($status === 'new') : ?><button type="submit" name="status" value="in_progress" class="btn-primary">Take this quote</button><?php endif; ?>
          <button type="submit" name="status" value="closed" class="btn-quiet">Close this quote</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card form-section" aria-labelledby="aq-offer">
      <h2 id="aq-offer">Price and send a quote</h2>
      <?php if ($offer !== null) : ?>
        <p class="hint">Latest sent: <?= e(money((int) $offer['total_pesewas'])) ?>, valid until <?= e(gmdate('d M Y', (int) $offer['valid_until'])) ?><?= e($offer['expired'] ? ' (expired)' : '') ?>. Sending again replaces it for the customer.</p>
      <?php endif; ?>
      <?php if (!$canOffer) : ?>
        <p class="empty-note">This quote is <?= e(strtolower($labels[$status] ?? '')) ?>, so it cannot take a new offer.</p>
      <?php else : ?>
        <form method="post" action="/admin/quotes/<?= e($ref) ?>/offer" class="form" novalidate>
          <?= csrf_field() ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th scope="col">Item</th><th scope="col">Qty</th><th scope="col">Price each (GH₵)</th></tr></thead>
              <tbody>
                <?php foreach ($quote['items'] as $n => $i) : ?>
                  <tr>
                    <th scope="row"><?= e($i['name']) ?><?php if ((string) ($i['note'] ?? '') !== '') : ?><br><span class="card-meta"><?= e($i['note']) ?></span><?php endif; ?></th>
                    <td><?= e($i['qty']) ?></td>
                    <td><label class="sr-only" for="unit-<?= e($i['id']) ?>">Price each for <?= e($i['name']) ?></label><input id="unit-<?= e($i['id']) ?>" name="unit[<?= e($i['id']) ?>]" type="text" inputmode="decimal" value="<?= e(isset($prev[$n]) ? Belis\Domain\CatalogueAdmin::plainPrice((int) $prev[$n]) : '') ?>" placeholder="0.00" required></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="field-grid">
            <div class="field"><label for="aq-until">Offer valid until</label><input id="aq-until" name="valid_until" type="text" inputmode="numeric" placeholder="2026-10-31" required></div>
            <div class="field"><label for="aq-note">Note to the customer (optional)</label><input id="aq-note" name="note" type="text" maxlength="500"></div>
          </div>
          <p class="hint">The total is worked out here from your prices. The customer is emailed and can accept or decline.</p>
          <p><button type="submit" class="btn-primary">Send quote</button></p>
        </form>
      <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="aq-items">
      <h2 id="aq-items">Requested items</h2>
      <ul class="addr-list">
        <?php foreach ($quote['items'] as $i) : ?>
          <li><strong><?= e($i['name']) ?></strong> x <?= e($i['qty']) ?><?php if ((string) ($i['note'] ?? '') !== '') : ?><br><span class="card-meta"><?= e($i['note']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card" aria-labelledby="aq-thread">
      <h2 id="aq-thread">Conversation</h2>
      <?php if ($quote['messages'] === []) : ?><p class="empty-note">No messages yet.</p><?php endif; ?>
      <ul class="thread">
        <?php foreach ($quote['messages'] as $m) : ?>
          <li class="msg msg-<?= e($m['author_role']) ?>">
            <p class="msg-meta"><strong><?= e($m['author_role'] === 'system' ? 'Update' : ($m['author_name'] ?? '')) ?></strong> (<?= e($m['author_role']) ?>) <?= e(gmdate('d M Y H:i', (int) $m['created_at'])) ?> UTC</p>
            <p class="msg-body"><?= e($m['body']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($status !== 'closed') : ?>
        <form method="post" action="/admin/quotes/<?= e($ref) ?>/reply" class="form" novalidate>
          <?= csrf_field() ?>
          <div class="field"><label for="aq-body">Reply to the customer</label><textarea id="aq-body" name="body" rows="4" maxlength="2000" required></textarea></div>
          <p><button type="submit" class="btn-outline">Send reply</button></p>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
