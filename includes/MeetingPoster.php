<?php
// includes/MeetingPoster.php
// Optional poster image for a meeting / fellowship.
// Posters are public (shown on the check-in page), stored in uploads/posters/.

class MeetingPoster
{
    const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    const ALLOWED   = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public static function dir(): string
    {
        return dirname(__DIR__) . '/uploads/posters/';
    }

    public static function url(?string $file): ?string
    {
        return $file ? APP_URL . '/uploads/posters/' . rawurlencode(basename($file)) : null;
    }

    /**
     * Save an uploaded poster. Returns the stored file name, or null when no
     * file was chosen. Problems are added to $errors.
     */
    public static function store(string $field, array &$errors): ?string
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            || $f['size'] > self::MAX_BYTES) {
            $errors[] = 'The poster is too large (max 5 MB).';
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The poster could not be uploaded. Please try again.';
            return null;
        }

        $mime = class_exists('finfo')
            ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])
            : mime_content_type($f['tmp_name']);
        if (!isset(self::ALLOWED[$mime]) || @getimagesize($f['tmp_name']) === false) {
            $errors[] = 'The poster must be an image (JPG, PNG or WEBP).';
            return null;
        }

        if (!is_dir(self::dir()) && !mkdir(self::dir(), 0755, true)) {
            $errors[] = 'Poster folder is missing and could not be created (uploads/posters).';
            return null;
        }

        $name = 'poster_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($f['tmp_name'], self::dir() . $name)) {
            $errors[] = 'Could not save the poster. Check that uploads/posters is writable.';
            return null;
        }
        return $name;
    }

    public static function delete(?string $file): void
    {
        if ($file && is_file(self::dir() . basename($file))) {
            @unlink(self::dir() . basename($file));
        }
    }
}
