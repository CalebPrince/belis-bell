<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Template rendering with escaping by default (THR-007, CTL-INP-001).
 * Templates print data only through e(), csrf_field() or asset(). The one
 * exception is raw($content) in templates/layout.php for already rendered pages.
 * tests/TemplateEscapingTest.php fails the build on any other <?= output.
 */
final class View
{
    /** @param array<string,mixed> $vars */
    public static function render(string $template, array $vars = []): string
    {
        $body = self::include($template, $vars);
        if (str_starts_with($template, 'pages/')) {
            return self::include('layout', $vars + ['content' => $body]);
        }
        return $body;
    }

    /** @param array<string,mixed> $vars */
    private static function include(string $template, array $vars): string
    {
        if (preg_match('#^[a-z0-9/_-]+$#', $template) !== 1) {
            throw new \InvalidArgumentException('Invalid template name');
        }
        $file = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        $render = static function () use ($file, $vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            include $file;
            return (string) ob_get_clean();
        };
        return $render();
    }
}
