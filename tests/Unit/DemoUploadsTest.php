<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Demo;
use PHPUnit\Framework\TestCase;

final class DemoUploadsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/agri_uploads_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/avatars', 0777, true);
        mkdir($this->dir . '/disease_checks', 0777, true);
        mkdir($this->dir . '/other/deeper', 0777, true);
        file_put_contents($this->dir . '/.htaccess', 'php_flag engine off');
        file_put_contents($this->dir . '/crop_1.jpg', 'x');
        file_put_contents($this->dir . '/avatars/avatar_1.png', 'x');
        file_put_contents($this->dir . '/avatars/.hidden', 'x');
        file_put_contents($this->dir . '/disease_checks/check_1.webp', 'x');
        file_put_contents($this->dir . '/other/deeper/file.txt', 'x');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testEmptiesUploadsButKeepsHtaccessAndFolders(): void
    {
        self::assertSame(5, Demo::clearUploads($this->dir));

        $left = scandir($this->dir);
        sort($left);
        self::assertSame(['.', '..', '.htaccess', 'avatars', 'disease_checks'], $left);
        self::assertSame('php_flag engine off', file_get_contents($this->dir . '/.htaccess'));
        self::assertSame(['.', '..'], scandir($this->dir . '/avatars'));
        self::assertSame(['.', '..'], scandir($this->dir . '/disease_checks'));
    }

    public function testRecreatesMissingFolders(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir . '/disease_checks'));
        Demo::clearUploads($this->dir);
        self::assertDirectoryExists($this->dir . '/disease_checks');
    }
}
