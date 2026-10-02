<?php
// admin/makeups/_helpers.php
// Shared settings and helpers for the Makeup Meetings module.

require_once __DIR__ . '/../includes/role_guard.php';

// Private folder for certificates / notices of attendance
define('MAKEUP_UPLOAD_DIR', realpath(__DIR__ . '/../../') . '/uploads/makeups/');
define('MAKEUP_MAX_BYTES', 5 * 1024 * 1024); // 5 MB

const MAKEUP_TYPES = [
    'Club Meeting',
    'Rotaract / Interact Meeting',
    'District Event / Conference',
    'Service Project',
    'Board / Committee Meeting',
    'Online / E-Club Meeting',
    'Other',
];

const MAKEUP_STATUSES = ['Pending', 'Approved', 'Rejected'];

const MAKEUP_ALLOWED_MIME = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
];

// Who can do what
function canRecordMakeups(): bool
{
    return in_array($_SESSION['admin_role'] ?? '', ['Super Admin', 'Secretary', 'Attendance Officer'], true);
}

function canApproveMakeups(): bool
{
    return in_array($_SESSION['admin_role'] ?? '', ['Super Admin', 'Secretary'], true);
}

function makeupBadge(string $status): string
{
    $map = ['Pending' => 'badge-gold', 'Approved' => 'badge-green', 'Rejected' => 'badge-red'];
    return '<span class="badge ' . ($map[$status] ?? 'badge-gray') . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Validate and store an uploaded file.
 * Returns ['path'=>..., 'name'=>..., 'mime'=>...] or null if no file was sent.
 * Adds messages to $errors on failure.
 */
function storeMakeupUpload(string $field, array &$errors): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'The attachment is too large (max 5 MB).'
            : 'The attachment could not be uploaded. Please try again.';
        return null;
    }
    if ($f['size'] > MAKEUP_MAX_BYTES) {
        $errors[] = 'The attachment is too large (max 5 MB).';
        return null;
    }

    $mime = class_exists('finfo')
        ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])
        : mime_content_type($f['tmp_name']);

    if (!isset(MAKEUP_ALLOWED_MIME[$mime])) {
        $errors[] = 'Attachment must be a PDF, JPG, PNG or WEBP file.';
        return null;
    }

    if (!is_dir(MAKEUP_UPLOAD_DIR) && !mkdir(MAKEUP_UPLOAD_DIR, 0755, true)) {
        $errors[] = 'Upload folder is missing and could not be created (uploads/makeups).';
        return null;
    }

    $stored = 'makeup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . MAKEUP_ALLOWED_MIME[$mime];
    if (!move_uploaded_file($f['tmp_name'], MAKEUP_UPLOAD_DIR . $stored)) {
        $errors[] = 'Could not save the attachment. Check that uploads/makeups is writable.';
        return null;
    }

    return [
        'path' => $stored,
        'name' => mb_substr(basename($f['name']), 0, 255),
        'mime' => $mime,
    ];
}

function deleteMakeupFile(?string $stored): void
{
    if ($stored && is_file(MAKEUP_UPLOAD_DIR . basename($stored))) {
        @unlink(MAKEUP_UPLOAD_DIR . basename($stored));
    }
}
