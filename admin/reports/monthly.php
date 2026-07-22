<?php
// admin/reports/monthly.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'Monthly Attendance Trends';
$pdo       = getPDO();
$year      = (int)($_GET['year'] ?? date('Y'));

// Monthly data
$monthly =  $pdo->prepare("
    SELECT
        DATE_FORMAT(m.meeting_date, '%M')   AS month_name,
        MONTH(m.meeting_date)               AS month_num,
        COUNT(DISTINCT m.id)                AS meetings,
        COALESCE(SUM(ms.total_members_present),    0) AS members,
        COALESCE(SUM(ms.total_visiting_rotarians), 0) AS visitors,
        COALESCE(SUM(ms.total_guests),             0) AS guests,
        COALESCE(SUM(ms.total_attendees),          0) AS total,
        COALESCE(SUM(ms.total_certificates_sent),  0) AS certs
    FROM   meetings m
    LEFT   JOIN meeting_summary ms ON ms.meeting_id = m.id
    WHERE  YEAR(m.meeting_date) = ?
    AND    m.status IN ('Open','Closed')
    GROUP  BY MONTH(m.meeting_date), DATE_FORMAT(m.meeting_date,'%M')
    ORDER  BY month_num
");
$monthly->execute([$year]);
$monthly =  $monthly->fetchAll();

// Year totals
$yearTotal =  $pdo->prepare("
    SELECT
        COUNT(DISTINCT m.id)                       AS meetings,
        COALESCE(SUM(ms.total_members_present),0)  AS members,
        COALESCE(SUM(ms.total_visiting_rotarians),0) AS visitors,
        COALESCE(SUM(ms.total_guests),0)           AS guests,
        COALESCE(SUM(ms.total_attendees),0)        AS total
    FROM meetings m
    LEFT JOIN meeting_summary ms ON ms.meeting_id = m.id
    WHERE YEAR(m.meeting_date) = ?
    AND   m.status IN ('Open','Closed')
");
$yearTotal->execute([$year]);
$yearTotal =  $yearTotal->fetch();

$maxTotal  = max(array_column($monthly, 'total') ?: [1]);
$yearRange = range(date('Y') - 3, date('Y') + 1);

require_once '../includes/layout_top.php';
?>

<!-- Year selector -->
<div class="card mb-4">
    <div class="card-body" style="padding:14px 22px;">
        <form method="GET"
              style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label>Year</label>
                <select name="year">
                    <?php foreach ($yearRange as  $y): ?>
                        <option value="<?=  $y ?>"
                            <?= ($year ===  $y) ? 'selected' : '' ?>>
                            <?=  $y ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 View</button>
            <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="export_csv.php?report=monthly&year=<?=  $year ?>"
                   class="btn btn-green">⬇️ CSV</a>
                <a href="export_pdf.php?report=monthly&year=<?=  $year ?>"
                   class="btn btn-primary">📄 PDF</a>
            </div>
        </form>
    </div>
</div>

<!-- Year totals -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr); margin-bottom:28px;">
    <div class="stat-card">
        <div class="val"><?=  $yearTotal['meetings'] ?></div>
        <div class="lbl">Meetings in <?=  $year ?></div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($yearTotal['total']) ?></div>
        <div class="lbl">Total Attendees</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= number_format($yearTotal['members']) ?></div>
        <div class="lbl">Member Check-Ins</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= number_format($yearTotal['visitors']) ?></div>
        <div class="lbl">Visiting Rotarians</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($yearTotal['guests']) ?></div>
        <div class="lbl">Guests</div>
    </div>
</div>

<!-- Visual Bar Chart -->
<?php if (!empty($monthly)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h2>📊 Monthly Attendance Chart — <?=  $year ?></h2>
    </div>
    <div class="card-body">
        <div style="display:flex; align-items:flex-end; gap:10px;
                    height:200px; padding:0 10px;">
            <?php foreach ($monthly as  $m):
                 $memberH  =  $maxTotal > 0 ? ($m['members']  /  $maxTotal) * 180 : 0;
                 $visitorH =  $maxTotal > 0 ? ($m['visitors'] /  $maxTotal) * 180 : 0;
                 $guestH   =  $maxTotal > 0 ? ($m['guests']   /  $maxTotal) * 180 : 0;
            ?>
            <div style="flex:1; display:flex; flex-direction:column;
                        align-items:center; gap:2px;">
                <!-- Total label -->
                <div style="font-size:0.72rem; font-weight:700;
                            color:#003f87; margin-bottom:4px;">
                    <?=  $m['total'] ?>
                </div>
                <!-- Stacked bar -->
                <div style="width:100%; display:flex; flex-direction:column;
                            align-items:center; justify-content:flex-end; height:180px;">
                    <div style="width:80%; background:#009a44;
                                height:<?=  $guestH ?>px; border-radius:3px 3px 0 0;"
                         title="Guests: <?=  $m['guests'] ?>"></div>
                    <div style="width:80%; background:#f7a800;
                                height:<?=  $visitorH ?>px;"
                         title="Visitors: <?=  $m['visitors'] ?>"></div>
                    <div style="width:80%; background:#003f87;
                                height:<?=  $memberH ?>px; border-radius:0 0 3px 3px;"
                         title="Members: <?=  $m['members'] ?>"></div>
                </div>
                <!-- Month label -->
                <div style="font-size:0.72rem; color:#888; margin-top:6px;">
                    <?= substr($m['month_name'], 0, 3) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Legend -->
        <div style="display:flex; gap:20px; justify-content:center;
                    margin-top:16px; font-size:0.82rem;">
            <span>
                <span style="display:inline-block; width:12px; height:12px;
                             background:#003f87; border-radius:2px;
                             margin-right:4px;"></span>Members
            </span>
            <span>
                <span style="display:inline-block; width:12px; height:12px;
                             background:#f7a800; border-radius:2px;
                             margin-right:4px;"></span>Visiting Rotarians
            </span>
            <span>
                <span style="display:inline-block; width:12px; height:12px;
                             background:#009a44; border-radius:2px;
                             margin-right:4px;"></span>Guests
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Monthly Table -->
<div class="card">
    <div class="card-header">
        <h2>📅 Monthly Breakdown — <?=  $year ?></h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Meetings</th>
                    <th>Members</th>
                    <th>Visitors</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Certs Sent</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($monthly)): ?>
                <tr>
                    <td colspan="8"
                        style="text-align:center; color:#999; padding:30px;">
                        No meeting data for <?=  $year ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($monthly as  $m):
                     $barWidth =  $maxTotal > 0
                        ? round(($m['total'] /  $maxTotal) * 100) : 0;
                ?>
                <tr>
                    <td><strong><?=  $m['month_name'] ?></strong></td>
                    <td><?=  $m['meetings'] ?></td>
                    <td><?=  $m['members'] ?></td>
                    <td><?=  $m['visitors'] ?></td>
                    <td><?=  $m['guests'] ?></td>
                    <td><strong style="color:#003f87"><?=  $m['total'] ?></strong></td>
                    <td><?=  $m['certs'] ?></td>
                    <td style="width:120px;">
                        <div style="background:#f0f0f0; border-radius:20px;
                                    height:8px; overflow:hidden;">
                            <div style="width:<?=  $barWidth ?>%; height:100%;
                                        background:#003f87;
                                        border-radius:20px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($monthly)): ?>
            <tfoot>
                <tr style="background:#f0f4f8; font-weight:700;">
                    <td style="padding:11px 14px;">TOTAL</td>
                    <td><?= array_sum(array_column($monthly,'meetings')) ?></td>
                    <td><?= array_sum(array_column($monthly,'members')) ?></td>
                    <td><?= array_sum(array_column($monthly,'visitors')) ?></td>
                    <td><?= array_sum(array_column($monthly,'guests')) ?></td>
                    <td><?= array_sum(array_column($monthly,'total')) ?></td>
                    <td><?= array_sum(array_column($monthly,'certs')) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
