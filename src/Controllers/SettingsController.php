<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\Session;
use Belis\Core\View;
use Belis\Domain\Accounts;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Mailer;
use Belis\Support\Settings;

/**
 * The owner-only Settings page for integration keys (CTL-SET-001). Viewing or changing anything needs a fresh emailed
 * code from the last 10 minutes. Secrets are write-only: this page never shows a saved secret, only whether it is set
 * and its last 4 characters. Every change is written to the audit log and emailed to the owner, without the values.
 */
final class SettingsController
{
    private const FRESH_SECONDS = 600;

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $owner = $this->owner();
        if ($owner === null) {
            return Response::redirect('/admin');
        }
        return $this->page($owner, [], [], 200);
    }

    /** @param array<string,string> $params */
    public function sendCode(Request $request, array $params = []): Response
    {
        $owner = $this->owner();
        if ($owner === null) {
            return Response::redirect('/admin');
        }
        try {
            (new Accounts(Db::fromEnv(), Mailer::fromEnv()))->issueCode((int) $owner['id'], 'stepup');
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Flash::notice('We emailed a 6 digit code to your address. It expires in 5 minutes.');
        return Response::redirect('/admin/settings');
    }

    /** @param array<string,string> $params */
    public function verifyCode(Request $request, array $params = []): Response
    {
        $owner = $this->owner();
        if ($owner === null) {
            return Response::redirect('/admin');
        }
        $code = is_string($request->post['code'] ?? null) ? preg_replace('/\s+/', '', $request->post['code']) : '';
        try {
            $db = Db::fromEnv();
            if (!(new Accounts($db, Mailer::fromEnv()))->checkCode((int) $owner['id'], 'stepup', (string) $code, $request->ip)) {
                Audit::add($db, (int) $owner['id'], 'settings.stepup_failed', 'settings', $request->ip);
                return $this->page($owner, [], ['code' => 'That code did not work. It may have expired or been used. Ask for a new one.'], 422);
            }
            Audit::add($db, (int) $owner['id'], 'settings.stepup', 'settings', $request->ip);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Session::start();
        $_SESSION['stepup'] = ['uid' => (int) $owner['id'], 'at' => time()];
        return Response::redirect('/admin/settings');
    }

    /** @param array<string,string> $params */
    public function save(Request $request, array $params = []): Response
    {
        $owner = $this->owner();
        if ($owner === null) {
            return Response::redirect('/admin');
        }
        if (!self::fresh((int) $owner['id'])) {
            Flash::notice('Please confirm with an emailed code first.');
            return Response::redirect('/admin/settings');
        }
        $errors = [];
        $changed = [];
        try {
            $db = Db::fromEnv();
            $pdo = $db->pdo();
            $pdo->beginTransaction();
            try {
                foreach (Settings::FIELDS as $name => $meta) {
                    $value = is_string($request->post[$name] ?? null) ? trim($request->post[$name]) : '';
                    if (($request->post['clear_' . $name] ?? '') === '1') {
                        Settings::clear($db, $name);
                        Audit::add($db, (int) $owner['id'], 'settings.clear', $name, $request->ip);
                        $changed[] = $meta['label'] . ' (removed)';
                    } elseif ($value !== '') {
                        $problem = Settings::save($db, $name, $value, (int) $owner['id']);
                        if ($problem !== null) {
                            $errors[$name] = $problem;
                            continue;
                        }
                        Audit::add($db, (int) $owner['id'], 'settings.update', $name, $request->ip);
                        $changed[] = $meta['label'];
                    }
                }
                if ($errors !== []) {
                    $pdo->rollBack();
                    Settings::forget();
                    return $this->page($owner, $request->post, $errors, 422);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Settings::forget();
                throw $e;
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($changed !== []) {
            $this->tellOwner((string) $owner['email'], $changed, $request->ip);
            Flash::notice('Saved: ' . implode(', ', $changed) . '.');
        } else {
            Flash::notice('Nothing to save.');
        }
        return Response::redirect('/admin/settings');
    }

    /** Sends a test email to the owner's own address, so a wrong SMTP setting shows up straight away. @param array<string,string> $params */
    public function testEmail(Request $request, array $params = []): Response
    {
        $owner = $this->owner();
        if ($owner === null) {
            return Response::redirect('/admin');
        }
        if (!self::fresh((int) $owner['id'])) {
            Flash::notice('Please confirm with an emailed code first.');
            return Response::redirect('/admin/settings');
        }
        try {
            Mailer::fromEnv()->send((string) $owner['email'], 'Belis Bell test email', 'This is a test email from the Belis Bell Settings page. If you can read it, sending works.');
            Audit::add(Db::fromEnv(), (int) $owner['id'], 'settings.test_email', 'email', $request->ip);
            Flash::notice('A test email was sent to your address.');
        } catch (\Throwable $e) {
            Logger::error('Test email failed', ['type' => $e::class]);
            Flash::notice('The test email could not be sent. Check the email settings.');
        }
        return Response::redirect('/admin/settings');
    }

    private static function fresh(int $uid): bool
    {
        Session::start();
        $s = $_SESSION['stepup'] ?? null;
        return is_array($s) && (int) ($s['uid'] ?? 0) === $uid && time() - (int) ($s['at'] ?? 0) <= self::FRESH_SECONDS;
    }

    /** @return array<string,string>|null the signed-in owner account, or null for the preview person (no real account) */
    private function owner(): ?array
    {
        $o = Auth::owner();
        if ($o === null || !isset($o['id'])) {
            Flash::notice('Settings need a real owner account. The local preview person cannot open them.');
            return null;
        }
        return $o;
    }

    /**
     * @param array<string,string> $owner
     * @param array<string,mixed> $old values to put back in non-secret fields
     * @param array<string,string> $errors
     */
    private function page(array $owner, array $old, array $errors, int $status): Response
    {
        $fresh = self::fresh((int) $owner['id']);
        $rows = [];
        $audit = [];
        if ($fresh) {
            foreach (Settings::FIELDS as $name => $meta) {
                $rows[] = [
                    'name' => $name, 'label' => $meta['label'], 'group' => $meta['group'], 'help' => $meta['help'], 'secret' => $meta['secret'],
                    'source' => Settings::source($name), 'shown' => Settings::display($name),
                    'value' => $meta['secret'] ? '' : (is_string($old[$name] ?? null) ? $old[$name] : ''),
                    'error' => $errors[$name] ?? null,
                ];
            }
            try {
                $audit = Audit::recent(Db::fromEnv(), 15);
            } catch (\Throwable) {
                $audit = [];
            }
        }
        return Response::html(View::render('pages/admin/settings', [
            'title' => 'Settings | Belis Bell',
            'owner' => $owner,
            'fresh' => $fresh,
            'rows' => $rows,
            'audit' => $audit,
            'codeError' => $errors['code'] ?? '',
            'mock' => is_mock_mode(),
        ]), $status);
    }

    /** @param list<string> $changed */
    private function tellOwner(string $email, array $changed, string $ip): void
    {
        try {
            Mailer::fromEnv()->send($email, 'Belis Bell settings were changed', "These settings were changed just now from address {$ip}:\n\n- " . implode("\n- ", $changed) . "\n\nThe values are not included. If you did not do this, sign in, replace the keys and change your password straight away.");
        } catch (\Throwable $e) {
            Logger::error('Settings change email failed', ['type' => $e::class]);
        }
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Settings request failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
