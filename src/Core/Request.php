<?php
declare(strict_types=1);

namespace Belis\Core;

final class Request
{
    /**
     * @param array<string,string> $headers lower-case names
     * @param array<string,mixed>  $post
     * @param array<string,mixed>  $query
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $headers = [],
        private ?string $rawBody = null,
        public readonly string $ip = '0.0.0.0',
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $_POST,
            $headers,
            null,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Raw body, needed to verify webhook signatures byte for byte. */
    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string) file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    public function isUnsafe(): bool
    {
        return !in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
