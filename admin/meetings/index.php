<?php
// admin/meetings/index.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'All Meetings';
$pdo = getPDO();

// Status filter
$statusFilter =  $_GET['status'] ?? '';
$where  =  $statusFilter ? "WHERE m.status = " .  $pdo->quote($statusFilter) : '';

$meetings =  $pdo->query("
    SELECT m.*, c.club_name,
           COALESCE(ms.total_members_present,0)    AS members_present,
           COALESCE(ms.total_visiting_rotarians,0) AS visitors,
           COALESCE(ms.total_guests,0)             AS guests,
           COALESCE(ms.total_attendees,0)          AS total_attendees
    FROM   meetings m
    JOIN   clubs c ON c.id = m.club_id
    LEFT   JOIN meeting_summary ms ON ms.meeting_id = m.id
     $where
    ORDER  BY m.meeting_date DESC, m.start_time DESC
")->fetchAll();

// Handle quick status toggle (Open/Close/Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
     $meetingId = (int)$_POST['meeting_id'];
     $newStatus =  $_POST['new_status'];
     $allowed   = ['Open', 'Closed', 'Cancelled', 'Scheduled'];
    if (in_array($newStatus,  $allowed)) {
         $pdo->prepare("UPDATE meetings SET status=? WHERE id=?")
            ->execute([$newStatus,  $meetingId]);
    }
    header('Location: index.php'); exit;
}

require_once '../includes/layout_top.php';
?>

<div class="page-actions">
    <a href="create.php" class="btn btn-primary">➕ New Meeting</a>
    <div style="margin-left:auto; display:flex; gap:8px;">
        <?php foreach ([''=>'All','Scheduled'=>'Scheduled','Open'=>'Open','Closed'=>'Closed','Cancelled'=>'Cancelled'] as  $val=>$lbl): ?>
            <a href="?status=<?=  $val ?>"
               class="btn btn-sm <?= ($statusFilter===$val) ? 'btn-primary' : 'btn-outline' ?>">
                <?=  $lbl ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📅 Meetings (<?= count($meetings) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Members</th>
                    <th>Visitors</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($meetings)): ?>
                <tr><td colspan="10" style="text-align:center;color:#999;padding:30px">No meetings found.</td></tr>
            <?php else: ?>
                <?php foreach ($meetings as  $m):
                     $badgeMap = ['Scheduled'=>'badge-gold','Open'=>'badge-green','Closed'=>'badge-gray','Cancelled'=>'badge-red'];
                     $badge    =  $badgeMap[$m['status']] ?? 'badge-gray';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                    <td><?= date('d M Y', strtotime($m['meeting_date'])) ?></td>
                    <td><?= date('h:i A', strtotime($m['start_time'])) ?></td>
                    <td><?= htmlspecialchars($m['venue'] ?? '—') ?></td>
                    <td><span class="badge <?=  $badge ?>"><?=  $m['status'] ?></span></td>
                    <td><?=  $m['members_present'] ?></td>
                    <td><?=  $m['visitors'] ?></td>
                    <td><?=  $m['guests'] ?></td>
                    <td><strong><?=  $m['total_attendees'] ?></strong></td>
                    <td>
                        <div class="actions">
                            <a href="view.php?id=<?=  $m['id'] ?>"
                               class="btn btn-outline btn-sm">👁 View</a>
                            <a href="edit.php?id=<?=  $m['id'] ?>"
                               class="btn btn-outline btn-sm">✏️</a>

                            <!-- Quick status toggle -->
                            <?php if ($m['status'] === 'Scheduled'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="meeting_id" value="<?=  $m['id'] ?>">
                                    <input type="hidden" name="new_status" value="Open">
                                    <button name="toggle_status" class="btn btn-green btn-sm">▶ Open</button>
                                </form>
                            <?php elseif ($m['status'] === 'Open'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="meeting_id" value="<?=  $m['id'] ?>">
                                    <input type="hidden" name="new_status" value="Closed">
                                    <button name="toggle_status" class="btn btn-red btn-sm">⏹ Close</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
