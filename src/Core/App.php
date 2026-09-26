<?php
declare(strict_types=1);

namespace Belis\Core;

use Belis\Support\Logger;

final class App
{
    public static function router(): Router
    {
        $router = new Router();
        (require dirname(__DIR__, 2) . '/config/routes.php')($router);
        return $router;
    }

    public function run(): void
    {
        $https = str_starts_with(Env::get('APP_URL', '') ?? '', 'https://');
        $response = $this->handle(Request::fromGlobals());
        SecurityHeaders::apply($response, $https)->send();
    }

    public function handle(Request $request): Response
    {
        $violations = Guard::violations();
        if ($violations !== []) {
            Logger::error('Refusing to start: environment guard failed', ['reasons' => $violations]);
            return self::unavailable();
        }
        if ((Env::get('APP_ENV', 'production') ?? 'production') === 'production') {
            try {
                if (Guard::mockRowsPresent(Db::fromEnv())) {
                    Logger::error('Refusing to start: mock data found in the production database');
                    return self::unavailable();
                }
            } catch (\Throwable $e) {
                Logger::error('Refusing to start: production database check failed', ['type' => $e::class]);
                return self::unavailable();
            }
        }
        try {
            return self::router()->dispatch($request);
        } catch (\Throwable $e) {
            Logger::error('Unhandled error', ['type' => $e::class, 'message' => $e->getMessage()]);
            return self::unavailable();
        }
    }

    private static function unavailable(): Response
    {
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
