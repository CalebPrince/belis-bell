<?php
declare(strict_types=1);

// Front controller. Everything except this file and public assets lives above the web root.
require dirname(__DIR__) . '/src/bootstrap.php';

(new Belis\Core\App())->run();
