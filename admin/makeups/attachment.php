<?php
// admin/makeups/attachment.php
// Serves a make-up attachment to logged-in admins only.

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once __DIR__ . '/_helpers.php';
requireRole(['Super Admin', 'Secretary', 'President', 'Attendance Officer']);

$pdo  = getPDO();
$stmt = $pdo->prepare("SELECT attachment_path, attachment_name, attachment_mime FROM makeup_meetings WHERE id=?");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$row = $stmt->fetch();

$file = $row && $row['attachment_path'] ? MAKEUP_UPLOAD_DIR . basename($row['attachment_path']) : null;
if (!$file || !is_file($file)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$name = preg_replace('/[^\w.\- ]+/', '_', $row['attachment_name'] ?: basename($file));
header('Content-Type: ' . ($row['attachment_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($file);
exit;
