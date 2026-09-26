<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Single router. A route cannot be registered without a policy, and every unsafe
 * method (POST, PUT, PATCH, DELETE) is CSRF-checked unless the route names a
 * reason for the exemption (for example a signed webhook). See RouteInventoryTest.
 */
final class Router
{
    /** @var list<array{method:string,path:string,regex:string,handler:callable|array,policy:string,csrf:bool,csrfExemptReason:?string}> */
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, string $policy, ?string $csrfExemptReason = null): void
    {
        $method = strtoupper($method);
        if ($policy === '') {
            throw new \LogicException("Route {$method} {$path} must declare a policy");
        }
        $unsafe = !in_array($method, ['GET', 'HEAD'], true);
        if (!$unsafe && $csrfExemptReason !== null) {
            throw new \LogicException("Route {$method} {$path}: a safe method needs no CSRF exemption");
        }
        if ($unsafe && $csrfExemptReason !== null && trim($csrfExemptReason) === '') {
            throw new \LogicException("Route {$method} {$path}: CSRF exemption needs a reason");
        }
        $regex = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9._~-]+)', $path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'policy' => $policy,
            'csrf' => $unsafe && $csrfExemptReason === null,
            'csrfExemptReason' => $csrfExemptReason,
        ];
    }

    /** @return list<array{method:string,path:string,policy:string,csrf:bool,csrfExemptReason:?string}> */
    public function inventory(): array
    {
        return array_map(static fn (array $r): array => [
            'method' => $r['method'],
            'path' => $r['path'],
            'policy' => $r['policy'],
            'csrf' => $r['csrf'],
            'csrfExemptReason' => $r['csrfExemptReason'],
        ], $this->routes);
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $m) !== 1) {
                continue;
            }
            $pathMatched = true;
            $method = $request->method === 'HEAD' ? 'GET' : $request->method;
            if ($route['method'] !== $method) {
                continue;
            }
            $status = Policy::evaluate($route['policy'], $request);
            if ($status !== 200) {
                return self::deny($status, $request->path, $route['policy']);
            }
            if ($route['csrf'] && !Csrf::verify($request)) {
                return new Response(419, 'Your session expired. Go back, reload the page and try again.', ['Content-Type' => 'text/plain; charset=utf-8']);
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            return $handler($request, $params);
        }
        return $pathMatched
            ? new Response(405, 'Method not allowed', ['Content-Type' => 'text/plain; charset=utf-8', 'Allow' => 'GET'])
            : Response::html(View::render('pages/404', ['title' => 'Page not found']), 404);
    }

    private static function deny(int $status, string $path, string $policy): Response
    {
        if ($status === 401 && str_starts_with($policy, 'webhook:')) {
            return new Response(401, 'Unauthorized', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return $status === 401
            ? Response::redirect(str_starts_with($path, '/admin') ? '/admin/sign-in' : '/account/sign-in', 302)
            : new Response(403, 'Forbidden', ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
