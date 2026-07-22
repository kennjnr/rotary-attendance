<?php
// certificate.php
// Public page — no login required
// URL: /certificate.php?no=RCUHCERT-2026-0001

require_once __DIR__ . '/config/db.php';

$certNo  = trim($_GET['no'] ?? '');
$logoFile = __DIR__ . '/assets/images/logowhite.png';
$logoUrl  = APP_URL . '/assets/images/logowhite.png';

if (!$certNo) {
     $error = 'No certificate number provided.';
} else {
     $pdo  = getPDO();
     $stmt =  $pdo->prepare("
        SELECT c.*,
               m.title        AS meeting_title,
               m.meeting_date,
               m.start_time,
               m.venue,
               cl.club_name
        FROM   certificates c
        JOIN   meetings m  ON m.id  = c.meeting_id
        JOIN   clubs    cl ON cl.id = m.club_id
        WHERE  c.certificate_no = ?
    ");
     $stmt->execute([$certNo]);
     $cert =  $stmt->fetch();

    if (!$cert) {
         $error = 'Certificate not found. Please check the certificate number.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= !empty($cert)
            ? 'Certificate — ' . htmlspecialchars($cert['certificate_no'])
            : 'Certificate Lookup' ?>
    </title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #003f87 0%, #0062cc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.22);
            overflow: hidden;
        }
        .card-header {
            background: #003f87;
            padding: 28px 32px 22px;
            text-align: center;
        }
        .card-header img {
            max-width: 110px;
            max-height: 70px;
            object-fit: contain;
            display: block;
            margin: 0 auto 12px;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.18));
        }
        .card-header .logo-fallback {
            font-size: 2.2rem;
            color: #f7a800;
            margin-bottom: 8px;
        }
        .card-header h1 {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .card-header p {
            color: rgba(255,255,255,0.6);
            font-size: 0.82rem;
        }
        .card-body  { padding: 32px; }
        .card-footer {
            background: #f8fafc;
            border-top: 1px solid #e9ecef;
            padding: 14px 32px;
            text-align: center;
            font-size: 0.76rem;
            color: #aaa;
        }

        /* ── Search form ── */
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }
        .search-form input {
            flex: 1;
            padding: 11px 14px;
            border: 1.5px solid #dee2e6;
            border-radius: 8px;
            font-size: 0.93rem;
        }
        .search-form input:focus {
            outline: none;
            border-color: #003f87;
        }
        .btn {
            padding: 11px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.93rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.88; }
        .btn-primary { background: #003f87; color: #fff; }
        .btn-green   { background: #009a44; color: #fff; }
        .btn-gold    { background: #f7a800; color: #fff; }
        .btn-block   { display: flex; width: 100%; justify-content: center; }

        /* ── Certificate detail ── */
        .cert-verified {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #e6f9ee;
            border: 1.5px solid #b7e4c7;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 22px;
        }
        .cert-verified .tick {
            width: 40px;
            height: 40px;
            background: #009a44;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .cert-verified .text h3 {
            color: #1a7a3e;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .cert-verified .text p {
            color: #2d8a52;
            font-size: 0.82rem;
        }

        .detail-box {
            background: #f0f4f8;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 22px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #e0e7ef;
            font-size: 0.88rem;
            gap: 12px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .key {
            color: #888;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .detail-row .val {
            font-weight: 600;
            color: #333;
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-blue  { background: #e3eeff; color: #003f87; }
        .badge-gold  { background: #fff3cd; color: #856404; }
        .badge-green { background: #d4edda; color: #155724; }

        .action-btns {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ── Error ── */
        .error-box {
            text-align: center;
            padding: 20px 0;
        }
        .error-box .icon { font-size: 3rem; margin-bottom: 12px; }
        .error-box h3 { color: #c0392b; margin-bottom: 8px; }
        .error-box p  { color: #888; font-size: 0.9rem; line-height: 1.6; }
    </style>
</head>
<body>
<div class="card">

    <!-- Header -->
    <div class="card-header">
        <?php if (file_exists($logoFile)): ?>
            <img src="<?=  $logoUrl ?>" alt="Club Logo">
        <?php else: ?>
            <div class="logo-fallback">&#9900;</div>
        <?php endif; ?>
        <h1>Certificate Verification</h1>
        <p>Rotary Club Attendance System</p>
    </div>

    <div class="card-body">

        <!-- Search form — always visible -->
        <form method="GET" class="search-form">
            <input type="text"
                   name="no"
                   value="<?= htmlspecialchars($certNo) ?>"
                   placeholder="Enter certificate number e.g. RCUHCERT-2026-0001"
                   required>
            <button type="submit" class="btn btn-primary">🔍</button>
        </form>

        <?php if (!empty($error)): ?>
            <!-- Error state -->
            <div class="error-box">
                <div class="icon">🔍</div>
                <h3>Not Found</h3>
                <p><?= htmlspecialchars($error) ?></p>
            </div>

        <?php elseif (!empty($cert)): ?>
            <!-- Verified badge -->
            <div class="cert-verified">
                <div class="tick">✓</div>
                <div class="text">
                    <h3>Certificate Verified</h3>
                    <p>
                        This is an authentic attendance certificate
                        issued by <?= htmlspecialchars($cert['club_name']) ?>.
                    </p>
                </div>
            </div>

            <!-- Certificate details -->
            <div class="detail-box">
                <div class="detail-row">
                    <span class="key">Certificate No</span>
                    <span class="val" style="font-family:monospace; font-size:0.85rem;">
                        <?= htmlspecialchars($cert['certificate_no']) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Recipient</span>
                    <span class="val">
                        <?= htmlspecialchars($cert['recipient_name']) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Attendee Type</span>
                    <span class="val">
                        <?php
                         $typeBadge = [
                            'Member'            => 'badge-blue',
                            'Visiting Rotarian' => 'badge-gold',
                            'Guest'             => 'badge-green',
                        ];
                        ?>
                        <span class="badge <?=  $typeBadge[$cert['attendee_type']]
                            ?? 'badge-blue' ?>">
                            <?= htmlspecialchars($cert['attendee_type']) ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Meeting</span>
                    <span class="val">
                        <?= htmlspecialchars($cert['meeting_title']) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Club</span>
                    <span class="val">
                        <?= htmlspecialchars($cert['club_name']) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Meeting Date</span>
                    <span class="val">
                        <?= date('l, d F Y',
                            strtotime($cert['meeting_date'])) ?>
                    </span>
                </div>
                <?php if ($cert['venue']): ?>
                <div class="detail-row">
                    <span class="key">Venue</span>
                    <span class="val">
                        <?= htmlspecialchars($cert['venue']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="key">Issued On</span>
                    <span class="val">
                        <?= date('d F Y, h:i A',
                            strtotime($cert['issued_at'])) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Email Sent</span>
                    <span class="val">
                        <?=  $cert['email_sent']
                            ? '<span style="color:#009a44">✓ Yes — '
                              . date('d M Y', strtotime($cert['email_sent_at']))
                              . '</span>'
                            : '<span style="color:#f7a800">⏳ Pending</span>' ?>
                    </span>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="action-btns">
                <?php if (!empty($cert['file_path'])
                          && file_exists($cert['file_path'])): ?>
                    <!-- Download PDF -->
                    <a href="<?= APP_URL . '/download_certificate.php?no=' . urlencode($cert['certificate_no']) ?>"
                        class="btn btn-primary btn-block">
                            ⬇️ Download Certificate PDF
                        </a>

                    <!-- View PDF in browser -->
                    <a href="<?= APP_URL . '/download_certificate.php?no=' . urlencode($cert['certificate_no']) . '&view=1' ?>"
                        target="_blank"
                        class="btn btn-gold btn-block">
                            👁 View Certificate in Browser
                        </a>
                <?php else: ?>
                    <div style="text-align:center; color:#888;
                                font-size:0.88rem; padding:12px;
                                background:#f8f9fa; border-radius:8px;">
                        ⚠️ PDF file not available for download.
                        Please contact the club secretary.
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>

    <div class="card-footer">
        Rotary CLub of Nairobi UpperHill
    </div>

</div>
</body>
</html>
