<?php
// admin/reports/visiting_clubs.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'Visiting Clubs Report';
$pdo       = getPDO();
$fromDate  =  $_GET['from_date'] ?? date('Y-01-01');
$toDate    =  $_GET['to_date']   ?? date('Y-12-31');

// Visits by club
$byClub =  $pdo->prepare("
    SELECT vr.home_club_name,
           vr.district,
           COUNT(*)                    AS total_visits,
           COUNT(DISTINCT vr.email)    AS unique_visitors,
           COUNT(DISTINCT vr.meeting_id) AS meetings_visited,
           MAX(m.meeting_date)         AS last_visit
    FROM   visiting_rotarians vr
    JOIN   meetings m ON m.id = vr.meeting_id
    WHERE  m.meeting_date BETWEEN ? AND ?
    GROUP  BY vr.home_club_name, vr.district
    ORDER  BY total_visits DESC
");
$byClub->execute([$fromDate,  $toDate]);
$byClub =  $byClub->fetchAll();

// Individual visitors
$visitors =  $pdo->prepare("
    SELECT vr.first_name, vr.last_name, vr.email, vr.phone,
           vr.home_club_name, vr.district, vr.role_in_club,
           COUNT(*)             AS total_visits,
           MAX(m.meeting_date)  AS last_visit,
           GROUP_CONCAT(m.title ORDER BY m.meeting_date SEPARATOR ' | ')
                                AS meetings_attended
    FROM   visiting_rotarians vr
    JOIN   meetings m ON m.id = vr.meeting_id
    WHERE  m.meeting_date BETWEEN ? AND ?
    GROUP  BY vr.email
    ORDER  BY total_visits DESC, vr.last_name
");
$visitors->execute([$fromDate,  $toDate]);
$visitors =  $visitors->fetchAll();

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
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="visiting_clubs.php" class="btn btn-outline">✕ Reset</a>
            <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="export_csv.php?report=visiting_clubs
                         &from_date=<?=  $fromDate ?>&to_date=<?=  $toDate ?>"
                   class="btn btn-green">⬇️ CSV</a>
                <a href="export_pdf.php?report=visiting_clubs
                         &from_date=<?=  $fromDate ?>&to_date=<?=  $toDate ?>"
                   class="btn btn-primary">📄 PDF</a>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:24px;">
    <div class="stat-card gold">
        <div class="val"><?= count($byClub) ?></div>
        <div class="lbl">Clubs Visited</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= count($visitors) ?></div>
        <div class="lbl">Unique Visitors</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= array_sum(array_column($byClub,'total_visits')) ?></div>
        <div class="lbl">Total Visits</div>
    </div>
</div>

<!-- By Club -->
<div class="card mb-4">
    <div class="card-header">
        <h2>🏛️ Visits by Club (<?= count($byClub) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Club Name</th>
                    <th>District</th>
                    <th>Total Visits</th>
                    <th>Unique Visitors</th>
                    <th>Meetings Visited</th>
                    <th>Last Visit</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($byClub)): ?>
                <tr>
                    <td colspan="7"
                        style="text-align:center; color:#999; padding:30px;">
                        No visiting Rotarians in this period.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($byClub as  $i =>  $c): ?>
                <tr>
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($c['home_club_name']) ?></strong></td>
                    <td><?= htmlspecialchars($c['district'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-blue"><?=  $c['total_visits'] ?></span>
                    </td>
                    <td><?=  $c['unique_visitors'] ?></td>
                    <td><?=  $c['meetings_visited'] ?></td>
                    <td><?= date('d M Y', strtotime($c['last_visit'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Individual Visitors -->
<div class="card">
    <div class="card-header">
        <h2>🔵 Individual Visitors (<?= count($visitors) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Home Club</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Visits</th>
                    <th>Last Visit</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($visitors as  $i =>  $v): ?>
            <tr>
                <td style="color:#999"><?=  $i + 1 ?></td>
                <td>
                    <strong>
                        <?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?>
                    </strong>
                </td>
                <td><?= htmlspecialchars($v['home_club_name']) ?></td>
                <td><?= htmlspecialchars($v['role_in_club'] ?? '—') ?></td>
                <td><?= htmlspecialchars($v['email']) ?></td>
                <td>
                    <span class="badge badge-gold"><?=  $v['total_visits'] ?></span>
                </td>
                <td><?= date('d M Y', strtotime($v['last_visit'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
