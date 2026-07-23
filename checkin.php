<?php
// checkin.php  ← QR code URL: https://yourdomain.com/checkin.php?token=XXXX

require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');

$logoPath    = 'assets/images/logowhite.png';
$logoExists  = file_exists(__DIR__ . '/assets/images/logo.png');

// ── Reusable styled error page function ─────────────────────────
function showError(string $title, string $message, string $icon = '⚠️',
                   bool $logoExists = false, string $logoPath = ''): void
{
    $year = date('Y');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> — Rotary Check-In</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #003f87 0%, #0062cc 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .card {
                background: #fff;
                border-radius: 20px;
                max-width: 420px;
                width: 100%;
                box-shadow: 0 24px 64px rgba(0,0,0,0.22);
                overflow: hidden;
                text-align: center;
            }
            .card-header {
                background: #003f87;
                padding: 28px 28px 22px;
            }
            .card-header img {
                max-width: 110px;
                max-height: 70px;
                object-fit: contain;
                display: block;
                margin: 0 auto 10px;
                filter: drop-shadow(0 2px 6px rgba(0,0,0,0.2));
            }
            .card-header .club-label {
                color: rgba(255,255,255,0.65);
                font-size: 0.8rem;
                letter-spacing: 0.8px;
                text-transform: uppercase;
            }
            .card-body {
                padding: 36px 32px 28px;
            }
            .error-icon {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: #fff3cd;
                border: 3px solid #f7a800;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2.2rem;
                margin: 0 auto 22px;
                animation: popIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275) both;
            }
            @keyframes popIn {
                0%   { transform: scale(0); opacity: 0; }
                100% { transform: scale(1); opacity: 1; }
            }
            .error-title {
                font-size: 1.3rem;
                font-weight: 800;
                color: #003f87;
                margin-bottom: 12px;
            }
            .error-message {
                color: #666;
                font-size: 0.95rem;
                line-height: 1.7;
                margin-bottom: 24px;
            }
            .info-box {
                background: #f0f4f8;
                border-radius: 10px;
                padding: 14px 18px;
                font-size: 0.85rem;
                color: #555;
                line-height: 1.7;
                text-align: left;
                margin-bottom: 0;
            }
            .info-box strong { color: #003f87; }
            .card-footer {
                background: #f8fafc;
                border-top: 1px solid #e9ecef;
                padding: 13px 28px;
                font-size: 0.75rem;
                color: #aaa;
            }
            .card-footer strong { color: #003f87; }
        </style>
    </head>
    <body>
    <div class="card">

        <div class="card-header">
            <?php if ($logoExists): ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Club Logo">
            <?php endif; ?>
            <div class="club-label">Rotary Club Attendance</div>
        </div>

        <div class="card-body">
            <div class="error-icon"><?= $icon ?></div>
            <div class="error-title"><?= htmlspecialchars($title) ?></div>
            <p class="error-message"><?= $message ?></p>

            <div class="info-box">
                💡 <strong>Need help?</strong><br>
                Please contact the club secretary or the
                person who shared this QR code with you.
            </div>
        </div>

        <div class="card-footer">
            &copy; <?= $year ?> <strong>Rotary Club</strong>
            &nbsp;·&nbsp; Service Above Self
        </div>

    </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Validate token ───────────────────────────────────────────────
if (empty($token)) {
    showError(
        title      : 'Invalid QR Code',
        message    : 'No check-in token was found in this link.<br>
                      Please scan the QR code again or ask the
                      secretary for a valid link.',
        icon       : '🔗',
        logoExists : $logoExists,
        logoPath   : $logoPath
    );
}

$pdo = getPDO();

// Resolve meeting from QR token
$stmt = $pdo->prepare("
    SELECT m.*, c.club_name
    FROM   meetings m
    JOIN   clubs c ON c.id = m.club_id
    WHERE  m.qr_token = ?
    LIMIT  1
");
$stmt->execute([$token]);
$meeting = $stmt->fetch();

if (!$meeting) {
    showError(
        title      : 'QR Code Not Recognised',
        message    : 'This QR code is not linked to any meeting.<br>
                      It may have been deleted or is no longer valid.',
        icon       : '🔍',
        logoExists : $logoExists,
        logoPath   : $logoPath
    );
}

// Check meeting status
if ($meeting['status'] !== 'Open') {
    $statusMessages = [
        'Scheduled' => [
            'title'   => 'Check-In Not Open Yet',
            'message' => 'Check-in for <strong>'
                         . htmlspecialchars($meeting['title'])
                         . '</strong> has not started yet.<br><br>'
                         . 'Please wait for the meeting to begin,
                            then scan the QR code again.',
            'icon'    => '🕐',
        ],
        'Closed'    => [
            'title'   => 'Check-In Closed',
            'message' => 'Check-in for <strong>'
                         . htmlspecialchars($meeting['title'])
                         . '</strong> has already closed.<br><br>'
                         . 'If you believe this is an error, please
                            speak to the club secretary.',
            'icon'    => '🔒',
        ],
        'Cancelled' => [
            'title'   => 'Meeting Cancelled',
            'message' => '<strong>'
                         . htmlspecialchars($meeting['title'])
                         . '</strong> has been cancelled.<br><br>'
                         . 'Please check with the club secretary
                            for further information.',
            'icon'    => '❌',
        ],
    ];

    $info = $statusMessages[$meeting['status']] ?? [
        'title'   => 'Check-In Unavailable',
        'message' => 'Check-in is not currently available for this meeting.<br>
                      Current status: <strong>'
                     . htmlspecialchars($meeting['status']) . '</strong>',
        'icon'    => '⚠️',
    ];

    showError(
        title      : $info['title'],
        message    : $info['message'],
        icon       : $info['icon'],
        logoExists : $logoExists,
        logoPath   : $logoPath
    );
}

// Check QR expiry
if (!empty($meeting['qr_expires_at'])
    && strtotime($meeting['qr_expires_at']) < time()) {
    showError(
        title      : 'QR Code Expired',
        message    : 'This QR code expired on <strong>'
                     . date('d M Y \a\t h:i A',
                         strtotime($meeting['qr_expires_at']))
                     . '</strong>.<br><br>'
                     . 'Please ask the club secretary to generate
                        a new QR code.',
        icon       : '⏰',
        logoExists : $logoExists,
        logoPath   : $logoPath
    );
}

// Store token in session for sub-pages
session_start();
$_SESSION['meeting_id']    = $meeting['id'];
$_SESSION['meeting_token'] = $token;
$_SESSION['meeting']       = $meeting;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Club Check-In</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            display: flex; justify-content: center;
            align-items: center; min-height: 100vh;
        }
        .card {
            background: #fff; border-radius: 16px;
            padding: 40px 32px; max-width: 420px;
            width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            text-align: center;
        }
        .logo { color: #003f87; font-size: 2rem; font-weight: 800; margin-bottom: 4px; }
        .subtitle { color: #666; font-size: 0.95rem; margin-bottom: 24px; }
        .meeting-info {
            background: #f7f9fc; border-radius: 10px;
            padding: 14px; margin-bottom: 28px; text-align: left;
        }
        .meeting-info h3 { color: #003f87; margin-bottom: 6px; }
        .meeting-info p  { color: #555; font-size: 0.9rem; line-height: 1.6; }
        .btn {
            display: block; width: 100%; padding: 14px;
            border: none; border-radius: 10px; font-size: 1rem;
            font-weight: 600; cursor: pointer; margin-bottom: 12px;
            text-decoration: none; transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.88; }
        .btn-member   { background: #003f87; color: #fff; }
        .btn-visitor  { background: #f7a800; color: #fff; }
        .btn-guest    { background: #009a44; color: #fff; }
    </style>
</head>
<body>
<div class="card">
    <?php if ($logoExists): ?>
        <img src="<?= $logoPath ?>"
             alt="Club Logo"
             style="max-width:200px; max-height:120px;
                    object-fit:contain; margin-bottom:10px;
                    display:block; margin-left:auto; margin-right:auto;">
    <?php endif; ?>

    <div class="subtitle">Club Attendance Check-In</div>

    <div class="meeting-info">
        <h3><?= htmlspecialchars($meeting['title']) ?></h3>
        <p>
            📅 <?= date('l, d F Y', strtotime($meeting['meeting_date'])) ?><br>
            🕐 <?= date('h:i A',    strtotime($meeting['start_time']))   ?><br>
            📍 <?= htmlspecialchars($meeting['venue'] ?? 'TBD')          ?><br>
            🏛️ <?= htmlspecialchars($meeting['club_name'])               ?>
        </p>
    </div>

    <p style="margin-bottom:16px; color:#444; font-size:0.95rem;">
        How are you attending today?
    </p>

    <a href="checkin_member.php"  class="btn btn-member">
        ✅ I am a Club Member
    </a>
    <a href="checkin_visitor.php" class="btn btn-visitor">
        🔵 I am a Visiting Rotarian
    </a>
    <a href="checkin_guest.php"   class="btn btn-guest">
        🟢 I am a Guest
    </a>
</div>
</body>
</html>
