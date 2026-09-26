<?php
declare(strict_types=1);

namespace Belis\Support;

/** Sends through an SMTP server. Failures throw; the message and password are never logged. */
final class SmtpMailer extends Mailer
{
    public function __construct(private readonly Smtp $smtp)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $this->smtp->send($to, $subject, $body);
    }
}
