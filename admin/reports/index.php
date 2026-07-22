<?php
// admin/reports/index.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/ReportGenerator.php';

$pageTitle = 'Meeting Reports';
$pdo       = getPDO();
$reporter  = new ReportGenerator($pdo);

// Filters
$filters = [
    'from_date' =>  $_GET['from_date'] ?? '',
    'to_date'   =>  $_GET['to_date']   ?? '',
    'status'    =>  $_GET['status']    ?? '',
];

$meetings   =  $reporter->getAllMeetingsSummary($filters);
$aggregates =  $reporter->getClubAggregates();

require_once '../includes/layout_top.php';
?>

<!-- Aggregate Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:28px;">
    <div class="stat-card">
        <div class="val"><?= number_format($aggregates['total_meetings']) ?></div>
        <div class="lbl">Total Meetings</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($aggregates['grand_total']) ?></div>
        <div class="lbl">Total Attendees (All Time)</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= number_format($aggregates['total_certs_sent']) ?></div>
        <div class="lbl">Certificates Sent</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= number_format($aggregates['avg_attendance'], 1) ?></div>
        <div class="lbl">Avg Attendance / Meeting</div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 22px;">
        <form method="GET" style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label>From Date</label>
                <input type="date" name="from_date"
                       value="<?= htmlspecialchars($filters['from_date']) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>To Date</label>
                <input type="date" name="to_date"
                       value="<?= htmlspecialchars($filters['to_date']) ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['Scheduled','Open','Closed','Cancelled'] as  $s): ?>
                        <option value="<?=  $s ?>"
                            <?= ($filters['status']===$s) ? 'selected' : '' ?>>
                            <?=  $s ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="index.php" class="btn btn-outline">✕ Reset</a>

            <!-- Bulk export -->
            <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="export_csv.php?<?= http_build_query($filters) ?>&all=1"
                   class="btn btn-green">⬇️ Export All CSV</a>
                <a href="export_pdf.php?<?= http_build_query($filters) ?>&all=1"
                   class="btn btn-primary">📄 Export All PDF</a>
            </div>
        </form>
    </div>
</div>

<!-- Meetings Table -->
<div class="card">
    <div class="card-header">
        <h2>📋 Meeting Reports (<?= count($meetings) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Meeting</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Members</th>
                    <th>Late</th>
                    <th>Visitors</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Certs Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($meetings)): ?>
                <tr>
                    <td colspan="11"
                        style="text-align:center; color:#999; padding:40px;">
                        No meetings found for the selected filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($meetings as  $i =>  $m):
                     $badgeMap = [
                        'Scheduled' => 'badge-gold',
                        'Open'      => 'badge-green',
                        'Closed'    => 'badge-gray',
                        'Cancelled' => 'badge-red',
                    ];
                     $badge =  $badgeMap[$m['status']] ?? 'badge-gray';
                ?>
                <tr>
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($m['title']) ?></strong><br>
                        <small style="color:#888">
                            <?= htmlspecialchars($m['venue'] ?? '—') ?>
                        </small>
                    </td>
                    <td>
                        <?= date('d M Y', strtotime($m['meeting_date'])) ?><br>
                        <small style="color:#888">
                            <?= date('h:i A', strtotime($m['start_time'])) ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge <?=  $badge ?>"><?=  $m['status'] ?></span>
                    </td>
                    <td><?=  $m['members'] ?></td>
                    <td>
                        <?php if ($m['late'] > 0): ?>
                            <span class="badge badge-red"><?=  $m['late'] ?></span>
                        <?php else: ?>
                            <span style="color:#999">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?=  $m['visitors'] ?></td>
                    <td><?=  $m['guests'] ?></td>
                    <td>
                        <strong style="color:#003f87"><?=  $m['total'] ?></strong>
                    </td>
                    <td>
                        <?php
                         $totalIssued =  $m['members'] +  $m['visitors'] +  $m['guests'];
                         $pct =  $totalIssued > 0
                            ? round(($m['certs_sent'] /  $totalIssued) * 100)
                            : 0;
                        ?>
                        <?=  $m['certs_sent'] ?>
                        <small style="color:#888">(<?=  $pct ?>%)</small>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="meeting.php?id=<?=  $m['id'] ?>"
                               class="btn btn-outline btn-sm">👁 View</a>
                            <a href="export_pdf.php?id=<?=  $m['id'] ?>"
                               class="btn btn-primary btn-sm">📄 PDF</a>
                            <a href="export_csv.php?id=<?=  $m['id'] ?>"
                               class="btn btn-green btn-sm">📊 CSV</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>

            <!-- Totals footer -->
            <?php if (!empty($meetings)): ?>
            <tfoot>
                <tr style="background:#f0f4f8; font-weight:700;">
                    <td colspan="4" style="padding:11px 14px; color:#555;">
                        TOTALS (<?= count($meetings) ?> meetings)
                    </td>
                    <td><?= array_sum(array_column($meetings,'members')) ?></td>
                    <td><?= array_sum(array_column($meetings,'late')) ?></td>
                    <td><?= array_sum(array_column($meetings,'visitors')) ?></td>
                    <td><?= array_sum(array_column($meetings,'guests')) ?></td>
                    <td><?= array_sum(array_column($meetings,'total')) ?></td>
                    <td><?= array_sum(array_column($meetings,'certs_sent')) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
