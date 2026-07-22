<?php
// admin/meetings/edit.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/QRGenerator.php';

$pageTitle = 'Edit Meeting';
$pdo       = getPDO();
$id        = (int)($_GET['id'] ?? 0);
$errors    = [];
$success   = false;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch meeting
$meeting =  $pdo->query("
    SELECT m.*, c.club_name
    FROM   meetings m
    JOIN   clubs c ON c.id = m.club_id
    WHERE  m.id =  $id
")->fetch();

if (!$meeting) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

     $title       = trim($_POST['title']        ?? '');
     $meetingDate = trim($_POST['meeting_date']  ?? '');
     $startTime   = trim($_POST['start_time']    ?? '');
     $endTime     = trim($_POST['end_time']      ?? '');
     $venue       = trim($_POST['venue']         ?? '');
     $theme       = trim($_POST['theme']         ?? '');
     $status      = trim($_POST['status']        ?? '');
     $expiryHours = (int)($_POST['expiry_hours'] ?? 8);

     $allowedStatuses = ['Scheduled', 'Open', 'Closed', 'Cancelled'];

    // Validation
    if (!$title)                           $errors[] = 'Meeting title is required.';
    if (!$meetingDate)                     $errors[] = 'Meeting date is required.';
    if (!$startTime)                       $errors[] = 'Start time is required.';
    if (!in_array($status,  $allowedStatuses))  $errors[] = 'Invalid status selected.';

    if (empty($errors)) {

        // Recalculate QR expiry based on new date/time and expiry hours
         $expiresAt = date('Y-m-d H:i:s',
            strtotime($meetingDate . ' ' .  $startTime) + ($expiryHours * 3600));

         $pdo->prepare("
            UPDATE meetings
            SET title         = ?,
                meeting_date  = ?,
                start_time    = ?,
                end_time      = ?,
                venue         = ?,
                theme         = ?,
                status        = ?,
                qr_expires_at = ?,
                updated_at    = NOW()
            WHERE id = ?
        ")->execute([
             $title,
             $meetingDate,
             $startTime,
             $endTime      ?: null,
             $venue        ?: null,
             $theme        ?: null,
             $status,
             $expiresAt,
             $id,
        ]);

         $success = true;

        // Refresh meeting data
         $meeting =  $pdo->query("
            SELECT m.*, c.club_name
            FROM   meetings m
            JOIN   clubs c ON c.id = m.club_id
            WHERE  m.id =  $id
        ")->fetch();
    }
}

// Compute QR info
$qrUrl     = QRGenerator::getUrl($meeting['qr_token']);
$qrWebPath = QRGenerator::getWebPath($meeting['qr_token']);
QRGenerator::generate($qrUrl,  $meeting['qr_token']);

// Expiry hours reverse-compute for dropdown default
$expiryHoursDefault = 8;
if (!empty($meeting['qr_expires_at']) && !empty($meeting['start_time'])) {
     $diff = (strtotime($meeting['qr_expires_at'])
           - strtotime($meeting['meeting_date'] . ' ' .  $meeting['start_time'])) / 3600;
     $expiryHoursDefault = max(1, (int)round($diff));
}

require_once '../includes/layout_top.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success">✅ Meeting updated successfully!</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as  $e): ?>
            ⚠️ <?= htmlspecialchars($e) ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Page Actions -->
<div class="page-actions">
    <a href="index.php"              class="btn btn-outline">← All Meetings</a>
    <a href="view.php?id=<?=  $id ?>" class="btn btn-outline">👁 View Meeting</a>
</div>

<!-- Two column layout: form + QR -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; align-items:start;">

    <!-- ── Edit Form ── -->
    <div class="card">
        <div class="card-header">
            <h2>✏️ Edit Meeting</h2>
            <?php
             $badgeMap = [
                'Scheduled' => 'badge-gold',
                'Open'      => 'badge-green',
                'Closed'    => 'badge-gray',
                'Cancelled' => 'badge-red',
            ];
             $badge =  $badgeMap[$meeting['status']] ?? 'badge-gray';
            ?>
            <span class="badge <?=  $badge ?>"><?=  $meeting['status'] ?></span>
        </div>
        <div class="card-body">
            <form method="POST">

                <div class="form-grid">

                    <!-- Title -->
                    <div class="form-group full">
                        <label>Meeting Title <span class="req">*</span></label>
                        <input type="text"
                               name="title"
                               value="<?= htmlspecialchars($meeting['title']) ?>"
                               placeholder="e.g. Weekly Luncheon Meeting"
                               required>
                    </div>

                    <!-- Date -->
                    <div class="form-group">
                        <label>Meeting Date <span class="req">*</span></label>
                        <input type="date"
                               name="meeting_date"
                               value="<?= htmlspecialchars($meeting['meeting_date']) ?>"
                               required>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label>Status <span class="req">*</span></label>
                        <select name="status">
                            <?php foreach (['Scheduled','Open','Closed','Cancelled'] as  $s): ?>
                                <option value="<?=  $s ?>"
                                    <?= ($meeting['status'] ===  $s) ? 'selected' : '' ?>>
                                    <?=  $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Start Time -->
                    <div class="form-group">
                        <label>Start Time <span class="req">*</span></label>
                        <input type="time"
                               name="start_time"
                               value="<?= htmlspecialchars($meeting['start_time']) ?>"
                               required>
                    </div>

                    <!-- End Time -->
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time"
                               name="end_time"
                               value="<?= htmlspecialchars($meeting['end_time'] ?? '') ?>">
                    </div>

                    <!-- QR Expiry -->
                    <div class="form-group">
                        <label>QR Code Valid For (hours)</label>
                        <select name="expiry_hours">
                            <?php foreach ([4, 6, 8, 12, 24, 48] as  $h): ?>
                                <option value="<?=  $h ?>"
                                    <?= ($expiryHoursDefault ===  $h) ? 'selected' : '' ?>>
                                    <?=  $h ?> hours
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Venue -->
                    <div class="form-group full">
                        <label>Venue</label>
                        <input type="text"
                               name="venue"
                               value="<?= htmlspecialchars($meeting['venue'] ?? '') ?>"
                               placeholder="e.g. Eko Hotel, Victoria Island">
                    </div>

                    <!-- Theme -->
                    <div class="form-group full">
                        <label>Meeting Theme / Topic</label>
                        <textarea name="theme"
                                  placeholder="e.g. Vocational Service in the 21st Century"
                        ><?= htmlspecialchars($meeting['theme'] ?? '') ?></textarea>
                    </div>

                </div><!-- /form-grid -->

                <!-- Info note -->
                <div style="background:#fff3cd; border-left:4px solid #f7a800;
                            padding:12px 16px; border-radius:8px;
                            margin:20px 0; font-size:0.85rem; color:#856404;">
                    ℹ️ The QR code token stays the same. Only the expiry time
                    is recalculated when you save. Existing check-ins are not affected.
                </div>

                <!-- Buttons -->
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Save Changes
                    </button>
                    <a href="view.php?id=<?=  $id ?>" class="btn btn-outline">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>

    <!-- ── QR Code + Link Panel ── -->
    <div class="card" style="text-align:center;">
        <div class="card-header"><h2>📲 QR Code & Check-In Link</h2></div>
        <div class="card-body">

            <!-- QR Image -->
            <img src="<?= htmlspecialchars($qrWebPath) ?>"
                 alt="QR Code"
                 style="max-width:200px; width:100%;
                        border:3px solid #003f87;
                        border-radius:10px; padding:6px;">

            <p style="font-size:0.78rem; color:#888; margin:10px 0;">
                This QR code does not change when you edit the meeting.
            </p>

            <!-- Download -->
            <a href="<?= htmlspecialchars($qrWebPath) ?>"
               download="QR_Meeting_<?=  $id ?>.png"
               class="btn btn-gold btn-sm">
                ⬇️ Download QR
            </a>

            <!-- Check-In Link -->
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

                <a href="<?= htmlspecialchars($qrUrl) ?>"
                   target="_blank"
                   class="btn btn-green btn-sm"
                   style="justify-content:center;">
                    🌐 Open Check-In Page
                </a>

                <a href="https://wa.me/?text=<?= urlencode(
                        'Please use this link to check in for our Rotary meeting: '
                        .  $qrUrl) ?>"
                   target="_blank"
                   class="btn btn-sm"
                   style="background:#25D366; color:#fff; justify-content:center;">
                    💬 Share via WhatsApp
                </a>

                <a href="mailto:?subject=<?= urlencode(
                        'Rotary Meeting Check-In: ' .  $meeting['title']) ?>&body=<?= urlencode(
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

            <!-- Quick Status Toggle -->
            <?php if ($meeting['status'] === 'Scheduled'): ?>
                <form method="POST">
                    <input type="hidden" name="title"
                           value="<?= htmlspecialchars($meeting['title']) ?>">
                    <input type="hidden" name="meeting_date"
                           value="<?=  $meeting['meeting_date'] ?>">
                    <input type="hidden" name="start_time"
                           value="<?=  $meeting['start_time'] ?>">
                    <input type="hidden" name="end_time"
                           value="<?=  $meeting['end_time'] ?? '' ?>">
                    <input type="hidden" name="venue"
                           value="<?= htmlspecialchars($meeting['venue'] ?? '') ?>">
                    <input type="hidden" name="theme"
                           value="<?= htmlspecialchars($meeting['theme'] ?? '') ?>">
                    <input type="hidden" name="expiry_hours"
                           value="<?=  $expiryHoursDefault ?>">
                    <input type="hidden" name="status" value="Open">
                    <button type="submit"
                            class="btn btn-green btn-sm"
                            style="width:100%;">
                        ▶ Open Check-In Now
                    </button>
                </form>

            <?php elseif ($meeting['status'] === 'Open'): ?>
                <form method="POST">
                    <input type="hidden" name="title"
                           value="<?= htmlspecialchars($meeting['title']) ?>">
                    <input type="hidden" name="meeting_date"
                           value="<?=  $meeting['meeting_date'] ?>">
                    <input type="hidden" name="start_time"
                           value="<?=  $meeting['start_time'] ?>">
                    <input type="hidden" name="end_time"
                           value="<?=  $meeting['end_time'] ?? '' ?>">
                    <input type="hidden" name="venue"
                           value="<?= htmlspecialchars($meeting['venue'] ?? '') ?>">
                    <input type="hidden" name="theme"
                           value="<?= htmlspecialchars($meeting['theme'] ?? '') ?>">
                    <input type="hidden" name="expiry_hours"
                           value="<?=  $expiryHoursDefault ?>">
                    <input type="hidden" name="status" value="Closed">
                    <button type="submit"
                            class="btn btn-red btn-sm"
                            style="width:100%;">
                        ⏹ Close Check-In
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>

</div><!-- /grid -->

<!-- Attendance summary (read-only reminder) -->
<?php
$summary =  $pdo->query("
    SELECT * FROM meeting_summary WHERE meeting_id =  $id
")->fetch();
?>
<?php if ($summary): ?>
<div class="card mt-4">
    <div class="card-header">
        <h2>📊 Current Attendance Summary</h2>
        <a href="view.php?id=<?=  $id ?>" class="btn btn-outline btn-sm">
            👁 Full Detail
        </a>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns:repeat(5,1fr); margin:0;">
            <div class="stat-card">
                <div class="val"><?=  $summary['total_members_present'] ?></div>
                <div class="lbl">Members</div>
            </div>
            <div class="stat-card red">
                <div class="val"><?=  $summary['total_late_members'] ?></div>
                <div class="lbl">Late</div>
            </div>
            <div class="stat-card gold">
                <div class="val"><?=  $summary['total_visiting_rotarians'] ?></div>
                <div class="lbl">Visitors</div>
            </div>
            <div class="stat-card green">
                <div class="val"><?=  $summary['total_guests'] ?></div>
                <div class="lbl">Guests</div>
            </div>
            <div class="stat-card">
                <div class="val"><?=  $summary['total_attendees'] ?></div>
                <div class="lbl">Total</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

<?php require_once '../includes/layout_bottom.php'; ?>
