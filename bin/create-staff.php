<?php
declare(strict_types=1);

// Creates a verified staff or owner account. Staff cannot register themselves, so this is the only way in.
// Usage: php bin/create-staff.php email@example.com "Full Name" staff|owner
// The password is read from the first line of standard input, never from the command line, so it does not
// end up in shell history. Example: type the command, then the password, then Enter.
require __DIR__ . '/_boot.php';

use Belis\Core\Db;
use Belis\Domain\Accounts;
use Belis\Support\Mailer;

[$script, $email, $name, $role] = array_pad($argv, 4, '');
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || trim($name) === '' || !in_array($role, ['staff', 'owner'], true)) {
    fwrite(STDERR, "Usage: php bin/create-staff.php email \"Full Name\" staff|owner   (password on standard input)\n");
    exit(2);
}
fwrite(STDERR, 'Password: ');
$password = rtrim((string) fgets(STDIN), "\r\n");
$problem = Accounts::passwordProblem($password, $email, $name);
if ($problem !== null) {
    fwrite(STDERR, "\n{$problem}\n");
    exit(1);
}
$accounts = new Accounts(Db::fromEnv(), Mailer::fromEnv());
try {
    $id = $accounts->createVerified($email, $name, 'n/a', $password, $role);
} catch (\Throwable $e) {
    fwrite(STDERR, "\nCould not create the account (does the email already exist?)\n");
    exit(1);
}
echo "\nCreated {$role} account #{$id}. They sign in at /admin/sign-in with a password and an emailed code.\n";
