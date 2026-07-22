<?php
// admin/reports/absentee.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle  = 'Absentee Report';
$pdo        = getPDO();
$fromDate   =  $_GET['from_date'] ?? date('Y-01-01');
$toDate     =  $_GET['to_date']   ?? date('Y-12-31');
$minMissed  = (int)($_GET['min_missed'] ?? 1);

// All closed/open meetings in period
$meetings =  $pdo->prepare("
    SELECT id, title, meeting_date FROM meetings
    WHERE  meeting_date BETWEEN ? AND ?
    AND    status IN ('Open','Closed')
    ORDER  BY meeting_date ASC
");
$meetings->execute([$fromDate,  $toDate]);
$meetings =  $meetings->fetchAll();
$meetingIds   = array_column($meetings, 'id');
$totalMeetings = count($meetingIds);

// Members who missed at least  $minMissed meetings
$absentees = [];
if ($totalMeetings > 0) {
     $placeholders = implode(',', array_fill(0,  $totalMeetings, '?'));
     $stmt =  $pdo->prepare("
        SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.role,
               COUNT(ma.id)                  AS attended,
               ? - COUNT(ma.id)              AS missed,
               GROUP_CONCAT(
                   CASE WHEN ma.id IS NULL
                        THEN mtg.meeting_date END
                   ORDER BY mtg.meeting_date
                   SEPARATOR ', '
               )                             AS missed_dates
        FROM   members m
        CROSS  JOIN meetings mtg
        LEFT   JOIN member_attendance ma
               ON  ma.member_id  = m.id
               AND ma.meeting_id = mtg.id
        WHERE  m.is_active = 1
        AND    mtg.id IN ($placeholders)
        GROUP  BY m.id
        HAVING missed >= ?
        ORDER  BY missed DESC, m.last_name
    ");
     $params = array_merge([$totalMeetings],  $meetingIds, [$minMissed]);
     $stmt->execute($params);
     $absentees =  $stmt->fetchAll();
}

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
                <label>Min Meetings Missed</label>
                <select name="min_missed">
                    <?php foreach ([1,2,3,4,5] as  $n): ?>
                        <option value="<?=  $n ?>"
                            <?= ($minMissed ===  $n) ? 'selected' : '' ?>>
                            <?=  $n ?>+
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="absentee.php" class="btn btn-outline">✕ Reset</a>
            <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="export_csv.php?report=absentee
                         &from_date=<?=  $fromDate ?>&to_date=<?=  $toDate ?>
                         &min_missed=<?=  $minMissed ?>"
                   class="btn btn-green">⬇️ CSV</a>
                <a href="export_pdf.php?report=absentee
                         &from_date=<?=  $fromDate ?>&to_date=<?=  $toDate ?>
                         &min_missed=<?=  $minMissed ?>"
                   class="btn btn-primary">📄 PDF</a>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:24px;">
    <div class="stat-card">
        <div class="val"><?=  $totalMeetings ?></div>
        <div class="lbl">Meetings in Period</div>
    </div>
    <div class="stat-card red">
        <div class="val"><?= count($absentees) ?></div>
        <div class="lbl">Members with Absences</div>
    </div>
    <div class="stat-card gold">
        <div class="val">
            <?= count($absentees) > 0
                ? round(array_sum(array_column($absentees,'missed'))
                        / count($absentees), 1)
                : 0 ?>
        </div>
        <div class="lbl">Avg Meetings Missed</div>
    </div>
</div>

<!-- Absentee Table -->
<div class="card">
    <div class="card-header">
        <h2>🚫 Absentees (<?= count($absentees) ?>)</h2>
        <span style="font-size:0.82rem; color:#888;">
            Missed <?=  $minMissed ?>+ meetings between
            <?= date('d M Y', strtotime($fromDate)) ?> —
            <?= date('d M Y', strtotime($toDate)) ?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Attended</th>
                    <th>Missed</th>
                    <th>Attendance Rate</th>
                    <th>Missed Meeting Dates</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($absentees)): ?>
                <tr>
                    <td colspan="8"
                        style="text-align:center; color:#999; padding:30px;">
                        No absentees found for the selected criteria. 🎉
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($absentees as  $i =>  $a):
                     $rate =  $totalMeetings > 0
                        ? round(($a['attended'] /  $totalMeetings) * 100, 1) : 0;
                     $rateColor =  $rate >= 80
                        ? '#009a44' : ($rate >= 50 ? '#f7a800' : '#c0392b');
                ?>
                <tr>
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?>
                        </strong><br>
                        <small style="color:#888">
                            <?= htmlspecialchars($a['email']) ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge badge-blue">
                            <?= htmlspecialchars($a['role']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($a['phone'] ?? '—') ?></td>
                    <td><?=  $a['attended'] ?> / <?=  $totalMeetings ?></td>
                    <td>
                        <span class="badge badge-red"><?=  $a['missed'] ?></span>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="flex:1; background:#f0f0f0;
                                        border-radius:20px; height:8px; overflow:hidden;">
                                <div style="width:<?=  $rate ?>%; height:100%;
                                            background:<?=  $rateColor ?>;
                                            border-radius:20px;"></div>
                            </div>
                            <span style="font-weight:700; color:<?=  $rateColor ?>;
                                         font-size:0.85rem;">
                                <?=  $rate ?>%
                            </span>
                        </div>
                    </td>
                    <td style="font-size:0.8rem; color:#666; max-width:200px;">
                        <?php
                         $dates = array_filter(
                            explode(', ',  $a['missed_dates'] ?? ''));
                        foreach ($dates as  $d): ?>
                            <span class="badge badge-red"
                                  style="margin:2px; font-size:0.72rem;">
                                <?= date('d M', strtotime($d)) ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
