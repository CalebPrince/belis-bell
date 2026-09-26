<?php
declare(strict_types=1);

// Shared bootstrap for command-line scripts. Refuses to run on the web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';
