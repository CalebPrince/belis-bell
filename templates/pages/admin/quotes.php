<?php
/**
 * Staff quote list (sales role and owner).
 *
 * @var array<string,string> $staff
 * @var array{items:list<array<string,mixed>>,total:int,pages:int,page:int} $result
 * @var array{status:string,q:string,page:int} $filters
 * @var bool $preview
 */
$active = 'quotes';
$labels = Belis\Domain\Quotes::STATUSES;
$keep = ['status' => $filters['status'], 'q' => $filters['q']];
$page = $result['page'];
$pages = $result['pages'];
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Quotes</h1>
  <p class="lead"><?= e($result['total']) ?> <?= e($result['total'] === 1 ? 'request' : 'requests') ?>.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <form method="get" action="/admin/quotes" class="card filter-bar" role="search" aria-label="Filter quotes">
      <div class="field">
        <label for="qf-q">Reference, email or organisation</label>
        <input id="qf-q" name="q" type="search" value="<?= e($filters['q']) ?>" maxlength="60">
      </div>
      <div class="field">
        <label for="qf-status">Status</label>
        <select id="qf-status" name="status">
          <option value="">All</option>
          <?php foreach ($labels as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($filters['status'] === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions"><button type="submit" class="btn-primary">Filter</button><a class="btn-quiet" href="/admin/quotes">Clear</a></div>
    </form>

    <section class="card" aria-labelledby="ql-title">
      <h2 id="ql-title" class="sr-only">Quote list</h2>
      <?php if ($result['items'] === []) : ?>
        <p class="empty-note">No quote requests found.<?= e($preview ? ' (Local preview: sign in as staff to see real requests.)' : '') ?></p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">Request</th><th scope="col">Customer</th><th scope="col">Items</th><th scope="col">Status</th><th scope="col">Needed by</th><th scope="col">Owner</th><th scope="col">Updated (UTC)</th></tr></thead>
            <tbody>
              <?php foreach ($result['items'] as $q) : ?>
                <tr>
                  <th scope="row"><a href="/admin/quotes/<?= e(rawurlencode((string) $q['ref'])) ?>"><?= e($q['ref']) ?></a></th>
                  <td><?= e((string) ($q['org_name'] ?? '') !== '' ? $q['org_name'] : $q['name']) ?><br><span class="card-meta"><?= e($q['email']) ?></span></td>
                  <td><?= e($q['items']) ?></td>
                  <td><span class="badge quote-<?= e($q['status']) ?>"><?= e($labels[$q['status']] ?? '') ?></span></td>
                  <td><?= e($q['needed_by'] === null ? '' : gmdate('d M Y', (int) $q['needed_by'])) ?></td>
                  <td><?= e($q['assigned_name'] ?? 'Unassigned') ?></td>
                  <td><?= e(gmdate('d M Y H:i', (int) $q['updated_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1) : ?>
          <nav class="pager" aria-label="Pages">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
              <a class="pg" href="<?= e(query_url('/admin/quotes', $keep + ['page' => (string) $i])) ?>"<?= flag($i === $page, 'aria-current="page"') ?>><?= e($i) ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
