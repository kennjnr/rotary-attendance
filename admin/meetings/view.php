<?php
// admin/meetings/view.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/QRGenerator.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$pdo     = getPDO();
$meeting =  $pdo->query("
    SELECT m.*, c.club_name
    FROM   meetings m JOIN clubs c ON c.id=m.club_id
    WHERE  m.id=$id
")->fetch();

if (!$meeting) { header('Location: index.php'); exit; }

$pageTitle = 'Meeting: ' .  $meeting['title'];

// Attendance lists
$memberAttendance =  $pdo->query("
    SELECT ma.*, m.first_name, m.last_name, m.rotary_id, m.role,
           cert.certificate_no, cert.email_sent
    FROM   member_attendance ma
    JOIN   members m ON m.id = ma.member_id
    LEFT   JOIN certificates cert ON cert.id = ma.certificate_id
    WHERE  ma.meeting_id =  $id
    ORDER  BY ma.check_in_time
")->fetchAll();

$visitors =  $pdo->query("
    SELECT vr.*, cert.certificate_no, cert.email_sent
    FROM   visiting_rotarians vr
    LEFT   JOIN certificates cert ON cert.id = vr.certificate_id
    WHERE  vr.meeting_id =  $id
    ORDER  BY vr.check_in_time
")->fetchAll();

$guests =  $pdo->query("
    SELECT g.*, m.first_name AS host_first, m.last_name AS host_last,
           cert.certificate_no, cert.email_sent
    FROM   guests g
    LEFT   JOIN members m ON m.id = g.host_member_id
    LEFT   JOIN certificates cert ON cert.id = g.certificate_id
    WHERE  g.meeting_id =  $id
    ORDER  BY g.check_in_time
")->fetchAll();

$summary =  $pdo->query("SELECT * FROM meeting_summary WHERE meeting_id=$id")->fetch();

$qrUrl     = QRGenerator::getUrl($meeting['qr_token']);
$qrWebPath = QRGenerator::getWebPath($meeting['qr_token']);
QRGenerator::generate($qrUrl,  $meeting['qr_token']); // ensure generated

require_once '../includes/layout_top.php';
?>

<!-- Header row -->
<div class="page-actions">
    <a href="index.php" class="btn btn-outline">← Meetings</a>
    <a href="edit.php?id=<?=  $id ?>" class="btn btn-gold">✏️ Edit</a>
    <a href="../reports/meeting.php?id=<?=  $id ?>" class="btn btn-primary">📋 Full Report</a>
</div>

<!-- Summary Stats -->
<?php if ($summary): ?>
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
    <div class="stat-card">
        <div class="val"><?=  $summary['total_members_present'] ?></div>
        <div class="lbl">Members</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?=  $summary['total_late_members'] ?></div>
        <div class="lbl">Late Arrivals</div>
    </div>
    <div class="stat-card">
        <div class="val"><?=  $summary['total_visiting_rotarians'] ?></div>
        <div class="lbl">Visiting Rotarians</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?=  $summary['total_guests'] ?></div>
        <div class="lbl">Guests</div>
    </div>
    <div class="stat-card">
        <div class="val"><?=  $summary['total_certificates_sent'] ?></div>
        <div class="lbl">Certs Sent</div>
    </div>
</div>
<?php endif; ?>

<!-- Meeting Info + QR -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><h2>📋 Meeting Info</h2></div>
        <div class="card-body">
            <table style="width:100%; font-size:0.92rem; line-height:2.2;">
                <tr><td style="color:#888;width:35%">Title</td>
                    <td><strong><?= htmlspecialchars($meeting['title']) ?></strong></td></tr>
                <tr><td style="color:#888">Club</td>
                    <td><?= htmlspecialchars($meeting['club_name']) ?></td></tr>
                <tr><td style="color:#888">Date</td>
                    <td><?= date('l, d F Y', strtotime($meeting['meeting_date'])) ?></td></tr>
                <tr><td style="color:#888">Time</td>
                    <td><?= date('h:i A', strtotime($meeting['start_time'])) ?>
                        <?=  $meeting['end_time'] ? '— '.date('h:i A',strtotime($meeting['end_time'])) : '' ?>
                    </td></tr>
                <tr><td style="color:#888">Venue</td>
                    <td><?= htmlspecialchars($meeting['venue'] ?? '—') ?></td></tr>
                <tr><td style="color:#888">Theme</td>
                    <td><?= htmlspecialchars($meeting['theme'] ?? '—') ?></td></tr>
                <tr><td style="color:#888">Status</td>
                    <td>
                        <?php
                         $badgeMap = ['Scheduled'=>'badge-gold','Open'=>'badge-green','Closed'=>'badge-gray','Cancelled'=>'badge-red'];
                        ?>
                        <span class="badge <?=  $badgeMap[$meeting['status']] ?? 'badge-gray' ?>">
                            <?=  $meeting['status'] ?>
                        </span>
                    </td></tr>
                <tr><td style="color:#888">QR Expires</td>
                    <td><?=  $meeting['qr_expires_at']
                            ? date('d M Y, h:i A', strtotime($meeting['qr_expires_at']))
                            : 'No expiry' ?>
                    </td></tr>
            </table>
        </div>
    </div>

    <!-- QR Code Panel -->
    <div class="card" style="text-align:center;">
        <div class="card-header"><h2>📲 QR Code & Check-In Link</h2></div>
        <div class="card-body">

            <!-- QR Image -->
            <img src="<?= htmlspecialchars($qrWebPath) ?>"
                alt="QR Code"
                style="max-width:300px; width:100%; border:3px solid #003f87;
                        border-radius:10px; padding:6px;">

            <p style="font-size:0.78rem; color:#888; margin:10px 0;">
                Scan to check in
            </p>

            <!-- Download -->
            <a href="<?= htmlspecialchars($qrWebPath) ?>"
            download="QR_Meeting_<?=  $id ?>.png"
            class="btn btn-gold btn-sm">
                ⬇️ Download QR
            </a>

            <!-- Check-In Link Box -->
            <div style="background:#f0f4f8; border-radius:10px;
                        padding:14px; margin-top:16px; text-align:left;">
                <p style="font-size:0.8rem; font-weight:600;
                        color:#555; margin-bottom:8px;">
                    🔗 Check-In Link
                </p>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="text"
                        id="checkin_url"
                        value="<?= htmlspecialchars($qrUrl) ?>"
                        readonly
                        style="flex:1; font-size:0.75rem; padding:8px 10px;
                                border:1.5px solid #dee2e6; border-radius:8px;
                                background:#fff; color:#333; cursor:text;">
                    <button onclick="copyCheckinUrl()"
                            id="copy_btn"
                            class="btn btn-primary btn-sm"
                            style="white-space:nowrap;">
                        📋 Copy
                    </button>
                </div>
            </div>

            <!-- Share Buttons -->
            <div style="margin-top:14px; display:flex; flex-direction:column; gap:8px;">

                <!-- Open in browser -->
                <a href="<?= htmlspecialchars($qrUrl) ?>"
                target="_blank"
                class="btn btn-green btn-sm"
                style="justify-content:center;">
                    🌐 Open Check-In Page
                </a>

                <!-- WhatsApp share -->
                <a href="https://wa.me/?text=<?= urlencode(
                        'Please use this link to check in for our Rotary meeting: '
                        .  $qrUrl) ?>"
                target="_blank"
                class="btn btn-sm"
                style="background:#25D366; color:#fff; justify-content:center;">
                    💬 Share via WhatsApp
                </a>

                <!-- Email share -->
                <a href="mailto:?subject=<?= urlencode('Rotary Meeting Check-In: ' .  $meeting['title']) ?>&body=<?= urlencode(
                        'Dear Rotarian,' . "\n\n"
                        . 'Please use the link below to check in for our meeting:' . "\n\n"
                        .  $meeting['title'] . "\n"
                        . date('l, d F Y', strtotime($meeting['meeting_date'])) . "\n"
                        . ($meeting['venue'] ?? '') . "\n\n"
                        . 'Check-In Link: ' .  $qrUrl . "\n\n"
                        . 'Yours in Rotary Service') ?>"
                class="btn btn-outline btn-sm"
                style="justify-content:center;">
                    📧 Share via Email
                </a>

            </div>

            <hr style="margin:16px 0; border:none; border-top:1px solid #eee;">

            <!-- Status Toggle -->
            <?php if ($meeting['status'] === 'Scheduled'): ?>
                <form method="POST" action="../meetings/index.php">
                    <input type="hidden" name="meeting_id" value="<?=  $id ?>">
                    <input type="hidden" name="new_status"  value="Open">
                    <button name="toggle_status"
                            class="btn btn-green btn-sm"
                            style="width:100%;">
                        ▶ Open Check-In
                    </button>
                </form>
            <?php elseif ($meeting['status'] === 'Open'): ?>
                <form method="POST" action="../meetings/index.php">
                    <input type="hidden" name="meeting_id" value="<?=  $id ?>">
                    <input type="hidden" name="new_status"  value="Closed">
                    <button name="toggle_status"
                            class="btn btn-red btn-sm"
                            style="width:100%;">
                        ⏹ Close Check-In
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <script>
    function copyCheckinUrl() {
        const input = document.getElementById('checkin_url');
        const btn   = document.getElementById('copy_btn');
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            btn.textContent      = '✅ Copied!';
            btn.style.background = '#009a44';
            setTimeout(() => {
                btn.textContent      = '📋 Copy';
                btn.style.background = '';
            }, 2500);
        }).catch(() => {
            document.execCommand('copy');
            btn.textContent = '✅ Copied!';
            setTimeout(() => { btn.textContent = '📋 Copy'; }, 2500);
        });
    }
    </script>

</div>

<!-- Member Attendance -->
<div class="card mb-4">
    <div class="card-header">
        <h2>✅ Club Members (<?= count($memberAttendance) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Rotary ID</th><th>Role</th>
                    <th>Check-In Time</th><th>Late?</th>
                    <th>Certificate</th><th>Email Sent</th></tr>
            </thead>
            <tbody>
            <?php if (empty($memberAttendance)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:20px">No members checked in yet.</td></tr>
            <?php else: ?>
                <?php foreach ($memberAttendance as  $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['rotary_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['role']) ?></td>
                    <td><?= date('h:i A', strtotime($a['check_in_time'])) ?></td>
                    <td><?=  $a['is_late'] ? '<span class="badge badge-red">Late</span>' : '<span class="badge badge-green">On Time</span>' ?></td>
                    <td><?= htmlspecialchars($a['certificate_no'] ?? '—') ?></td>
                    <td><?=  $a['email_sent'] ? '✅' : '⏳' ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Visiting Rotarians -->
<div class="card mb-4">
    <div class="card-header">
        <h2>🔵 Visiting Rotarians (<?= count($visitors) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Home Club</th><th>District</th><th>Role</th>
                    <th>Email</th><th>Check-In</th><th>Late?</th><th>Cert Sent</th></tr>
            </thead>
            <tbody>
            <?php if (empty($visitors)): ?>
                <tr><td colspan="8" style="text-align:center;color:#999;padding:20px">No visiting Rotarians.</td></tr>
            <?php else: ?>
                <?php foreach ($visitors as  $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></strong></td>
                    <td><?= htmlspecialchars($v['home_club_name']) ?></td>
                    <td><?= htmlspecialchars($v['district'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($v['role_in_club'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($v['email']) ?></td>
                    <td><?= date('h:i A', strtotime($v['check_in_time'])) ?></td>
                    <td><?=  $v['is_late'] ? '<span class="badge badge-red">Late</span>' : '<span class="badge badge-green">On Time</span>' ?></td>
                    <td><?=  $v['email_sent'] ? '✅' : '⏳' ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Guests -->
<div class="card">
    <div class="card-header">
        <h2>🟢 Guests (<?= count($guests) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Organization</th><th>Email</th>
                    <th>Invited By</th><th>Check-In</th><th>Late?</th><th>Cert Sent</th></tr>
            </thead>
            <tbody>
            <?php if (empty($guests)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:20px">No guests.</td></tr>
            <?php else: ?>
                <?php foreach ($guests as  $g): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($g['first_name'].' '.$g['last_name']) ?></strong></td>
                    <td><?= htmlspecialchars($g['organization'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($g['email']) ?></td>
                    <td><?=  $g['host_first'] ? htmlspecialchars($g['host_first'].' '.$g['host_last']) : '—' ?></td>
                    <td><?= date('h:i A', strtotime($g['check_in_time'])) ?></td>
                    <td><?=  $g['is_late'] ? '<span class="badge badge-red">Late</span>' : '<span class="badge badge-green">On Time</span>' ?></td>
                    <td><?=  $g['email_sent'] ? '✅' : '⏳' ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
