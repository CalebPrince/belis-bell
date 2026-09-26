<?php
declare(strict_types=1);

namespace Belis\Core;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self($status, (string) json_encode($data, JSON_UNESCAPED_SLASHES), ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        // Only same-site paths are allowed, which prevents open redirects.
        if (!str_starts_with($location, '/') || str_starts_with($location, '//')) {
            $location = '/';
        }
        return new self($status, '', ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
