<?php
declare(strict_types=1);

namespace Belis\Support;

/** One open connection to a mail server. Replaced by a scripted fake in tests. */
interface SmtpConnection
{
    /** One line of the server's answer, without the line ending. Empty when the server hung up. */
    public function readLine(): string;

    public function write(string $data): void;

    /** Switch the open connection to TLS (STARTTLS), verifying the certificate. */
    public function enableTls(): void;

    public function close(): void;
}
