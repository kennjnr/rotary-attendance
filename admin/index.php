<?php
// admin/index.php

require_once 'includes/auth.php';
require_once '../config/db.php';

$pageTitle = 'Dashboard';
$pdo = getPDO();

// ── Stats ────────────────────────────────────────────────────────
$totalMembers   = $pdo->query("SELECT COUNT(*) FROM members WHERE is_active=1")->fetchColumn();
$totalMeetings  = $pdo->query("SELECT COUNT(*) FROM meetings")->fetchColumn();
$openMeetings   = $pdo->query("SELECT COUNT(*) FROM meetings WHERE status='Open'")->fetchColumn();
$totalCerts     = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
$certsSent      = $pdo->query("SELECT COUNT(*) FROM certificates WHERE email_sent=1")->fetchColumn();
$totalAttendees = $pdo->query("SELECT COALESCE(SUM(total_attendees),0) FROM meeting_summary")->fetchColumn();

// ── Recent meetings ───────────────────────────────────────────────
$recentMeetings = $pdo->query("
    SELECT m.*, c.club_name,
           COALESCE(ms.total_attendees,0) AS attendees,
           COALESCE(ms.total_certificates_sent,0) AS certs_sent
    FROM   meetings m
    JOIN   clubs c ON c.id = m.club_id
    LEFT   JOIN meeting_summary ms ON ms.meeting_id = m.id
    ORDER  BY m.meeting_date DESC
    LIMIT  8
")->fetchAll();

// ── Today's check-ins ─────────────────────────────────────────────
$todayCheckins = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT id FROM member_attendance  WHERE DATE(check_in_time) = CURDATE()
        UNION ALL
        SELECT id FROM visiting_rotarians WHERE DATE(check_in_time) = CURDATE()
        UNION ALL
        SELECT id FROM guests             WHERE DATE(check_in_time) = CURDATE()
    ) t
")->fetchColumn();

require_once 'includes/layout_top.php';
?>



<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="val"><?= number_format($totalMembers) ?></div>
        <div class="lbl">Active Members</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($totalMeetings) ?></div>
        <div class="lbl">Total Meetings</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= number_format($openMeetings) ?></div>
        <div class="lbl">Open / Live Meetings</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= number_format($totalAttendees) ?></div>
        <div class="lbl">Total Attendees (All Time)</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= number_format($certsSent) ?></div>
        <div class="lbl">Certificates Sent</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($todayCheckins) ?></div>
        <div class="lbl">Today's Check-Ins</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="page-actions mb-4">
    <a href="meetings/create.php" class="btn btn-primary">➕ New Meeting</a>
    <a href="members/create.php"  class="btn btn-gold">👤 Add Member</a>
    <a href="reports/index.php"   class="btn btn-outline">📋 View Reports</a>
</div>

<!-- Recent Meetings Table -->
<div class="card">
    <div class="card-header">
        <h2>📅 Recent Meetings</h2>
        <a href="meetings/index.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Meeting</th>
                    <th>Date</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Attendees</th>
                    <th>Certs Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentMeetings)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:30px">No meetings yet.</td></tr>
            <?php else: ?>
                <?php foreach ($recentMeetings as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                    <td><?= date('d M Y', strtotime($m['meeting_date'])) ?></td>
                    <td><?= htmlspecialchars($m['venue'] ?? '—') ?></td>
                    <td>
                        <?php
                        $badgeMap = [
                            'Scheduled' => 'badge-gold',
                            'Open'      => 'badge-green',
                            'Closed'    => 'badge-gray',
                            'Cancelled' => 'badge-red',
                        ];
                        $badge = $badgeMap[$m['status']] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $badge ?>"><?= $m['status'] ?></span>
                    </td>
                    <td><?= $m['attendees'] ?></td>
                    <td><?= $m['certs_sent'] ?></td>
                    <td class="actions">
                        <a href="meetings/view.php?id=<?= $m['id'] ?>"
                           class="btn btn-outline btn-sm">👁 View</a>
                        <a href="meetings/edit.php?id=<?= $m['id'] ?>"
                           class="btn btn-outline btn-sm">✏️ Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/layout_bottom.php'; ?>
