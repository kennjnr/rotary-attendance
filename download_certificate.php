<?php
// download_certificate.php
// Serves the PDF securely without exposing the file path

require_once __DIR__ . '/config/db.php';

$certNo = trim($_GET['no']   ?? '');
$view   = !empty($_GET['view']); // true = inline view, false = download

if (!$certNo) {
    http_response_code(400);
    exit('Invalid request.');
}

$pdo  = getPDO();
$stmt =  $pdo->prepare("
    SELECT file_path, certificate_no, recipient_name
    FROM   certificates
    WHERE  certificate_no = ?
");
$stmt->execute([$certNo]);
$cert =  $stmt->fetch();

if (!$cert) {
    http_response_code(404);
    exit('Certificate not found.');
}

if (empty($cert['file_path']) || !file_exists($cert['file_path'])) {
    http_response_code(404);
    exit('Certificate file not available.');
}

// Serve the PDF
$filename    =  $cert['certificate_no'] . '.pdf';
$disposition =  $view ? 'inline' : 'attachment';

header('Content-Type: application/pdf');
header('Content-Disposition: ' .  $disposition
     . '; filename="' .  $filename . '"');
header('Content-Length: ' . filesize($cert['file_path']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($cert['file_path']);
exit;
