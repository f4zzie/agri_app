<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the Swahili translation complete: a missing key would show the
 * raw key on screen, and a placeholder mismatch would break sprintf().
 */
final class LangTest extends TestCase
{
    private static function load(string $lang): array
    {
        return require __DIR__ . '/../../lang/' . $lang . '.php';
    }

    public function testSwahiliHasExactlyTheEnglishKeys(): void
    {
        $en = array_keys(self::load('en'));
        $sw = array_keys(self::load('sw'));

        self::assertSame([], array_values(array_diff($en, $sw)), 'Missing in sw.php');
        self::assertSame([], array_values(array_diff($sw, $en)), 'Only in sw.php');
    }

    public function testPlaceholdersMatchBetweenLanguages(): void
    {
        $en = self::load('en');
        $sw = self::load('sw');
        foreach ($en as $key => $text) {
            preg_match_all('/%(?:\\d\\$)?[sd]/', $text, $a);
            preg_match_all('/%(?:\\d\\$)?[sd]/', $sw[$key], $b);
            sort($a[0]);
            sort($b[0]);
            self::assertSame($a[0], $b[0], "Placeholder mismatch for '{$key}'");
        }
    }

    public function testEveryKeyUsedInCodeExists(): void
    {
        $en   = self::load('en');
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob("$root/public/*.php"), glob("$root/includes/*.php"), glob("$root/src/*.php"), ["$root/config.php"]);
        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/\\bt\\('([a-z0-9_]+)'\\)/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                if (!isset($en[$key])) {
                    $missing[] = basename($file) . ': ' . $key;
                }
            }
            preg_match_all("/\\btn\\('([a-z0-9_]+)'/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                foreach (['_one', '_other'] as $form) {
                    if (!isset($en[$key . $form])) {
                        $missing[] = basename($file) . ': ' . $key . $form;
                    }
                }
            }
        }
        self::assertSame([], $missing);
    }

    public function testTranslateUsesSessionLanguageAndFallsBackToKey(): void
    {
        $_SESSION['lang'] = 'sw';
        self::assertSame('Sokoni', t('marketplace'));
        $_SESSION['lang'] = 'en';
        self::assertSame('Marketplace', t('marketplace'));
        self::assertSame('no_such_key', t('no_such_key'));
    }

    public function testLangUrlKeepsOtherQueryParameters(): void
    {
        $_GET = ['id' => '5', 'lang' => 'en'];
        self::assertSame('?id=5&amp;lang=sw', lang_url('sw'));
        $_GET = [];
    }
}
