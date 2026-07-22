<?php
// admin/reports/member_attendance.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'Member Attendance Report';
$pdo       = getPDO();

// Filters
$fromDate =  $_GET['from_date'] ?? date('Y-01-01');
$toDate   =  $_GET['to_date']   ?? date('Y-12-31');
$roleFilter =  $_GET['role']    ?? '';

// Total meetings in period
$totalMeetings =  $pdo->prepare("
    SELECT COUNT(*) FROM meetings
    WHERE meeting_date BETWEEN ? AND ?
    AND   status IN ('Open','Closed')
");
$totalMeetings->execute([$fromDate,  $toDate]);
$totalMeetings = (int)$totalMeetings->fetchColumn();

// Member attendance stats
$where  =  $roleFilter ? "AND m.role = " .  $pdo->quote($roleFilter) : '';
$stmt   =  $pdo->prepare("
    SELECT m.id, m.first_name, m.last_name, m.email,
           m.role, m.rotary_id,
           COUNT(ma.id)                        AS meetings_attended,
           SUM(ma.is_late)                     AS times_late,
           COUNT(cert.id)                      AS certs_received,
           MAX(ma.check_in_time)               AS last_attended,
           ROUND(COUNT(ma.id) / GREATEST(?,1)
                 * 100, 1)                     AS attendance_rate
    FROM   members m
    LEFT   JOIN member_attendance ma
           ON  ma.member_id = m.id
           AND ma.check_in_time BETWEEN ? AND ?
    LEFT   JOIN certificates cert ON cert.id = ma.certificate_id
    WHERE  m.is_active = 1  $where
    GROUP  BY m.id
    ORDER  BY attendance_rate DESC, m.last_name
");
$stmt->execute([$totalMeetings,  $fromDate . ' 00:00:00',  $toDate . ' 23:59:59']);
$members =  $stmt->fetchAll();

// Roles for filter dropdown
$roles =  $pdo->query("
    SELECT DISTINCT role FROM members WHERE is_active=1 ORDER BY role
")->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/layout_top.php';
?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 22px;">
        <form method="GET"
              style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label>From Date</label>
                <input type="date" name="from_date"
                       value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>To Date</label>
                <input type="date" name="to_date"
                       value="<?= htmlspecialchars($toDate) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Role</label>
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as  $r): ?>
                        <option value="<?=  $r ?>"
                            <?= ($roleFilter ===  $r) ? 'selected' : '' ?>>
                            <?=  $r ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="member_attendance.php" class="btn btn-outline">✕ Reset</a>
            <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="export_csv.php?report=member_attendance
                         &from_date=<?=  $fromDate ?>&to_date=<?=  $toDate ?>
                         &role=<?= urlencode($roleFilter) ?>"
                   class="btn btn-green">⬇️ CSV</a>
                <a href="export_pdf.php?report=member_attendance
                         &from_date=<?=  $fromDate ?>&to_date=<?=  $toDate ?>
                         &role=<?= urlencode($roleFilter) ?>"
                   class="btn btn-primary">📄 PDF</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:24px;">
    <div class="stat-card">
        <div class="val"><?=  $totalMeetings ?></div>
        <div class="lbl">Meetings in Period</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= count($members) ?></div>
        <div class="lbl">Active Members</div>
    </div>
    <div class="stat-card green">
        <div class="val">
            <?=  $totalMeetings > 0
                ? round(array_sum(array_column($members,'meetings_attended'))
                        / count($members), 1)
                : 0 ?>
        </div>
        <div class="lbl">Avg Meetings Attended</div>
    </div>
    <div class="stat-card">
        <div class="val">
            <?=  $totalMeetings > 0
                ? round(array_sum(array_column($members,'attendance_rate'))
                        / max(count($members),1), 1) . '%'
                : '0%' ?>
        </div>
        <div class="lbl">Avg Attendance Rate</div>
    </div>
</div>

<!-- Member Table -->
<div class="card">
    <div class="card-header">
        <h2>👥 Member Attendance (<?= count($members) ?>)</h2>
        <span style="font-size:0.82rem; color:#888;">
            Period: <?= date('d M Y', strtotime($fromDate)) ?>
            — <?= date('d M Y', strtotime($toDate)) ?>
            | Total Meetings: <?=  $totalMeetings ?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Role</th>
                    <th>Rotary ID</th>
                    <th>Attended</th>
                    <th>Missed</th>
                    <th>Times Late</th>
                    <th>Attendance Rate</th>
                    <th>Last Attended</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as  $i =>  $m):
                 $missed =  $totalMeetings -  $m['meetings_attended'];
                 $rate   = (float)$m['attendance_rate'];
                 $rateColor =  $rate >= 80
                    ? '#009a44' : ($rate >= 50 ? '#f7a800' : '#c0392b');
            ?>
            <tr>
                <td style="color:#999"><?=  $i + 1 ?></td>
                <td>
                    <strong>
                        <?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?>
                    </strong><br>
                    <small style="color:#888"><?= htmlspecialchars($m['email']) ?></small>
                </td>
                <td>
                    <span class="badge badge-blue"><?= htmlspecialchars($m['role']) ?></span>
                </td>
                <td><?= htmlspecialchars($m['rotary_id'] ?? '—') ?></td>
                <td>
                    <strong style="color:#003f87"><?=  $m['meetings_attended'] ?></strong>
                    / <?=  $totalMeetings ?>
                </td>
                <td>
                    <?php if ($missed > 0): ?>
                        <span class="badge badge-red"><?=  $missed ?></span>
                    <?php else: ?>
                        <span class="badge badge-green">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['times_late'] > 0): ?>
                        <span class="badge badge-gold"><?=  $m['times_late'] ?></span>
                    <?php else: ?>
                        <span style="color:#999">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <!-- Attendance rate bar -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="flex:1; background:#f0f0f0; border-radius:20px;
                                    height:8px; overflow:hidden;">
                            <div style="width:<?=  $rate ?>%; height:100%;
                                        background:<?=  $rateColor ?>;
                                        border-radius:20px;"></div>
                        </div>
                        <span style="font-weight:700; color:<?=  $rateColor ?>;
                                     font-size:0.85rem; white-space:nowrap;">
                            <?=  $rate ?>%
                        </span>
                    </div>
                </td>
                <td>
                    <?=  $m['last_attended']
                        ? date('d M Y', strtotime($m['last_attended']))
                        : '<span style="color:#999">Never</span>' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
