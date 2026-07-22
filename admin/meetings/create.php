<?php
// admin/meetings/create.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/QRGenerator.php';

$pageTitle = 'Create Meeting';
$pdo    = getPDO();
$errors = [];
$success = false;
$newMeeting = null;

// Fetch host club
$hostClub =  $pdo->query("SELECT * FROM clubs WHERE is_host_club=1 LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $title       = trim($_POST['title']       ?? '');
     $meetingDate = trim($_POST['meeting_date'] ?? '');
     $startTime   = trim($_POST['start_time']   ?? '');
     $endTime     = trim($_POST['end_time']     ?? '');
     $venue       = trim($_POST['venue']        ?? '');
     $theme       = trim($_POST['theme']        ?? '');
     $expiryHours = (int)($_POST['expiry_hours'] ?? 8);

    if (!$title)        $errors[] = 'Meeting title is required.';
    if (!$meetingDate)  $errors[] = 'Meeting date is required.';
    if (!$startTime)    $errors[] = 'Start time is required.';
    if (!$hostClub)     $errors[] = 'No host club found. Please set up the host club first.';

    if (empty($errors)) {
        // Generate unique QR token
         $token     = bin2hex(random_bytes(16));
         $expiresAt = date('Y-m-d H:i:s',
            strtotime($meetingDate . ' ' .  $startTime) + ($expiryHours * 3600));

         $stmt =  $pdo->prepare("
            INSERT INTO meetings
                (club_id, title, meeting_date, start_time, end_time,
                 venue, theme, qr_token, qr_expires_at, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)
        ");
         $stmt->execute([
             $hostClub['id'],  $title,  $meetingDate,  $startTime,
             $endTime ?: null,  $venue ?: null,  $theme ?: null,
             $token,  $expiresAt,  $_SESSION['admin_id'],
        ]);
         $meetingId = (int)$pdo->lastInsertId();

        // Pre-generate QR code image
         $qrUrl = QRGenerator::getUrl($token);
        QRGenerator::generate($qrUrl,  $token);

         $success    = true;
         $newMeeting =  $pdo->query("SELECT * FROM meetings WHERE id=$meetingId")->fetch();
         $newMeeting['qr_url']      =  $qrUrl;
         $newMeeting['qr_web_path'] = QRGenerator::getWebPath($token);
    }
}

require_once '../includes/layout_top.php';
?>

<?php if ($success &&  $newMeeting): ?>
<!-- ── SUCCESS: Show QR Code ── -->
<div class="alert alert-success">
    ✅ Meeting created successfully! Share the QR code below with attendees.
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">

    <!-- Meeting Details -->
    <div class="card">
        <div class="card-header"><h2>📅 Meeting Details</h2></div>
        <div class="card-body">
            <table style="width:100%; font-size:0.92rem; line-height:2;">
                <tr><td style="color:#888; width:40%">Title</td>
                    <td><strong><?= htmlspecialchars($newMeeting['title']) ?></strong></td></tr>
                <tr><td style="color:#888">Date</td>
                    <td><?= date('l, d F Y', strtotime($newMeeting['meeting_date'])) ?></td></tr>
                <tr><td style="color:#888">Time</td>
                    <td><?= date('h:i A', strtotime($newMeeting['start_time'])) ?></td></tr>
                <tr><td style="color:#888">Venue</td>
                    <td><?= htmlspecialchars($newMeeting['venue'] ?? '—') ?></td></tr>
                <tr><td style="color:#888">Theme</td>
                    <td><?= htmlspecialchars($newMeeting['theme'] ?? '—') ?></td></tr>
                <tr><td style="color:#888">Status</td>
                    <td><span class="badge badge-gold">Scheduled</span></td></tr>
                <tr><td style="color:#888">QR Expires</td>
                    <td><?= date('d M Y, h:i A', strtotime($newMeeting['qr_expires_at'])) ?></td></tr>
            </table>

            <div style="margin-top:18px; padding:12px; background:#f0f4f8;
                        border-radius:8px; word-break:break-all; font-size:0.82rem;">
                <strong>Check-In URL:</strong><br>
                <a href="<?= htmlspecialchars($newMeeting['qr_url']) ?>" target="_blank">
                    <?= htmlspecialchars($newMeeting['qr_url']) ?>
                </a>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="view.php?id=<?=  $newMeeting['id'] ?>" class="btn btn-primary">
                    👁 View Meeting
                </a>
                <a href="index.php" class="btn btn-outline">← All Meetings</a>
                <a href="create.php" class="btn btn-outline">➕ New Meeting</a>
            </div>
        </div>
    </div>

    <!-- QR Code -->
    <div class="card" style="text-align:center;">
        <div class="card-header"><h2>📲 QR Code & Check-In Link</h2></div>
        <div class="card-body">

            <!-- QR Image -->
            <img src="<?= htmlspecialchars($newMeeting['qr_web_path']) ?>"
                alt="Meeting QR Code"
                style="max-width:280px; width:100%; border:4px solid #003f87;
                        border-radius:12px; padding:8px;">

            <p style="color:#666; font-size:0.85rem; margin:14px 0;">
                Attendees scan this code or click the link below to check in.<br>
                Print or display on a screen at the venue.
            </p>

            <!-- Download QR -->
            <a href="<?= htmlspecialchars($newMeeting['qr_web_path']) ?>"
            download="QR_<?=  $newMeeting['qr_token'] ?>.png"
            class="btn btn-gold" style="margin-bottom:18px;">
                ⬇️ Download QR Code
            </a>

            <!-- Check-In Link Box -->
            <div style="background:#f0f4f8; border-radius:10px;
                        padding:16px; margin-top:6px; text-align:left;">
                <p style="font-size:0.82rem; font-weight:600;
                        color:#555; margin-bottom:8px;">
                    🔗 Check-In Link
                </p>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text"
                        id="checkin_url"
                        value="<?= htmlspecialchars($newMeeting['qr_url']) ?>"
                        readonly
                        style="flex:1; font-size:0.82rem; padding:9px 12px;
                                border:1.5px solid #dee2e6; border-radius:8px;
                                background:#fff; color:#333; cursor:text;">
                    <button onclick="copyCheckinUrl()"
                            id="copy_btn"
                            class="btn btn-primary btn-sm"
                            style="white-space:nowrap;">
                        📋 Copy
                    </button>
                </div>
                <p style="font-size:0.78rem; color:#999; margin-top:8px;">
                    Share this link via WhatsApp, email or SMS so attendees
                    can check in directly from their phones.
                </p>
            </div>

            <!-- Open Link Button -->
            <a href="<?= htmlspecialchars($newMeeting['qr_url']) ?>"
            target="_blank"
            class="btn btn-green"
            style="width:100%; margin-top:14px; justify-content:center;">
                🌐 Open Check-In Page
            </a>

        </div>
    </div>

    <script>
    function copyCheckinUrl() {
        const input = document.getElementById('checkin_url');
        const btn   = document.getElementById('copy_btn');
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            btn.textContent = '✅ Copied!';
            btn.style.background = '#009a44';
            setTimeout(() => {
                btn.textContent   = '📋 Copy';
                btn.style.background = '';
            }, 2500);
        }).catch(() => {
            // Fallback for older browsers
            document.execCommand('copy');
            btn.textContent = '✅ Copied!';
            setTimeout(() => { btn.textContent = '📋 Copy'; }, 2500);
        });
    }
    </script>

</div>

<?php else: ?>
<!-- ── CREATE FORM ── -->

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as  $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>➕ New Meeting</h2>
        <a href="index.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">

                <div class="form-group full">
                    <label>Meeting Title <span class="req">*</span></label>
                    <input type="text" name="title"
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                           placeholder="e.g. Weekly Club Meeting" required>
                </div>

                <div class="form-group">
                    <label>Meeting Date <span class="req">*</span></label>
                    <input type="date" name="meeting_date"
                           value="<?= htmlspecialchars($_POST['meeting_date'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Start Time <span class="req">*</span></label>
                    <input type="time" name="start_time"
                           value="<?= htmlspecialchars($_POST['start_time'] ?? '18:30') ?>" required>
                </div>

                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time"
                           value="<?= htmlspecialchars($_POST['end_time'] ?? '21:00') ?>">
                </div>

                <div class="form-group">
                    <label>QR Code Valid For (hours)</label>
                    <select name="expiry_hours">
                        <?php foreach ([4,6,8,12,24,48] as  $h): ?>
                            <option value="<?=  $h ?>"
                                <?= (($_POST['expiry_hours'] ?? 5) ==  $h) ? 'selected' : '' ?>>
                                <?=  $h ?> hours
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Venue</label>
                    <input type="text" name="venue"
                           value="<?= htmlspecialchars($_POST['venue'] ?? '') ?>"
                           placeholder="e.g. Taidy's Tavern, Upperhill">
                </div>

                <div class="form-group full">
                    <label>Meeting Theme / Topic</label>
                    <textarea name="theme"
                              placeholder="e.g. Parenting in the 21st Century"
                    ><?= htmlspecialchars($_POST['theme'] ?? '') ?></textarea>
                </div>

            </div><!-- /form-grid -->

            <div style="margin-top:24px; display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary">
                    ✅ Create Meeting &amp; Generate QR
                </button>
                <a href="index.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/layout_bottom.php'; ?>
