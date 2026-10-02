<?php
// admin/makeups/create.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once __DIR__ . '/_helpers.php';
requireRole(['Super Admin', 'Secretary', 'Attendance Officer']);

$pageTitle = 'Record Make-up Meeting';
$pdo    = getPDO();
$errors = [];

$members   = $pdo->query("SELECT id, first_name, last_name, rotary_id FROM members WHERE is_active=1 ORDER BY last_name, first_name")->fetchAll();
$clubNames = $pdo->query("SELECT club_name FROM clubs WHERE is_host_club=0 ORDER BY club_name")->fetchAll(PDO::FETCH_COLUMN);

$data = [
    'member_id'    => (int)($_POST['member_id'] ?? ($_GET['member_id'] ?? 0)),
    'meeting_date' => trim($_POST['meeting_date'] ?? ''),
    'club_visited' => trim($_POST['club_visited'] ?? ''),
    'district'     => trim($_POST['district']     ?? ''),
    'meeting_type' => trim($_POST['meeting_type'] ?? 'Club Meeting'),
    'venue'        => trim($_POST['venue']        ?? ''),
    'notes'        => trim($_POST['notes']        ?? ''),
    'status'       => $_POST['status'] ?? 'Pending',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$data['member_id'])    $errors[] = 'Please select a member.';
    if (!$data['meeting_date']) $errors[] = 'Date of make-up is required.';
    elseif ($data['meeting_date'] > date('Y-m-d')) $errors[] = 'Date of make-up cannot be in the future.';
    if (!$data['club_visited']) $errors[] = 'Club visited is required.';
    if (!in_array($data['meeting_type'], MAKEUP_TYPES, true)) $errors[] = 'Please choose a valid make-up type.';

    $status = (canApproveMakeups() && $data['status'] === 'Approved') ? 'Approved' : 'Pending';

    $file = null;
    if (empty($errors)) {
        $file = storeMakeupUpload('attachment', $errors);   // optional
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO makeup_meetings
                (member_id, meeting_date, club_visited, district, meeting_type, venue, notes,
                 attachment_path, attachment_name, attachment_mime,
                 status, reviewed_by, reviewed_at, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $data['member_id'], $data['meeting_date'], $data['club_visited'],
            $data['district'] ?: null, $data['meeting_type'], $data['venue'] ?: null, $data['notes'] ?: null,
            $file['path'] ?? null, $file['name'] ?? null, $file['mime'] ?? null,
            $status,
            $status === 'Approved' ? $_SESSION['admin_id'] : null,
            $status === 'Approved' ? date('Y-m-d H:i:s') : null,
            $_SESSION['admin_id'] ?? null,
        ]);
        header('Location: view.php?id=' . (int)$pdo->lastInsertId() . '&saved=1');
        exit;
    }
}

require_once '../includes/layout_top.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>🔁 Record Make-up Meeting</h2>
        <a href="index.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <p class="text-muted" style="font-size:0.88rem; margin-bottom:18px;">
            Record a meeting a member attended at another club, and attach the certificate
            or notice of attendance they received.
        </p>
        <?php $isEdit = false; $record = null; require __DIR__ . '/_form.php'; ?>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
