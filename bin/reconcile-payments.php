<?php
declare(strict_types=1);

// Daily job (cPanel cron): asks the payment provider about every unpaid order older than 5 minutes, marks the ones
// that were paid, and cancels orders still unpaid after 24 hours (CTL-PAY-002). Safe to run as often as you like.
// Example cron line:  0 3 * * *  /usr/local/bin/php /home/ACCOUNT/belis-bell/bin/reconcile-payments.php
require __DIR__ . '/_boot.php';

use Belis\Core\Db;
use Belis\Domain\Orders;
use Belis\Domain\Refunds;
use Belis\Payments\Payments;

$adapter = Payments::adapter();
$r = (new Orders(Db::fromEnv()))->reconcile($adapter);
$f = (new Refunds(Db::fromEnv()))->reconcile($adapter);
echo sprintf("orders: checked %d, marked paid %d, expired %d, lookup errors %d\nrefunds: checked %d, lookup errors %d\n", $r['checked'], $r['paid'], $r['expired'], $r['errors'], $f['checked'], $f['errors']);
exit($r['errors'] + $f['errors'] > 0 ? 1 : 0);
