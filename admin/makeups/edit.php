<?php
// admin/makeups/edit.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once __DIR__ . '/_helpers.php';
requireRole(['Super Admin', 'Secretary', 'Attendance Officer']);

$pageTitle = 'Edit Make-up Meeting';
$pdo    = getPDO();
$errors = [];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM makeup_meetings WHERE id=?");
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) { header('Location: index.php'); exit; }

$members   = $pdo->query("SELECT id, first_name, last_name, rotary_id FROM members WHERE is_active=1 OR id=" . (int)$record['member_id'] . " ORDER BY last_name, first_name")->fetchAll();
$clubNames = $pdo->query("SELECT club_name FROM clubs WHERE is_host_club=0 ORDER BY club_name")->fetchAll(PDO::FETCH_COLUMN);

$src  = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $record;
$data = [
    'member_id'    => (int)($src['member_id'] ?? 0),
    'meeting_date' => trim($src['meeting_date'] ?? ''),
    'club_visited' => trim($src['club_visited'] ?? ''),
    'district'     => trim($src['district']     ?? ''),
    'meeting_type' => trim($src['meeting_type'] ?? 'Club Meeting'),
    'venue'        => trim($src['venue']        ?? ''),
    'notes'        => trim($src['notes']        ?? ''),
    'status'       => $record['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$data['member_id'])    $errors[] = 'Please select a member.';
    if (!$data['meeting_date']) $errors[] = 'Date of make-up is required.';
    elseif ($data['meeting_date'] > date('Y-m-d')) $errors[] = 'Date of make-up cannot be in the future.';
    if (!$data['club_visited']) $errors[] = 'Club visited is required.';
    if (!in_array($data['meeting_type'], MAKEUP_TYPES, true)) $errors[] = 'Please choose a valid make-up type.';

    $file = empty($errors) ? storeMakeupUpload('attachment', $errors) : null;

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE makeup_meetings
            SET member_id=?, meeting_date=?, club_visited=?, district=?, meeting_type=?, venue=?, notes=?
            WHERE id=?
        ")->execute([
            $data['member_id'], $data['meeting_date'], $data['club_visited'],
            $data['district'] ?: null, $data['meeting_type'], $data['venue'] ?: null, $data['notes'] ?: null,
            $id,
        ]);

        if ($file) {
            $pdo->prepare("UPDATE makeup_meetings SET attachment_path=?, attachment_name=?, attachment_mime=? WHERE id=?")
                ->execute([$file['path'], $file['name'], $file['mime'], $id]);
            deleteMakeupFile($record['attachment_path']);
        }

        header('Location: view.php?id=' . $id . '&saved=1');
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
        <h2>✏️ Edit Make-up Meeting</h2>
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <?php $isEdit = true; require __DIR__ . '/_form.php'; ?>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
