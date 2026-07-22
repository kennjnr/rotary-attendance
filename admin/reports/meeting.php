<?php
// admin/reports/meeting.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/ReportGenerator.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$pdo      = getPDO();
$reporter = new ReportGenerator($pdo);
$data     =  $reporter->getMeetingReport($id);

if (!$data) { header('Location: index.php'); exit; }

extract($data);
//  $meeting,  $summary,  $members,  $visitors,  $guests,  $certStats,  $visitingByClub

$pageTitle = 'Report: ' .  $meeting['title'];

require_once '../includes/layout_top.php';
?>

<!-- Action Bar -->
<div class="page-actions" style="margin-bottom:22px;">
    <a href="index.php" class="btn btn-outline">← All Reports</a>
    <a href="../meetings/view.php?id=<?=  $id ?>"
       class="btn btn-outline">📅 Meeting Detail</a>
    <div style="margin-left:auto; display:flex; gap:10px;">
        <a href="export_csv.php?id=<?=  $id ?>"
           class="btn btn-green">⬇️ Export CSV</a>
        <a href="export_pdf.php?id=<?=  $id ?>"
           class="btn btn-primary">📄 Export PDF</a>
    </div>
</div>

<!-- Meeting Header -->
<div class="card mb-4">
    <div class="card-body"
         style="display:grid; grid-template-columns:1fr auto; gap:20px; align-items:start;">
        <div>
            <h2 style="color:#003f87; font-size:1.3rem; margin-bottom:10px;">
                <?= htmlspecialchars($meeting['title']) ?>
            </h2>
            <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:0.9rem; color:#555;">
                <span>📅 <?= date('l, d F Y', strtotime($meeting['meeting_date'])) ?></span>
                <span>🕐 <?= date('h:i A', strtotime($meeting['start_time'])) ?>
                    <?=  $meeting['end_time']
                        ? '— ' . date('h:i A', strtotime($meeting['end_time']))
                        : '' ?>
                </span>
                <span>📍 <?= htmlspecialchars($meeting['venue'] ?? 'N/A') ?></span>
                <span>🏛️ <?= htmlspecialchars($meeting['club_name']) ?></span>
                <?php if ($meeting['theme']): ?>
                    <span>💡 <?= htmlspecialchars($meeting['theme']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
         $badgeMap = [
            'Scheduled' => 'badge-gold',
            'Open'      => 'badge-green',
            'Closed'    => 'badge-gray',
            'Cancelled' => 'badge-red',
        ];
        ?>
        <span class="badge <?=  $badgeMap[$meeting['status']] ?? 'badge-gray' ?>"
              style="font-size:0.9rem; padding:6px 16px;">
            <?=  $meeting['status'] ?>
        </span>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid"
     style="grid-template-columns:repeat(6,1fr); margin-bottom:28px;">
    <div class="stat-card">
        <div class="val"><?=  $summary['total_members_present'] ?? 0 ?></div>
        <div class="lbl">Members Present</div>
    </div>
    <div class="stat-card red">
        <div class="val"><?=  $summary['total_late_members'] ?? 0 ?></div>
        <div class="lbl">Late Arrivals</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?=  $summary['total_visiting_rotarians'] ?? 0 ?></div>
        <div class="lbl">Visiting Rotarians</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?=  $summary['total_guests'] ?? 0 ?></div>
        <div class="lbl">Guests</div>
    </div>
    <div class="stat-card">
        <div class="val"><?=  $summary['total_attendees'] ?? 0 ?></div>
        <div class="lbl">Total Attendees</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?=  $certStats['total_sent'] ?? 0 ?></div>
        <div class="lbl">Certs Delivered</div>
    </div>
</div>

<!-- Two-column: Cert Stats + Visiting Clubs -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">

    <!-- Certificate Summary -->
    <div class="card">
        <div class="card-header"><h2>🏅 Certificate Summary</h2></div>
        <div class="card-body">
            <table style="width:100%; font-size:0.92rem; line-height:2.2;">
                <tr>
                    <td style="color:#888">Total Issued</td>
                    <td><strong><?=  $certStats['total_issued'] ?? 0 ?></strong></td>
                </tr>
                <tr>
                    <td style="color:#888">Successfully Sent</td>
                    <td>
                        <span class="badge badge-green">
                            <?=  $certStats['total_sent'] ?? 0 ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color:#888">Failed / Pending</td>
                    <td>
                        <?php  $failed =  $certStats['total_failed'] ?? 0; ?>
                        <span class="badge <?=  $failed > 0 ? 'badge-red' : 'badge-gray' ?>">
                            <?=  $failed ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color:#888">First Issued At</td>
                    <td>
                        <?=  $certStats['first_issued']
                            ? date('h:i A', strtotime($certStats['first_issued']))
                            : '—' ?>
                    </td>
                </tr>
                <tr>
                    <td style="color:#888">Last Issued At</td>
                    <td>
                        <?=  $certStats['last_issued']
                            ? date('h:i A', strtotime($certStats['last_issued']))
                            : '—' ?>
                    </td>
                </tr>
                <tr>
                    <td style="color:#888">Delivery Rate</td>
                    <td>
                        <?php
                         $rate = ($certStats['total_issued'] ?? 0) > 0
                            ? round(($certStats['total_sent'] /
                                      $certStats['total_issued']) * 100)
                            : 0;
                        ?>
                        <strong style="color:<?=  $rate >= 80 ? '#009a44' : '#c0392b' ?>">
                            <?=  $rate ?>%
                        </strong>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Visiting Clubs Breakdown -->
    <div class="card">
        <div class="card-header"><h2>🔵 Visiting Clubs</h2></div>
        <div class="card-body">
            <?php if (empty($visitingByClub)): ?>
                <p style="color:#999; text-align:center; padding:20px 0;">
                    No visiting Rotarians for this meeting.
                </p>
            <?php else: ?>
                <table style="width:100%; font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left; color:#888;
                                       font-size:0.78rem; padding-bottom:8px;">
                                Club Name
                            </th>
                            <th style="text-align:right; color:#888;
                                       font-size:0.78rem; padding-bottom:8px;">
                                Count
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visitingByClub as  $vc): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:7px 0;">
                                <?= htmlspecialchars($vc['home_club_name']) ?>
                            </td>
                            <td style="text-align:right;">
                                <span class="badge badge-blue"><?=  $vc['count'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Member Attendance Table -->
<div class="card mb-4">
    <div class="card-header">
        <h2>✅ Club Members (<?= count($members) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Rotary ID</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Check-In</th>
                    <th>Status</th>
                    <th>Certificate No</th>
                    <th>Email Sent</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="10"
                        style="text-align:center; color:#999; padding:24px;">
                        No members checked in.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as  $i =>  $row): ?>
                <tr>
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>
                        </strong>
                    </td>
                    <td><?= htmlspecialchars($row['role']) ?></td>
                    <td><?= htmlspecialchars($row['rotary_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                    <td><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                    <td>
                        <?=  $row['is_late']
                            ? '<span class="badge badge-red">Late</span>'
                            : '<span class="badge badge-green">On Time</span>' ?>
                    </td>
                    <td style="font-size:0.82rem; color:#555;">
                        <?= htmlspecialchars($row['certificate_no'] ?? '—') ?>
                    </td>
                    <td>
                        <?php if ($row['email_sent']): ?>
                            <span class="badge badge-green">✅ Sent</span>
                        <?php else: ?>
                            <span class="badge badge-red">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Visiting Rotarians Table -->
<div class="card mb-4">
    <div class="card-header">
        <h2>🔵 Visiting Rotarians (<?= count($visitors) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Home Club</th>
                    <th>District</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Check-In</th>
                    <th>Status</th>
                    <th>Cert No</th>
                    <th>Email Sent</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($visitors)): ?>
                <tr>
                    <td colspan="11"
                        style="text-align:center; color:#999; padding:24px;">
                        No visiting Rotarians.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($visitors as  $i =>  $row): ?>
                <tr>
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>
                        </strong>
                    </td>
                    <td><?= htmlspecialchars($row['home_club_name']) ?></td>
                    <td><?= htmlspecialchars($row['district'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['role_in_club'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                    <td><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                    <td>
                        <?=  $row['is_late']
                            ? '<span class="badge badge-red">Late</span>'
                            : '<span class="badge badge-green">On Time</span>' ?>
                    </td>
                    <td style="font-size:0.82rem; color:#555;">
                        <?= htmlspecialchars($row['certificate_no'] ?? '—') ?>
                    </td>
                    <td>
                        <?=  $row['email_sent']
                            ? '<span class="badge badge-green">✅ Sent</span>'
                            : '<span class="badge badge-red">⏳ Pending</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Guests Table -->
<div class="card">
    <div class="card-header">
        <h2>🟢 Guests (<?= count($guests) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Organization</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Invited By</th>
                    <th>Check-In</th>
                    <th>Status</th>
                    <th>Cert No</th>
                    <th>Email Sent</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($guests)): ?>
                <tr>
                    <td colspan="10"
                        style="text-align:center; color:#999; padding:24px;">
                        No guests.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($guests as  $i =>  $row): ?>
                <tr>
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>
                        </strong>
                    </td>
                    <td><?= htmlspecialchars($row['organization'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                    <td>
                        <?= ($row['host_first'] ?? false)
                            ? htmlspecialchars($row['host_first'].' '.$row['host_last'])
                            : '—' ?>
                    </td>
                    <td><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                    <td>
                        <?=  $row['is_late']
                            ? '<span class="badge badge-red">Late</span>'
                            : '<span class="badge badge-green">On Time</span>' ?>
                    </td>
                    <td style="font-size:0.82rem; color:#555;">
                        <?= htmlspecialchars($row['certificate_no'] ?? '—') ?>
                    </td>
                    <td>
                        <?=  $row['email_sent']
                            ? '<span class="badge badge-green">✅ Sent</span>'
                            : '<span class="badge badge-red">⏳ Pending</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
