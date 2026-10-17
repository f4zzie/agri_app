<?php

declare(strict_types=1);

namespace App;

/**
 * Safe image upload handling.
 *
 * The stored file name and extension are derived from the *detected*
 * MIME type, never from the client-supplied file name. This prevents a
 * valid image with a ".php" extension from landing in a web-served
 * directory and being executed.
 */
final class Upload
{
    /** Allowed MIME types and the extension we store them under. */
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Validate an entry from $_FILES.
     *
     * @param array{name?:string,tmp_name?:string,size?:int,error?:int} $file
     * @param string|null $mimeOverride MIME type to use instead of sniffing (for tests)
     * @return array{ok:bool, error:?string, ext:?string}
     */
    public static function validateImage(array $file, int $maxBytes, ?string $mimeOverride = null): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return self::fail($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? 'upload_too_large'
                : 'upload_failed');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return self::fail('upload_failed');
        }
        if ($size > $maxBytes) {
            return self::fail('upload_too_large');
        }

        $mime = $mimeOverride ?? self::detectMime((string) ($file['tmp_name'] ?? ''));
        if ($mime === null || !isset(self::IMAGE_TYPES[$mime])) {
            return self::fail('upload_bad_type');
        }

        return ['ok' => true, 'error' => null, 'ext' => self::IMAGE_TYPES[$mime]];
    }

    /**
     * Validate and move an uploaded image into $dir.
     *
     * @param array{name?:string,tmp_name?:string,size?:int,error?:int} $file
     * @return array{ok:bool, error:?string, filename:?string}
     */
    public static function storeImage(array $file, string $dir, string $prefix, int $maxBytes): array
    {
        $check = self::validateImage($file, $maxBytes);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => $check['error'], 'filename' => null];
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'upload_failed', 'filename' => null];
        }

        $filename = $prefix . bin2hex(random_bytes(12)) . '.' . $check['ext'];
        if (!move_uploaded_file((string) $file['tmp_name'], rtrim($dir, '/') . '/' . $filename)) {
            return ['ok' => false, 'error' => 'upload_failed', 'filename' => null];
        }

        return ['ok' => true, 'error' => null, 'filename' => $filename];
    }

    /** True when the request carried a file for this field. */
    public static function wasSubmitted(?array $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Only plain generated names (prefix + hex + known extension) are ever
     * treated as stored files, so a tampered DB value can't point at ../.
     */
    public static function isSafeStoredName(?string $name): bool
    {
        return $name !== null
            && preg_match('/^[a-z]+_[a-f0-9.]+\.(jpe?g|png|gif|webp)$/', $name) === 1;
    }

    private static function detectMime(string $path): ?string
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);
        return $mime === false ? null : $mime;
    }

    /** @return array{ok:false, error:string, ext:null} */
    private static function fail(string $key): array
    {
        return ['ok' => false, 'error' => $key, 'ext' => null];
    }
}
