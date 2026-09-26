<?php
/**
 * Owner-only activity log. Read only. It holds who did what and when, never secrets or customer details.
 *
 * @var array<string,string> $staff
 * @var array{items:list<array<string,mixed>>,total:int,pages:int,page:int} $result
 * @var string $area
 * @var bool $preview
 */
$active = 'audit';
$areas = Belis\Support\Audit::AREAS;
$page = $result['page'];
$pages = $result['pages'];
?>
<div class="wrap page-head">
  <p class="eyebrow">Staff area</p>
  <h1 class="page-title page-title-lg">Activity log</h1>
  <p class="lead"><?= e($result['total']) ?> <?= e($result['total'] === 1 ? 'entry' : 'entries') ?>. Entries cannot be edited or deleted.</p>
</div>

<div class="wrap admin-grid">
  <?php include __DIR__ . '/../../partials/admin_nav.php'; ?>
  <div class="account-main">
    <form method="get" action="/admin/audit" class="card filter-bar" aria-label="Filter the log">
      <div class="field">
        <label for="au-area">Show</label>
        <select id="au-area" name="area">
          <?php foreach ($areas as $k => $label) : ?><option value="<?= e($k) ?>"<?= flag($area === $k, 'selected') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions"><button type="submit" class="btn-primary">Filter</button><a class="btn-quiet" href="/admin/audit">Clear</a></div>
    </form>
    <section class="card" aria-labelledby="au-title">
      <h2 id="au-title" class="sr-only">Log</h2>
      <?php if ($result['items'] === []) : ?>
        <p class="empty-note">Nothing recorded.</p>
      <?php else : ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th scope="col">When (UTC)</th><th scope="col">Who</th><th scope="col">What</th><th scope="col">Item</th><th scope="col">Note</th><th scope="col">From</th></tr></thead>
            <tbody>
              <?php foreach ($result['items'] as $a) : ?>
                <tr><td><?= e(gmdate('d M Y H:i:s', (int) $a['created_at'])) ?></td><td><?= e($a['email'] ?? 'Not signed in') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['target']) ?></td><td><?= e($a['detail'] ?? '') ?></td><td><?= e($a['ip']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1) : ?>
          <nav class="pager" aria-label="Pages">
            <?php for ($i = 1; $i <= $pages; $i++) : ?>
              <a class="pg" href="<?= e(query_url('/admin/audit', ['area' => $area, 'page' => (string) $i])) ?>"<?= flag($i === $page, 'aria-current="page"') ?>><?= e($i) ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php if ($preview) : ?><p class="mock-note">Local preview: opening this log is not recorded.</p><?php endif; ?>
  </div>
</div>
