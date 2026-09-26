<?php
declare(strict_types=1);

namespace Belis\Support;

/** The real socket behind Smtp::open(). */
final class StreamSmtpConnection implements SmtpConnection
{
    /** @param resource $fp */
    public function __construct(private $fp)
    {
    }

    public function readLine(): string
    {
        $line = fgets($this->fp, 1024);
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    public function write(string $data): void
    {
        if (@fwrite($this->fp, $data) === false) {
            throw new \RuntimeException('Could not write to the mail server');
        }
    }

    public function enableTls(): void
    {
        if (@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new \RuntimeException('Could not start TLS with the mail server');
        }
    }

    public function close(): void
    {
        @fclose($this->fp);
    }
}
