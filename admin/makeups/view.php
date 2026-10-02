<?php
// admin/makeups/view.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once __DIR__ . '/_helpers.php';
requireRole(['Super Admin', 'Secretary', 'President', 'Attendance Officer']);

$pageTitle = 'Make-up Meeting';
$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

// ── Actions: approve / reject / reset / delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && canApproveMakeups()) {
    $action = $_POST['action'] ?? '';
    $note   = mb_substr(trim($_POST['review_note'] ?? ''), 0, 255);

    if (in_array($action, ['Approved', 'Rejected', 'Pending'], true)) {
        $pdo->prepare("UPDATE makeup_meetings SET status=?, review_note=?, reviewed_by=?, reviewed_at=? WHERE id=?")
            ->execute([
                $action,
                $action === 'Pending' ? null : ($note ?: null),
                $action === 'Pending' ? null : $_SESSION['admin_id'],
                $action === 'Pending' ? null : date('Y-m-d H:i:s'),
                $id,
            ]);
        header('Location: view.php?id=' . $id . '&done=' . urlencode($action));
        exit;
    }

    if ($action === 'delete') {
        $s = $pdo->prepare("SELECT attachment_path FROM makeup_meetings WHERE id=?");
        $s->execute([$id]);
        deleteMakeupFile($s->fetchColumn() ?: null);
        $pdo->prepare("DELETE FROM makeup_meetings WHERE id=?")->execute([$id]);
        header('Location: index.php');
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT mm.*, m.first_name, m.last_name, m.email, m.rotary_id,
           rv.username AS reviewer, cr.username AS creator
    FROM   makeup_meetings mm
    JOIN   members m ON m.id = mm.member_id
    LEFT   JOIN admin_users rv ON rv.id = mm.reviewed_by
    LEFT   JOIN admin_users cr ON cr.id = mm.created_by
    WHERE  mm.id = ?
");
$stmt->execute([$id]);
$mk = $stmt->fetch();
if (!$mk) { header('Location: index.php'); exit; }

$isImage = strpos((string)$mk['attachment_mime'], 'image/') === 0;
$isPdf   = $mk['attachment_mime'] === 'application/pdf';

require_once '../includes/layout_top.php';
?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">✅ Make-up meeting saved.</div>
<?php elseif (!empty($_GET['done'])): ?>
    <div class="alert alert-success">✅ Status updated to <strong><?= htmlspecialchars($_GET['done']) ?></strong>.</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">

    <!-- Details -->
    <div class="card">
        <div class="card-header">
            <h2>🔁 Make-up Details</h2>
            <a href="index.php" class="btn btn-outline btn-sm">← Back</a>
        </div>
        <div class="card-body">
            <table style="width:100%; font-size:0.92rem; line-height:2;">
                <tr><td style="color:#888; width:38%">Member</td>
                    <td><strong><?= htmlspecialchars($mk['first_name'] . ' ' . $mk['last_name']) ?></strong>
                        <?= $mk['rotary_id'] ? '<small style="color:#888"> · ' . htmlspecialchars($mk['rotary_id']) . '</small>' : '' ?></td></tr>
                <tr><td style="color:#888">Date</td>
                    <td><?= date('l, d F Y', strtotime($mk['meeting_date'])) ?></td></tr>
                <tr><td style="color:#888">Club Visited</td>
                    <td><?= htmlspecialchars($mk['club_visited']) ?></td></tr>
                <tr><td style="color:#888">District</td>
                    <td><?= htmlspecialchars($mk['district'] ?: '—') ?></td></tr>
                <tr><td style="color:#888">Type</td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($mk['meeting_type']) ?></span></td></tr>
                <tr><td style="color:#888">Venue</td>
                    <td><?= htmlspecialchars($mk['venue'] ?: '—') ?></td></tr>
                <tr><td style="color:#888">Status</td>
                    <td><?= makeupBadge($mk['status']) ?></td></tr>
                <?php if ($mk['reviewed_at']): ?>
                <tr><td style="color:#888">Reviewed</td>
                    <td><?= htmlspecialchars($mk['reviewer'] ?? '—') ?>,
                        <?= date('d M Y, h:i A', strtotime($mk['reviewed_at'])) ?></td></tr>
                <?php endif; ?>
                <?php if ($mk['review_note']): ?>
                <tr><td style="color:#888">Review Note</td>
                    <td><?= nl2br(htmlspecialchars($mk['review_note'])) ?></td></tr>
                <?php endif; ?>
                <tr><td style="color:#888">Recorded</td>
                    <td><?= $mk['created_by'] ? htmlspecialchars($mk['creator'] ?? '—') : '🌐 Member, via online form' ?>,
                        <?= date('d M Y', strtotime($mk['created_at'])) ?></td></tr>
            </table>

            <?php if ($mk['notes']): ?>
                <div style="margin-top:16px; padding:12px 14px; background:#f0f4f8; border-radius:8px; font-size:0.88rem;">
                    <strong>Notes</strong><br><?= nl2br(htmlspecialchars($mk['notes'])) ?>
                </div>
            <?php endif; ?>

            <?php if (canRecordMakeups()): ?>
            <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="edit.php?id=<?= $mk['id'] ?>" class="btn btn-outline">✏️ Edit</a>
                <?php if (canApproveMakeups()): ?>
                <form method="POST" onsubmit="return confirm('Delete this make-up record and its attachment?')">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-outline" style="color:var(--red)">🗑 Delete</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <!-- Review -->
        <?php if (canApproveMakeups()): ?>
        <div class="card mb-4">
            <div class="card-header"><h2>✅ Review</h2></div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Review Note (optional)</label>
                        <input type="text" name="review_note" maxlength="255"
                               value="<?= htmlspecialchars($mk['review_note'] ?? '') ?>"
                               placeholder="e.g. Signed notice from club secretary">
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if ($mk['status'] !== 'Approved'): ?>
                            <button name="action" value="Approved" class="btn btn-green">✓ Approve</button>
                        <?php endif; ?>
                        <?php if ($mk['status'] !== 'Rejected'): ?>
                            <button name="action" value="Rejected" class="btn btn-red">✕ Reject</button>
                        <?php endif; ?>
                        <?php if ($mk['status'] !== 'Pending'): ?>
                            <button name="action" value="Pending" class="btn btn-outline">↩ Back to Pending</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attachment -->
        <div class="card">
            <div class="card-header">
                <h2>📎 Certificate / Notice</h2>
                <?php if ($mk['attachment_path']): ?>
                    <div class="actions">
                        <a href="attachment.php?id=<?= $mk['id'] ?>" target="_blank" class="btn btn-outline btn-sm">↗ Open</a>
                        <a href="attachment.php?id=<?= $mk['id'] ?>&download=1" class="btn btn-gold btn-sm">⬇️ Download</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body" style="text-align:center;">
                <?php if (!$mk['attachment_path']): ?>
                    <p class="text-muted">No attachment uploaded.</p>
                <?php elseif ($isImage): ?>
                    <img src="attachment.php?id=<?= $mk['id'] ?>" alt="Attendance proof"
                         style="max-width:100%; border:1px solid var(--border); border-radius:8px;">
                <?php elseif ($isPdf): ?>
                    <iframe src="attachment.php?id=<?= $mk['id'] ?>"
                            style="width:100%; height:520px; border:1px solid var(--border); border-radius:8px;"></iframe>
                <?php endif; ?>
                <?php if ($mk['attachment_name']): ?>
                    <p class="text-muted" style="font-size:0.8rem; margin-top:8px;"><?= htmlspecialchars($mk['attachment_name']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once '../includes/layout_bottom.php'; ?>
