<?php
/**
 * A customer's own quote requests.
 *
 * @var list<array<string,mixed>> $quotes
 * @var bool $preview
 */
$labels = Belis\Domain\Quotes::STATUSES;
?>
<div class="wrap page-head">
  <nav aria-label="Breadcrumb" class="crumbs"><ol><li><a href="/">Home</a></li><li aria-hidden="true">/</li><li><a href="/account">My account</a></li><li aria-hidden="true">/</li><li aria-current="page">My quotes</li></ol></nav>
  <h1 class="page-title page-title-lg">My quotes</h1>
  <p class="lead"><a class="btn-primary" href="/quote/new">Request a quote<?= icon('arrow-right') ?></a></p>
</div>
<div class="wrap">
  <section class="card" aria-labelledby="mq-title">
    <h2 id="mq-title" class="sr-only">Quote requests</h2>
    <?php if ($quotes === []) : ?>
      <p class="empty-note">You have not asked for a quote yet.<?= e($preview ? ' (Local preview: nothing to show.)' : '') ?></p>
    <?php else : ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th scope="col">Request</th><th scope="col">Sent</th><th scope="col">Items</th><th scope="col">Status</th><th scope="col">Last update</th></tr></thead>
          <tbody>
            <?php foreach ($quotes as $q) : ?>
              <tr>
                <th scope="row"><a href="/quotes/<?= e(rawurlencode((string) $q['ref'])) ?>"><?= e($q['ref']) ?></a></th>
                <td><?= e(gmdate('d M Y', (int) $q['created_at'])) ?></td>
                <td><?= e($q['items']) ?></td>
                <td><span class="badge quote-<?= e($q['status']) ?>"><?= e($labels[$q['status']] ?? '') ?></span></td>
                <td><?= e(gmdate('d M Y H:i', (int) $q['updated_at'])) ?> UTC</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
