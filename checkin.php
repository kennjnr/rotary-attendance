<?php
// checkin.php  ← QR code URL: https://yourdomain.com/checkin.php?token=XXXX

require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    die('Invalid QR code.');
}

$pdo = getPDO();

// Resolve meeting from QR token
$stmt =  $pdo->prepare("
    SELECT m.*, c.club_name
    FROM   meetings m
    JOIN   clubs c ON c.id = m.club_id
    WHERE  m.qr_token = ?
    LIMIT  1
");
$stmt->execute([$token]);
$meeting =  $stmt->fetch();

if (!$meeting) {
    die('This QR code is not linked to any meeting.');
}

// Check meeting status
if ($meeting['status'] !== 'Open') {
    die('Check-in is not currently active for this meeting. Status: ' .  $meeting['status']);
}

// Check QR expiry
if (!empty($meeting['qr_expires_at']) && strtotime($meeting['qr_expires_at']) < time()) {
    die('This QR code has expired.');
}

// Store token in session for sub-pages
session_start();
$_SESSION['meeting_id']    =  $meeting['id'];
$_SESSION['meeting_token'] =  $token;
$_SESSION['meeting']       =  $meeting;
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
    <?php
    $logoPath = 'assets/images/logo.png';
    if (file_exists(__DIR__ . '/assets/images/logo.png')): ?>
        <img src="<?=  $logoPath ?>"
            alt="Club Logo"
            style="max-width:200px; max-height:120px;
                    object-fit:contain; margin-bottom:10px;
                    display:block; margin-left:auto; margin-right:auto;">
    <?php endif; ?>
    <!-- <div class="logo">&#9900; Rotary</div> -->
    <div class="subtitle">Club Attendance Check-In</div>


    <div class="meeting-info">
        <h3><?= htmlspecialchars($meeting['title']) ?></h3>
        <p>
            📅 <?= date('l, d F Y', strtotime($meeting['meeting_date'])) ?><br>
            🕐 <?= date('h:i A', strtotime($meeting['start_time'])) ?><br>
            📍 <?= htmlspecialchars($meeting['venue'] ?? 'TBD') ?><br>
            🏛️ <?= htmlspecialchars($meeting['club_name']) ?>
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
