<?php
// checkin_success.php

session_start();
require_once __DIR__ . '/config/db.php';

$result =  $_SESSION['checkin_result'] ?? null;
if (!$result) { header('Location: checkin.php'); exit; }
unset($_SESSION['checkin_result']);

$colors = [
    'Member'            => '#003f87',
    'Visiting Rotarian' => '#f7a800',
    'Guest'             => '#009a44',
];
$color =  $colors[$result['attendee_type']] ?? '#003f87';

$logoFile = __DIR__ . '/assets/images/logo.png';
$logoUrl  = APP_URL . '/assets/images/logo.png';

// Fetch host club name for footer
$hostClub = getPDO()->query("
    SELECT club_name FROM clubs WHERE is_host_club = 1 LIMIT 1
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Check-In Successful</title>
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
            max-width: 440px;
            width: 100%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.22);
            overflow: hidden;
            text-align: center;
        }

        /* ── Header band ── */
        .card-header {
            background: <?=  $color ?>;
            padding: 28px 28px 22px;
        }
        .card-header .logo-wrap {
            margin-bottom: 14px;
        }
        .card-header .logo-wrap img {
            max-width: 200px;
            max-height: 120px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            /* White drop shadow so logo is visible on any header colour */
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.18));
        }
        .card-header .logo-fallback {
            font-size: 2rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 4px;
        }
        .card-header .club-name {
            color: rgba(255,255,255,0.85);
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* ── Body ── */
        .card-body {
            padding: 32px 28px 28px;
        }

        /* ── Animated success icon ── */
        .success-icon {
            width: 86px;
            height: 86px;
            background: linear-gradient(135deg, <?=  $color ?>, <?=  $color ?>cc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.6rem;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px <?=  $color ?>44;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes popIn {
            0%   { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ── Logo on body (smaller, subtle) ── */
        .body-logo {
            max-width: 72px;
            max-height: 48px;
            object-fit: contain;
            display: block;
            margin: 0 auto 16px;
            opacity: 0.75;
        }

        /* ── Text ── */
        h2 {
            color: <?=  $color ?>;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .welcome-name {
            font-size: 1.05rem;
            color: #333;
            margin-bottom: 4px;
        }
        .welcome-name strong { color: <?=  $color ?>; }

        /* ── Attendee type badge ── */
        .badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 20px;
            background: <?=  $color ?>18;
            color: <?=  $color ?>;
            font-size: 0.82rem;
            font-weight: 700;
            margin: 10px 0 20px;
            border: 1.5px solid <?=  $color ?>33;
        }

        /* ── Detail box ── */
        .detail-box {
            background: #f0f4f8;
            border-radius: 12px;
            padding: 16px 18px;
            text-align: left;
            margin-bottom: 18px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            border-bottom: 1px solid #e0e7ef;
            font-size: 0.87rem;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .key { color: #888; }
        .detail-row .val { font-weight: 600; color: #333; }

        /* ── Certificate number ── */
        .cert-no {
            font-size: 0.8rem;
            color: #aaa;
            margin-bottom: 14px;
            letter-spacing: 0.5px;
        }

        /* ── Email status ── */
        .email-status {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .email-status.sent {
            background: #e6f9ee;
            color: #1a7a3e;
            border: 1px solid #b7e4c7;
        }
        .email-status.pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #fde68a;
        }

        /* ── Footer ── */
        .card-footer {
            background: #f8fafc;
            border-top: 1px solid #e9ecef;
            padding: 14px 28px;
            font-size: 0.76rem;
            color: #aaa;
        }
        .card-footer strong { color: <?=  $color ?>; }

        @media (max-width: 480px) {
            .card-body  { padding: 24px 20px; }
            .card-header { padding: 22px 20px 18px; }
        }
    </style>
</head>
<body>
<div class="card">

    <!-- ── Header with logo ── -->
    

    <!-- ── Body ── -->
    <div class="card-body">

        <!-- Animated success tick -->
        <div class="success-icon">✓</div>

        <!-- Logo repeated smaller in body -->
        <?php if (file_exists($logoFile)): ?>
            <img src="<?=  $logoUrl ?>"
                 alt="Club Logo"
                 class="body-logo">
        <?php endif; ?>

        <h2>Check-In Successful!</h2>

        <p class="welcome-name">
            Welcome, <strong><?= htmlspecialchars($result['name']) ?></strong>!
        </p>

        <div class="badge">
            <?= htmlspecialchars($result['attendee_type']) ?>
        </div>

        <!-- Detail box -->
        <div class="detail-box">
            <div class="detail-row">
                <span class="key">Name</span>
                <span class="val"><?= htmlspecialchars($result['name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="key">Type</span>
                <span class="val">
                    <?= htmlspecialchars($result['attendee_type']) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="key">Email</span>
                <span class="val"><?= htmlspecialchars($result['email']) ?></span>
            </div>
            <div class="detail-row">
                <span class="key">Check-In Time</span>
                <span class="val"><?= date('h:i A') ?></span>
            </div>
            <div class="detail-row">
                <span class="key">Date</span>
                <span class="val"><?= date('l, d F Y') ?></span>
            </div>
            <?php if (!empty($result['cert_no'])): ?>
            <div class="detail-row">
                <span class="key">Certificate No</span>
                <span class="val"
                      style="font-size:0.82rem; letter-spacing:0.5px;">
                    <?= htmlspecialchars($result['cert_no']) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Email status -->
        <div class="email-status <?=  $result['email_sent'] ? 'sent' : 'pending' ?>">
            <?php if ($result['email_sent']): ?>
                📧 Your attendance certificate has been sent to<br>
                <strong><?= htmlspecialchars($result['email']) ?></strong>
            <?php else: ?>
                ⏳ Your certificate is being processed.<br>
                Check your inbox at
                <strong><?= htmlspecialchars($result['email']) ?></strong>
                shortly.
            <?php endif; ?>
        </div>

    </div><!-- /card-body -->

    <!-- ── Footer ── -->
    <div class="card-footer">
        <?php if ($hostClub): ?>
            <strong><?= htmlspecialchars($hostClub) ?></strong>
            <!-- &nbsp;·&nbsp; -->
        <?php endif; ?>
        <!-- Service Above Self &nbsp;·&nbsp; <br>Rotary International -->
    </div>

</div>
</body>
</html>
