<?php
// checkin_guest.php

session_start();
require_once 'config/db.php';
require_once 'includes/CheckinHandler.php';

if (empty($_SESSION['meeting_id'])) {
    header('Location: checkin.php'); exit;
}

$pdo     = getPDO();
$meeting =  $_SESSION['meeting'];
$error   = '';

// Fetch members for "invited by" dropdown
$stmt =  $pdo->prepare("
    SELECT m.id, m.first_name, m.last_name
    FROM   members m
    JOIN   clubs c ON c.id = m.club_id
    WHERE  c.is_host_club = 1 AND m.is_active = 1
    ORDER  BY m.last_name
");
$stmt->execute();
$members =  $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $data = [
        'first_name'     => trim($_POST['first_name']     ?? ''),
        'last_name'      => trim($_POST['last_name']      ?? ''),
        'email'          => trim($_POST['email']          ?? ''),
        'phone'          => trim($_POST['phone']          ?? ''),
        'organization'   => trim($_POST['organization']   ?? ''),
        'host_member_id' => (int)($_POST['host_member_id']?? 0),
    ];

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
         $error = 'Please fill in all required fields.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
         $error = 'Please enter a valid email address.';
    } else {
         $handler = new CheckinHandler($pdo);
         $result  =  $handler->checkinGuest($_SESSION['meeting_id'],  $data);

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
    <title>Guest Check-In</title>
    <style>
        body { font-family:'Segoe UI',sans-serif; background:#f0f4f8;
               display:flex; justify-content:center; align-items:flex-start;
               min-height:100vh; padding:30px 16px; }
        .card { background:#fff; border-radius:16px; padding:36px 28px;
                max-width:460px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,0.10); }
        h2   { color:#009a44; margin-bottom:20px; }
        label { display:block; font-size:0.88rem; color:#555; margin-bottom:4px; }
        input, select {
            width:100%; padding:11px; border-radius:8px;
            border:1px solid #ccc; font-size:0.97rem; margin-bottom:14px;
        }
        .req  { color:#c0392b; }
        button { width:100%; padding:13px; background:#009a44; color:#fff;
                 border:none; border-radius:8px; font-size:1rem;
                 font-weight:600; cursor:pointer; }
        .error { background:#ffe0e0; color:#c0392b; padding:10px;
                 border-radius:8px; margin-bottom:14px; }
        .back  { color:#009a44; text-decoration:none; font-size:0.9rem;
                 display:block; margin-top:14px; }
        .row   { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    </style>
</head>
<body>
<div class="card">
    <h2>🟢 Guest Check-In</h2>
    <p style="color:#555; margin-bottom:20px;">
        Welcome! Please register to check in for<br>
        <strong><?= htmlspecialchars($meeting['title']) ?></strong>
    </p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <div>
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div>
                <label>Last Name <span class="req">*</span></label>
                <input type="text" name="last_name"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
            </div>
        </div>

        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

        <label>Phone Number</label>
        <input type="tel" name="phone"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

        <label>Organization / Company</label>
        <input type="text" name="organization"
               value="<?= htmlspecialchars($_POST['organization'] ?? '') ?>">

        <label>Invited By (Club Member)</label>
        <select name="host_member_id">
            <option value="0">— Select Member (Optional) —</option>
            <?php foreach ($members as  $m): ?>
                <option value="<?=  $m['id'] ?>"
                    <?= (($_POST['host_member_id'] ?? 0) ==  $m['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['first_name'] . ' ' .  $m['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Check In &amp; Get Certificate</button>
    </form>
    <a href="checkin.php?token=<?=  $_SESSION['meeting_token'] ?>" class="back">← Back</a>
</div>
</body>
</html>
