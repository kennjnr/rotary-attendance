<?php
// admin/settings/club.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'Club Settings';
$pdo       = getPDO();
$success   = false;
$errors    = [];

// Fetch existing host club
$hostClub =  $pdo->query("SELECT * FROM clubs WHERE is_host_club = 1 LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $clubName = trim($_POST['club_name'] ?? '');
     $district = trim($_POST['district']  ?? '');
     $city     = trim($_POST['city']      ?? '');
     $country  = trim($_POST['country']   ?? '');

    if (!$clubName)  $errors[] = 'Club name is required.';

    if (empty($errors)) {
        if ($hostClub) {
            // Update existing host club
             $pdo->prepare("
                UPDATE clubs
                SET club_name = ?, district = ?, city = ?, country = ?, updated_at = NOW()
                WHERE is_host_club = 1
            ")->execute([$clubName,  $district,  $city,  $country]);
        } else {
            // Insert new host club
             $pdo->prepare("
                INSERT INTO clubs (club_name, district, city, country, is_host_club)
                VALUES (?, ?, ?, ?, 1)
            ")->execute([$clubName,  $district,  $city,  $country]);
        }
         $success  = true;
         $hostClub =  $pdo->query("SELECT * FROM clubs WHERE is_host_club = 1 LIMIT 1")->fetch();
    }
}

require_once '../includes/layout_top.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success">✅ Host club saved successfully!</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as  $e): ?>
            ⚠️ <?= htmlspecialchars($e) ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$hostClub): ?>
    <div class="alert alert-info">
        ⚠️ No host club is set up yet. Please fill in your club details below.
        The system cannot create meetings or add members until this is done.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>🏛️ Host Club Settings</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">

                <div class="form-group full">
                    <label>Club Name <span class="req">*</span></label>
                    <input type="text" name="club_name"
                           value="<?= htmlspecialchars($hostClub['club_name'] ??  $_POST['club_name'] ?? '') ?>"
                           placeholder="e.g. Rotary Club of Victoria Island"
                           required>
                </div>

                <div class="form-group">
                    <label>District</label>
                    <input type="text" name="district"
                           value="<?= htmlspecialchars($hostClub['district'] ??  $_POST['district'] ?? '') ?>"
                           placeholder="e.g. District 9110">
                </div>

                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city"
                           value="<?= htmlspecialchars($hostClub['city'] ??  $_POST['city'] ?? '') ?>"
                           placeholder="e.g. Lagos">
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country"
                           value="<?= htmlspecialchars($hostClub['country'] ??  $_POST['country'] ?? 'Nigeria') ?>"
                           placeholder="e.g. Nigeria">
                </div>

            </div>

            <div style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary">
                    💾 Save Club Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($hostClub): ?>
<!-- Current club info display -->
<div class="card mt-4">
    <div class="card-header"><h2>📋 Current Host Club</h2></div>
    <div class="card-body">
        <table style="font-size: 0.92rem; line-height: 2.2; width: 100%;">
            <tr>
                <td style="color:#888; width:30%">Club Name</td>
                <td><strong><?= htmlspecialchars($hostClub['club_name']) ?></strong></td>
            </tr>
            <tr>
                <td style="color:#888">District</td>
                <td><?= htmlspecialchars($hostClub['district'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="color:#888">City</td>
                <td><?= htmlspecialchars($hostClub['city'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="color:#888">Country</td>
                <td><?= htmlspecialchars($hostClub['country'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="color:#888">Club ID</td>
                <td><?=  $hostClub['id'] ?></td>
            </tr>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/layout_bottom.php'; ?>
