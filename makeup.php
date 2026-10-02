<?php
// makeup.php — public form for members to submit a make-up meeting
// Share link: APP_URL/makeup.php  (also linked from the check-in page)

session_start();
require_once 'config/db.php';
require_once 'admin/makeups/_helpers.php';

$pdo        = getPDO();
$errors     = [];
$submitted  = null;
$logoExists = file_exists(__DIR__ . '/assets/images/logo.png');
$logoPath   = 'assets/images/logowhite.png';
$backToken  = $_SESSION['meeting_token'] ?? '';

if (empty($_SESSION['makeup_csrf'])) {
    $_SESSION['makeup_csrf'] = bin2hex(random_bytes(16));
}

$members = $pdo->query("
    SELECT m.id, m.first_name, m.last_name, m.rotary_id, m.email
    FROM   members m
    JOIN   clubs c ON c.id = m.club_id
    WHERE  c.is_host_club = 1 AND m.is_active = 1
    ORDER  BY m.last_name, m.first_name
")->fetchAll();
$memberById = array_column($members, null, 'id');

$clubNames = $pdo->query("SELECT club_name FROM clubs WHERE is_host_club=0 ORDER BY club_name")->fetchAll(PDO::FETCH_COLUMN);

$data = [
    'member_id'    => (int)($_POST['member_id'] ?? 0),
    'email'        => trim($_POST['email']        ?? ''),
    'meeting_date' => trim($_POST['meeting_date'] ?? ''),
    'club_visited' => trim($_POST['club_visited'] ?? ''),
    'district'     => trim($_POST['district']     ?? ''),
    'meeting_type' => trim($_POST['meeting_type'] ?? 'Club Meeting'),
    'venue'        => trim($_POST['venue']        ?? ''),
    'notes'        => trim($_POST['notes']        ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic abuse protection
    $recent = array_filter($_SESSION['makeup_submits'] ?? [], fn($t) => $t > time() - 3600);
    if (!hash_equals($_SESSION['makeup_csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!empty($_POST['website'])) {               // honeypot field, hidden from people
        $errors[] = 'Submission blocked.';
    } elseif (count($recent) >= 5) {
        $errors[] = 'Too many submissions from this device. Please try again later.';
    }

    $member = $memberById[$data['member_id']] ?? null;
    if (empty($errors)) {
        if (!$member) {
            $errors[] = 'Please select your name from the list.';
        } elseif (strcasecmp(trim($member['email']), $data['email']) !== 0) {
            $errors[] = 'That email does not match the one the club has on file for you. '
                      . 'Please use your registered email, or contact the club secretary.';
        }
        if (!$data['meeting_date']) $errors[] = 'Date of the make-up is required.';
        elseif ($data['meeting_date'] > date('Y-m-d')) $errors[] = 'Date of the make-up cannot be in the future.';
        elseif ($data['meeting_date'] < date('Y-m-d', strtotime('-1 year'))) $errors[] = 'Make-ups older than one year cannot be submitted online.';
        if (!$data['club_visited']) $errors[] = 'Club visited is required.';
        if (!in_array($data['meeting_type'], MAKEUP_TYPES, true)) $errors[] = 'Please choose a valid type of make-up.';
    }

    if (empty($errors)) {
        $dup = $pdo->prepare("SELECT id FROM makeup_meetings WHERE member_id=? AND meeting_date=? AND club_visited=? AND status<>'Rejected'");
        $dup->execute([$member['id'], $data['meeting_date'], $data['club_visited']]);
        if ($dup->fetchColumn()) {
            $errors[] = 'You have already submitted this make-up. The club secretary will review it.';
        }
    }

    $file = null;
    if (empty($errors)) {
        $file = storeMakeupUpload('attachment', $errors);   // optional
    }

    if (empty($errors)) {
        $pdo->prepare("
            INSERT INTO makeup_meetings
                (member_id, meeting_date, club_visited, district, meeting_type, venue, notes,
                 attachment_path, attachment_name, attachment_mime, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,'Pending',NULL)
        ")->execute([
            $member['id'], $data['meeting_date'], $data['club_visited'],
            $data['district'] ?: null, $data['meeting_type'], $data['venue'] ?: null, $data['notes'] ?: null,
            $file['path'] ?? null, $file['name'] ?? null, $file['mime'] ?? null,
        ]);

        $recent[] = time();
        $_SESSION['makeup_submits'] = $recent;
        $_SESSION['makeup_done'] = [
            'ref'    => 'MU-' . str_pad($pdo->lastInsertId(), 5, '0', STR_PAD_LEFT),
            'name'   => $member['first_name'],
            'club'   => $data['club_visited'],
            'date'   => $data['meeting_date'],
        ];
        header('Location: makeup.php?done=1');   // avoid resubmission on refresh
        exit;
    }
}

if (!empty($_GET['done']) && !empty($_SESSION['makeup_done'])) {
    $submitted = $_SESSION['makeup_done'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Make-up — Rotary Club</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #003f87 0%, #0062cc 100%);
            min-height: 100vh; padding: 24px 16px;
            display: flex; justify-content: center; align-items: flex-start;
        }
        .card {
            background: #fff; border-radius: 20px; max-width: 520px; width: 100%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.22); overflow: hidden;
        }
        .card-header { background: #003f87; padding: 24px 28px 20px; text-align: center; color: #fff; }
        .card-header img { max-width: 140px; max-height: 80px; object-fit: contain; display: block; margin: 0 auto 10px; }
        .card-header h1 { font-size: 1.25rem; font-weight: 800; }
        .card-header p  { color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-top: 4px; }
        .card-body { padding: 26px 28px 24px; }
        .intro { color: #555; font-size: 0.9rem; line-height: 1.6; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 5px; }
        label { font-size: 0.83rem; font-weight: 600; color: #555; }
        .req  { color: #c0392b; }
        input, select, textarea {
            width: 100%; padding: 11px 12px; border: 1.5px solid #dee2e6;
            border-radius: 8px; font-size: 0.95rem; font-family: inherit; background: #fff;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #003f87; }
        input[type=file] { border-style: dashed; background: #fafbfc; padding: 10px; }
        textarea { min-height: 70px; resize: vertical; }
        small { color: #888; font-size: 0.77rem; }
        .btn {
            display: block; width: 100%; padding: 14px; border: none; border-radius: 10px;
            font-size: 1rem; font-weight: 700; cursor: pointer; text-align: center;
            text-decoration: none; margin-top: 20px;
        }
        .btn-primary { background: #003f87; color: #fff; }
        .btn-outline { background: #fff; color: #003f87; border: 1.5px solid #003f87; margin-top: 10px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 18px; line-height: 1.6; }
        .hp { position: absolute; left: -9999px; }
        .back { display: inline-block; margin-top: 16px; color: #003f87; text-decoration: none; font-size: 0.88rem; }
        .success-icon {
            width: 78px; height: 78px; border-radius: 50%; background: #d4edda; color: #009a44;
            border: 3px solid #009a44; font-size: 2rem; display: flex; align-items: center;
            justify-content: center; margin: 4px auto 18px;
        }
        .summary { background: #f0f4f8; border-radius: 10px; padding: 14px 18px; font-size: 0.9rem; line-height: 1.9; }
        .summary span { color: #888; display: inline-block; width: 110px; }
        .card-footer { background: #f8fafc; border-top: 1px solid #e9ecef; padding: 12px 28px; font-size: 0.75rem; color: #aaa; text-align: center; }
        @media (max-width: 520px) { .grid { grid-template-columns: 1fr; } .card-body { padding: 22px 18px; } }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <?php if ($logoExists): ?>
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Club Logo">
        <?php endif; ?>
        <h1>🔁 Submit a Make-up Meeting</h1>
        <p>Attended a meeting at another club? Let us know.</p>
    </div>

    <div class="card-body">
    <?php if ($submitted): ?>

        <div style="text-align:center;">
            <div class="success-icon">✓</div>
            <h2 style="color:#003f87; font-size:1.2rem; margin-bottom:8px;">
                Thank you, <?= htmlspecialchars($submitted['name']) ?>!
            </h2>
            <p class="intro">Your make-up has been sent to the club secretary for review.</p>
        </div>
        <div class="summary">
            <div><span>Reference</span><strong><?= htmlspecialchars($submitted['ref']) ?></strong></div>
            <div><span>Club visited</span><?= htmlspecialchars($submitted['club']) ?></div>
            <div><span>Date</span><?= date('d M Y', strtotime($submitted['date'])) ?></div>
            <div><span>Status</span>Pending review</div>
        </div>
        <a href="makeup.php" class="btn btn-outline">➕ Submit another make-up</a>
        <?php if ($backToken): ?>
            <a href="checkin.php?token=<?= urlencode($backToken) ?>" class="back">← Back to check-in</a>
        <?php endif; ?>

    <?php else: ?>

        <p class="intro">
            Choose your name, confirm the email the club has on file for you, and tell us about
            the meeting. If the club you visited gave you a certificate or notice of attendance,
            attach it too.
        </p>

        <?php if ($errors): ?>
            <div class="error"><?php foreach ($errors as $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $_SESSION['makeup_csrf'] ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAKEUP_MAX_BYTES ?>">
            <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="grid">
                <div class="field full">
                    <label>Your Name <span class="req">*</span></label>
                    <select name="member_id" required>
                        <option value="">— Select your name —</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $data['member_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['last_name'] . ', ' . $m['first_name']) ?>
                                <?= $m['rotary_id'] ? '(' . htmlspecialchars($m['rotary_id']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label>Your Email <span class="req">*</span></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>"
                           placeholder="The email registered with the club" required>
                </div>

                <div class="field">
                    <label>Date Attended <span class="req">*</span></label>
                    <input type="date" name="meeting_date" max="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($data['meeting_date']) ?>" required>
                </div>

                <div class="field">
                    <label>Type <span class="req">*</span></label>
                    <select name="meeting_type" required>
                        <?php foreach (MAKEUP_TYPES as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $data['meeting_type'] === $t ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Club Visited <span class="req">*</span></label>
                    <input type="text" name="club_visited" list="club_list"
                           value="<?= htmlspecialchars($data['club_visited']) ?>"
                           placeholder="e.g. RC Nairobi East" required>
                    <datalist id="club_list">
                        <?php foreach ($clubNames as $cn): ?><option value="<?= htmlspecialchars($cn) ?>"><?php endforeach; ?>
                    </datalist>
                </div>

                <div class="field">
                    <label>District</label>
                    <input type="text" name="district" value="<?= htmlspecialchars($data['district']) ?>" placeholder="e.g. 9212">
                </div>

                <div class="field full">
                    <label>Venue / Location</label>
                    <input type="text" name="venue" value="<?= htmlspecialchars($data['venue']) ?>" placeholder="e.g. Serena Hotel, Nairobi">
                </div>

                <div class="field full">
                    <label>Certificate / Notice of Attendance <small>(optional)</small></label>
                    <input type="file" name="attachment"
                           accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
                    <small>If you received one: PDF or photo (JPG, PNG, WEBP), max 5 MB. A clear phone photo is fine.</small>
                </div>

                <div class="field full">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Anything the secretary should know (optional)"><?= htmlspecialchars($data['notes']) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">📤 Submit Make-up</button>
        </form>

        <?php if ($backToken): ?>
            <a href="checkin.php?token=<?= urlencode($backToken) ?>" class="back">← Back to check-in</a>
        <?php endif; ?>

    <?php endif; ?>
    </div>

    <div class="card-footer">&copy; <?= date('Y') ?> <strong style="color:#003f87">Rotary Club</strong> · Service Above Self</div>
</div>
</body>
</html>
