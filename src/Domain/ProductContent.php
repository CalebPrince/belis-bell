<?php
declare(strict_types=1);

namespace Belis\Domain;

/**
 * Turns the plain-text product fields into lists for the product page. Everything here is plain text
 * that templates escape on output. Icon names and use slugs are checked against fixed rules, so
 * database text can never become markup or a file path.
 */
final class ProductContent
{
    public const HIGHLIGHT_ICONS = ['shield-check', 'sparkles', 'house', 'award', 'tag', 'truck'];
    public const USE_LABELS = ['laundry' => 'Laundry', 'bathrooms' => 'Bathrooms', 'kitchens' => 'Kitchens', 'floors' => 'Floors', 'bins' => 'Bins'];

    /** @return list<array{label:string,value:string}> lines written as "Label|Value" */
    public static function specs(?string $text): array
    {
        $out = [];
        foreach (self::lines($text) as $line) {
            $parts = explode('|', $line, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                $out[] = ['label' => trim($parts[0]), 'value' => trim($parts[1])];
            }
        }
        return $out;
    }

    /** @return list<string> */
    public static function features(?string $text): array
    {
        return self::lines($text);
    }

    /** @return list<array{icon:string,title:string,line:string}> lines written as "icon|Title|Line" */
    public static function highlights(?string $text): array
    {
        $out = [];
        foreach (self::lines($text) as $line) {
            $parts = explode('|', $line, 3);
            if (count($parts) === 3 && trim($parts[1]) !== '') {
                $icon = trim($parts[0]);
                $out[] = [
                    'icon' => in_array($icon, self::HIGHLIGHT_ICONS, true) ? $icon : 'award',
                    'title' => trim($parts[1]),
                    'line' => trim($parts[2]),
                ];
            }
        }
        return array_slice($out, 0, 4);
    }

    /** @return list<array{slug:string,label:string}> comma-separated use names, only known ones */
    public static function uses(?string $text): array
    {
        $out = [];
        foreach (explode(',', (string) $text) as $slug) {
            $slug = strtolower(trim($slug));
            if (isset(self::USE_LABELS[$slug]) && !in_array($slug, array_column($out, 'slug'), true)) {
                $out[] = ['slug' => $slug, 'label' => self::USE_LABELS[$slug]];
            }
        }
        return array_slice($out, 0, 5);
    }

    /** First sentence, for a short intro when no summary is stored. */
    public static function firstSentence(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return preg_match('/^.{10,220}?[.!?](?=\s|$)/su', $text, $m) === 1 ? $m[0] : mb_substr($text, 0, 200);
    }

    /** @return list<string> */
    private static function lines(?string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return array_slice($out, 0, 30);
    }
}
