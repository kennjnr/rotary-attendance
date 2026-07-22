<?php
// admin/reports/certificates.php

require_once '../includes/auth.php';
require_once '../includes/role_guard.php';
require_once '../../config/db.php';

requireRole(['Super Admin', 'Secretary', 'President', 'Attendance Officer']);

$pageTitle = 'Certificate Manager';
$pdo       = getPDO();

// Filters
$search    = trim($_GET['q']          ?? '');
$emailSent =  $_GET['email_sent']      ?? '';
$type      =  $_GET['type']            ?? '';
$fromDate  =  $_GET['from_date']       ?? date('Y-01-01');
$toDate    =  $_GET['to_date']         ?? date('Y-12-31');
$meetingId = (int)($_GET['meeting_id'] ?? 0);

$where  = ['m.meeting_date BETWEEN ? AND ?'];
$params = [$fromDate,  $toDate];

if ($search) {
     $where[]  = '(c.recipient_name LIKE ? OR c.recipient_email LIKE ?
                  OR c.certificate_no LIKE ?)';
     $params[] = "%$search%";
     $params[] = "%$search%";
     $params[] = "%$search%";
}
if ($emailSent !== '') {
     $where[]  = 'c.email_sent = ?';
     $params[] = (int)$emailSent;
}
if ($type) {
     $where[]  = 'c.attendee_type = ?';
     $params[] =  $type;
}
if ($meetingId) {
     $where[]  = 'c.meeting_id = ?';
     $params[] =  $meetingId;
}

$whereStr = implode(' AND ',  $where);

$stmt =  $pdo->prepare("
    SELECT c.*,
           m.title        AS meeting_title,
           m.meeting_date,
           cl.club_name
    FROM   certificates c
    JOIN   meetings m  ON m.id  = c.meeting_id
    JOIN   clubs    cl ON cl.id = m.club_id
    WHERE   $whereStr
    ORDER  BY c.issued_at DESC
");
$stmt->execute($params);
$certs =  $stmt->fetchAll();

// Stats
$stats =  $pdo->query("
    SELECT
        COUNT(*)                    AS total,
        SUM(email_sent = 1)         AS sent,
        SUM(email_sent = 0)         AS pending,
        SUM(attendee_type='Member') AS members,
        SUM(attendee_type='Visiting Rotarian') AS visitors,
        SUM(attendee_type='Guest')  AS guests
    FROM certificates
")->fetch();

// Meetings for filter dropdown
$meetings =  $pdo->query("
    SELECT id, title, meeting_date
    FROM   meetings
    ORDER  BY meeting_date DESC
")->fetchAll();

// Handle resend email
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['resend_cert'])
    && canManageMembers()) {

    require_once '../../includes/Mailer.php';

     $resendId = (int)$_POST['cert_id'];
     $certRow  =  $pdo->prepare("
        SELECT c.*,
               m.title AS meeting_title,
               m.meeting_date, m.start_time, m.venue,
               cl.club_name
        FROM   certificates c
        JOIN   meetings m  ON m.id  = c.meeting_id
        JOIN   clubs    cl ON cl.id = m.club_id
        WHERE  c.id = ?
    ");
     $certRow->execute([$resendId]);
     $certRow =  $certRow->fetch();

    if ($certRow && file_exists($certRow['file_path'])) {
         $mailer = new Mailer();
         $sent   =  $mailer->sendCertificate(
             $certRow['recipient_email'],
             $certRow['recipient_name'],
            [
                'title'        =>  $certRow['meeting_title'],
                'meeting_date' =>  $certRow['meeting_date'],
                'start_time'   =>  $certRow['start_time'],
                'venue'        =>  $certRow['venue'],
                'club_name'    =>  $certRow['club_name'],
            ],
             $certRow['file_path']
        );

        if ($sent['sent']) {
             $pdo->prepare("
                UPDATE certificates
                SET email_sent=1, email_sent_at=NOW(), email_error=NULL
                WHERE id=?
            ")->execute([$resendId]);
             $flashSuccess = 'Certificate resent successfully.';
        } else {
             $pdo->prepare("
                UPDATE certificates SET email_error=? WHERE id=?
            ")->execute([$sent['error'],  $resendId]);
             $flashError = 'Failed to resend: ' .  $sent['error'];
        }
    } else {
         $flashError = 'Certificate file not found on server.';
    }

    header('Location: certificates.php?' . http_build_query([
        'q'          =>  $search,
        'email_sent' =>  $emailSent,
        'type'       =>  $type,
        'from_date'  =>  $fromDate,
        'to_date'    =>  $toDate,
    ]));
    exit;
}

require_once '../includes/layout_top.php';
?>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid"
     style="grid-template-columns:repeat(6,1fr); margin-bottom:24px;">
    <div class="stat-card">
        <div class="val"><?= number_format($stats['total']) ?></div>
        <div class="lbl">Total Issued</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= number_format($stats['sent'] ?? 0) ?></div>

        <div class="lbl">Emails Sent</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($stats['pending'] ?? 0) ?></div>
        <div class="lbl">Pending</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= number_format($stats['members'] ?? 0) ?></div>
        <div class="lbl">Members</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= number_format($stats['visitors'] ?? 0) ?></div>
        <div class="lbl">Visitors</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= number_format($stats['guests'] ?? 0) ?></div>
        <div class="lbl">Guests</div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 22px;">
        <form method="GET"
              style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label>Search</label>
                <input type="text" name="q"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Name, email, cert no..."
                       style="width:200px;">
            </div>
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
                <label>Type</label>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="Member"
                        <?= ($type==='Member') ? 'selected':'' ?>>
                        Member
                    </option>
                    <option value="Visiting Rotarian"
                        <?= ($type==='Visiting Rotarian') ? 'selected':'' ?>>
                        Visiting Rotarian
                    </option>
                    <option value="Guest"
                        <?= ($type==='Guest') ? 'selected':'' ?>>
                        Guest
                    </option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Email Status</label>
                <select name="email_sent">
                    <option value="">All</option>
                    <option value="1"
                        <?= ($emailSent==='1') ? 'selected':'' ?>>
                        Sent
                    </option>
                    <option value="0"
                        <?= ($emailSent==='0') ? 'selected':'' ?>>
                        Pending
                    </option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Meeting</label>
                <select name="meeting_id">
                    <option value="0">All Meetings</option>
                    <?php foreach ($meetings as  $mtg): ?>
                        <option value="<?=  $mtg['id'] ?>"
                            <?= ($meetingId===$mtg['id']) ? 'selected':'' ?>>
                            <?= htmlspecialchars(
                                date('d M Y',
                                    strtotime($mtg['meeting_date']))
                                . ' — ' .  $mtg['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="certificates.php" class="btn btn-outline">✕ Reset</a>
        </form>
    </div>
</div>

<!-- Certificate Table -->
<div class="card">
    <div class="card-header">
        <h2>📜 Certificates (<?= count($certs) ?>)</h2>
        <span style="font-size:0.82rem; color:#888;">
            <?= date('d M Y', strtotime($fromDate)) ?> —
            <?= date('d M Y', strtotime($toDate)) ?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cert No</th>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Meeting</th>
                    <th>Issued</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($certs)): ?>
                <tr>
                    <td colspan="7"
                        style="text-align:center; color:#999; padding:30px;">
                        No certificates found for the selected filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php
                 $typeBadge = [
                    'Member'            => 'badge-blue',
                    'Visiting Rotarian' => 'badge-gold',
                    'Guest'             => 'badge-green',
                ];
                foreach ($certs as  $c):
                     $fileExists = !empty($c['file_path'])
                                  && file_exists($c['file_path']);
                ?>
                <tr>
                    <td>
                        <span style="font-family:monospace; font-size:0.82rem;
                                     color:#003f87; font-weight:600;">
                            <?= htmlspecialchars($c['certificate_no']) ?>
                        </span>
                    </td>
                    <td>
                        <strong>
                            <?= htmlspecialchars($c['recipient_name']) ?>
                        </strong><br>
                        <small style="color:#888;">
                            <?= htmlspecialchars($c['recipient_email']) ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge <?=  $typeBadge[$c['attendee_type']]
                            ?? 'badge-gray' ?>">
                            <?= htmlspecialchars($c['attendee_type']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:0.88rem;">
                            <?= htmlspecialchars($c['meeting_title']) ?>
                        </span><br>
                        <small style="color:#888;">
                            <?= date('d M Y',
                                strtotime($c['meeting_date'])) ?>
                        </small>
                    </td>
                    <td style="font-size:0.85rem; color:#555;">
                        <?= date('d M Y', strtotime($c['issued_at'])) ?><br>
                        <small style="color:#aaa;">
                            <?= date('h:i A', strtotime($c['issued_at'])) ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($c['email_sent']): ?>
                            <span class="badge badge-green">✓ Sent</span><br>
                            <small style="color:#aaa; font-size:0.75rem;">
                                <?= date('d M Y',
                                    strtotime($c['email_sent_at'])) ?>
                            </small>
                        <?php else: ?>
                            <span class="badge badge-gold">⏳ Pending</span>
                            <?php if (!empty($c['email_error'])): ?>
                                <br>
                                <small style="color:#c0392b; font-size:0.75rem;"
                                       title="<?= htmlspecialchars(
                                            $c['email_error']) ?>">
                                    ⚠️ Error
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions" style="flex-wrap:wrap; gap:4px;">

                            <!-- View in browser -->
                            <?php if ($fileExists): ?>
                                <a href="<?= APP_URL . '/download_certificate.php?no=' . urlencode($c['certificate_no']) . '&view=1' ?>"
                                target="_blank"
                                class="btn btn-outline btn-sm"
                                title="View PDF">
                                    👁
                                </a>

                                <!-- Download -->
                                <a href="<?= APP_URL . '/download_certificate.php?no=' . urlencode($c['certificate_no']) ?>"
                                class="btn btn-primary btn-sm"
                                title="Download PDF">
                                    ⬇️
                                </a>
                            <?php else: ?>
                                <span style="color:#ccc; font-size:0.8rem;">
                                    No file
                                </span>
                            <?php endif; ?>

                            <!-- Resend email -->
                            <?php if (canManageMembers()): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="cert_id"
                                           value="<?=  $c['id'] ?>">
                                    <button name="resend_cert"
                                            class="btn btn-gold btn-sm"
                                            title="Resend email"
                                            onclick="return confirm(
                                                'Resend certificate to <?= htmlspecialchars(
                                                     $c['recipient_email']) ?>?')">
                                        📧
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Public verify link -->
                            <a href="<?= APP_URL . '/certificate.php?no=' . urlencode($c['certificate_no']) ?>"
                                target="_blank"
                                class="btn btn-outline btn-sm"
                                title="Public verify link">
                                    🔗
                                </a>


                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
