<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/agritrack-upload-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function file(string $name, string $bytes): array
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $bytes);
        return ['name' => $name, 'tmp_name' => $path, 'size' => strlen($bytes), 'error' => UPLOAD_ERR_OK];
    }

    public function testExtensionComesFromContentNotFileName(): void
    {
        // A valid GIF with PHP appended, uploaded as shell.php: the original RCE vector
        $gif = $this->file('shell.php', "GIF89a\x01\x00\x01\x00\x00\x00\x00;<?php echo 'pwned'; ?>");

        $result = Upload::validateImage($gif, 1024);

        self::assertTrue($result['ok']);
        self::assertSame('gif', $result['ext']);
    }

    public function testRejectsNonImageEvenWithImageExtension(): void
    {
        $php = $this->file('photo.jpg', "<?php system('id');");

        $result = Upload::validateImage($php, 1024);

        self::assertFalse($result['ok']);
        self::assertSame('upload_bad_type', $result['error']);
    }

    public function testRejectsOversizedFiles(): void
    {
        $result = Upload::validateImage(['tmp_name' => '/x', 'size' => 2048, 'error' => UPLOAD_ERR_OK], 1024, 'image/png');

        self::assertSame('upload_too_large', $result['error']);
    }

    public function testMapsPhpUploadErrors(): void
    {
        self::assertSame('upload_too_large', Upload::validateImage(['error' => UPLOAD_ERR_INI_SIZE], 1024)['error']);
        self::assertSame('upload_failed', Upload::validateImage(['error' => UPLOAD_ERR_PARTIAL], 1024)['error']);
    }

    #[DataProvider('storedNames')]
    public function testOnlyGeneratedNamesAreTreatedAsStoredFiles(?string $name, bool $safe): void
    {
        self::assertSame($safe, Upload::isSafeStoredName($name));
    }

    public static function storedNames(): array
    {
        return [
            'new style'        => ['prod_0a1b2c3d4e5f60718293a4b5.jpg', true],
            'legacy uniqid'    => ['avatar_6650a1b2c3d4e5.12345678.png', true],
            'path traversal'   => ['../../config.php', false],
            'php extension'    => ['prod_abc.php', false],
            'null'             => [null, false],
        ];
    }

    public function testWasSubmittedIgnoresEmptyFileInputs(): void
    {
        self::assertFalse(Upload::wasSubmitted(['error' => UPLOAD_ERR_NO_FILE]));
        self::assertFalse(Upload::wasSubmitted(null));
        self::assertTrue(Upload::wasSubmitted(['error' => UPLOAD_ERR_OK]));
    }
}
