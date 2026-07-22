<?php
// checkin_member.php

session_start();
require_once 'config/db.php';
require_once 'includes/CheckinHandler.php';

if (empty($_SESSION['meeting_id'])) {
    header('Location: checkin.php'); exit;
}

$pdo     = getPDO();
$meeting =  $_SESSION['meeting'];
$error   = '';

// Fetch active host club members
$stmt =  $pdo->prepare("
    SELECT m.id, m.first_name, m.last_name, m.rotary_id
    FROM   members m
    JOIN   clubs c ON c.id = m.club_id
    WHERE  c.is_host_club = 1 AND m.is_active = 1
    ORDER  BY m.last_name, m.first_name
");
$stmt->execute();
$members =  $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $memberId = (int)($_POST['member_id'] ?? 0);

    if ($memberId < 1) {
         $error = 'Please select your name from the list.';
    } else {
         $handler = new CheckinHandler($pdo);
         $result  =  $handler->checkinMember($_SESSION['meeting_id'],  $memberId);

        if ($result['success']) {
             $_SESSION['checkin_result'] =  $result;
            header('Location: checkin_success.php'); exit;
        } else {
             $error =  $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Check-In</title>
    <style>
        body { font-family:'Segoe UI',sans-serif; background:#f0f4f8;
               display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .card { background:#fff; border-radius:16px; padding:36px 28px;
                max-width:440px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,0.10); }
        h2 { color:#003f87; margin-bottom:20px; }
        select, button {
            width:100%; padding:12px; border-radius:8px;
            border:1px solid #ccc; font-size:1rem; margin-bottom:14px;
        }
        button { background:#003f87; color:#fff; border:none;
                 font-weight:600; cursor:pointer; }
        .error { background:#ffe0e0; color:#c0392b; padding:10px;
                 border-radius:8px; margin-bottom:14px; }
        .back  { color:#003f87; text-decoration:none; font-size:0.9rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>✅ Member Check-In</h2>
    <p style="color:#555; margin-bottom:20px;">
        Select your name to mark attendance for<br>
        <strong><?= htmlspecialchars($meeting['title']) ?></strong>
    </p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <select name="member_id" required>
            <option value="">— Select Your Name —</option>
            <?php foreach ($members as  $m): ?>
                <option value="<?=  $m['id'] ?>">
                    <?= htmlspecialchars($m['last_name'] . ', ' .  $m['first_name']) ?>
                    <?=  $m['rotary_id'] ? '(' .  $m['rotary_id'] . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Check In Now</button>
    </form>
    <a href="checkin.php?token=<?=  $_SESSION['meeting_token'] ?>" class="back">← Back</a>
</div>
</body>
</html>
