<?php
declare(strict_types=1);

namespace Belis\Support;

/**
 * A small SMTP client for sending plain text email with a username and password (AUTH LOGIN) over STARTTLS
 * (port 587) or implicit TLS (port 465). Certificates are always verified, every step has a timeout, and line breaks
 * are stripped from addresses and subjects so nothing can inject extra headers. NOT YET RUN against a real mail
 * server: it is tested only against a scripted fake connection.
 */
final class Smtp
{
    /** @var callable(string,int,string):SmtpConnection */
    private $connect;

    /** @param callable(string,int,string):SmtpConnection|null $connect replaceable for tests: (host, port, encryption) */
    public function __construct(private readonly string $host, private readonly int $port, private readonly string $encryption, private readonly string $user, private readonly string $password, private readonly string $fromAddress, private readonly string $fromName, ?callable $connect = null, private readonly string $helo = 'localhost')
    {
        $this->connect = $connect ?? [self::class, 'open'];
    }

    public function send(string $to, string $subject, string $body): void
    {
        $to = self::line($to);
        $from = self::line($this->fromAddress);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Bad email address');
        }
        $c = ($this->connect)($this->host, $this->port, $this->encryption);
        try {
            $this->expect($c, 220);
            $this->command($c, 'EHLO ' . $this->helo, 250);
            if ($this->encryption === 'tls') {
                $this->command($c, 'STARTTLS', 220);
                $c->enableTls();
                $this->command($c, 'EHLO ' . $this->helo, 250);
            }
            $this->command($c, 'AUTH LOGIN', 334);
            $this->command($c, base64_encode($this->user), 334);
            $this->command($c, base64_encode($this->password), 235);
            $this->command($c, 'MAIL FROM:<' . $from . '>', 250);
            $this->command($c, 'RCPT TO:<' . $to . '>', 250);
            $this->command($c, 'DATA', 354);
            $c->write($this->message($to, $subject, $body) . "\r\n.\r\n");
            $this->expect($c, 250);
            $c->write("QUIT\r\n");
        } finally {
            $c->close();
        }
    }

    private function message(string $to, string $subject, string $body): string
    {
        $name = self::line($this->fromName);
        $fromHeader = $name === '' ? '<' . self::line($this->fromAddress) . '>' : '=?UTF-8?B?' . base64_encode($name) . '?= <' . self::line($this->fromAddress) . '>';
        $headers = [
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode(self::line($subject)) . '?=',
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $this->fromAddress)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $text = preg_replace('/^\./m', '..', $text) ?? $text; // dot-stuffing
        return implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $text);
    }

    private function command(SmtpConnection $c, string $line, int $expect): void
    {
        $c->write($line . "\r\n");
        $this->expect($c, $expect);
    }

    private function expect(SmtpConnection $c, int $code): void
    {
        do {
            $line = $c->readLine();
            if (strlen($line) < 3) {
                throw new \RuntimeException('Mail server closed the connection');
            }
            $more = ($line[3] ?? ' ') === '-';
        } while ($more);
        if ((int) substr($line, 0, 3) !== $code) {
            throw new \RuntimeException('Mail server answered ' . substr($line, 0, 3));
        }
    }

    private static function line(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $s));
    }

    public static function open(string $host, int $port, string $encryption): SmtpConnection
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($target, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if ($fp === false) {
            throw new \RuntimeException('Could not connect to the mail server');
        }
        stream_set_timeout($fp, 10);
        return new StreamSmtpConnection($fp);
    }
}
