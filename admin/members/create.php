<?php
// admin/members/create.php  (also used as edit.php with ?id=N)

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pdo      = getPDO();
$editId   = (int)($_GET['id'] ?? 0);
$isEdit   =  $editId > 0;
$pageTitle =  $isEdit ? 'Edit Member' : 'Add Member';
$errors   = [];
$success  = false;

// Fetch existing data for edit
$member = [];
if ($isEdit) {
     $member =  $pdo->query("SELECT * FROM members WHERE id=$editId")->fetch();
    if (!$member) { header('Location: index.php'); exit; }
}

// Fetch host club
$hostClub =  $pdo->query("SELECT * FROM clubs WHERE is_host_club=1 LIMIT 1")->fetch();

$roles = ['Member','President','Secretary','Treasurer',
          'SAA','Past President','Speaker Secretary','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $firstName = trim($_POST['first_name'] ?? '');
     $lastName  = trim($_POST['last_name']  ?? '');
     $email     = trim($_POST['email']      ?? '');
     $phone     = trim($_POST['phone']      ?? '');
     $rotaryId  = trim($_POST['rotary_id']  ?? '');
     $role      = trim($_POST['role']       ?? 'Member');
     $isActive  = isset($_POST['is_active']) ? 1 : 0;
     $password  = trim($_POST['password']   ?? '');

    if (!$firstName)  $errors[] = 'First name is required.';
    if (!$lastName)   $errors[] = 'Last name is required.';
    if (!$email)      $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Invalid email address.';
    if (!$hostClub)   $errors[] = 'Host club not found.';

    // Check email uniqueness
    if (empty($errors)) {
         $dupCheck =  $pdo->prepare("SELECT id FROM members WHERE email=? AND id != ?");
         $dupCheck->execute([$email,  $editId]);
        if ($dupCheck->fetch())  $errors[] = 'This email is already registered.';
    }

    if (empty($errors)) {
        if ($isEdit) {
             $pdo->prepare("
                UPDATE members
                SET first_name=?, last_name=?, email=?, phone=?,
                    rotary_id=?, role=?, is_active=?, updated_at=NOW()
                WHERE id=?
            ")->execute([$firstName,$lastName,$email,$phone?:null,
                          $rotaryId?:null,$role,$isActive,$editId]);

            // Optionally create admin account
            if ($password) {
                 $hash = password_hash($password, PASSWORD_BCRYPT);
                 $stmt =  $pdo->prepare("SELECT id FROM admin_users WHERE email=?");
                 $stmt->execute([$email]);
                if ($stmt->fetch()) {
                     $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE email=?")
                        ->execute([$hash,$email]);
                } else {
                     $pdo->prepare("INSERT INTO admin_users (member_id,username,email,password_hash,role) VALUES (?,?,?,?,'Secretary')")
                        ->execute([$editId, strtolower($firstName.'.'.$lastName),  $email,  $hash]);
                }
            }
        } else {
             $pdo->prepare("
                INSERT INTO members
                    (club_id,first_name,last_name,email,phone,rotary_id,role,is_active)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([$hostClub['id'],$firstName,$lastName,$email,
                          $phone?:null,$rotaryId?:null,$role,$isActive]);
             $newMemberId = (int)$pdo->lastInsertId();

            // Create admin login if password provided
            if ($password) {
                 $hash = password_hash($password, PASSWORD_BCRYPT);
                 $pdo->prepare("INSERT INTO admin_users (member_id,username,email,password_hash,role) VALUES (?,?,?,?,'Secretary')")
                    ->execute([$newMemberId, strtolower($firstName.'.'.$lastName),  $email,  $hash]);
            }
        }
         $success = true;
        if (!$isEdit) { header('Location: index.php?created=1'); exit; }
         $member =  $pdo->query("SELECT * FROM members WHERE id=$editId")->fetch();
    }
}

require_once '../includes/layout_top.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success">✅ Member <?=  $isEdit ? 'updated' : 'created' ?> successfully!</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as  $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?=  $isEdit ? '✏️ Edit Member' : '➕ Add New Member' ?></h2>
        <a href="index.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">

                <div class="form-group">
                    <label>First Name <span class="req">*</span></label>
                    <input type="text" name="first_name"
                           value="<?= htmlspecialchars($member['first_name'] ??  $_POST['first_name'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name"
                           value="<?= htmlspecialchars($member['last_name'] ??  $_POST['last_name'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($member['email'] ??  $_POST['email'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone"
                           value="<?= htmlspecialchars($member['phone'] ??  $_POST['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Rotary International ID</label>
                    <input type="text" name="rotary_id"
                           value="<?= htmlspecialchars($member['rotary_id'] ??  $_POST['rotary_id'] ?? '') ?>"
                           placeholder="Optional">
                </div>

                <div class="form-group">
                    <label>Club Role</label>
                    <select name="role">
                        <?php foreach ($roles as  $r): ?>
                            <option value="<?=  $r ?>"
                                <?= (($member['role'] ??  $_POST['role'] ?? 'Member') ===  $r) ? 'selected' : '' ?>>
                                <?=  $r ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>
                        <input type="checkbox" name="is_active" value="1"
                               <?= ($member['is_active'] ?? 1) ? 'checked' : '' ?>>
                        &nbsp;Active Member
                    </label>
                </div>

                <div class="form-group full" style="border-top:1px solid #eee; padding-top:18px; margin-top:4px;">
                    <label>
                        Admin Login Password
                        <span style="font-weight:400; color:#888;">
                            (<?=  $isEdit ? 'leave blank to keep current' : 'optional — grants admin access' ?>)
                        </span>
                    </label>
                    <input type="password" name="password"
                           placeholder="<?=  $isEdit ? 'Leave blank to keep unchanged' : 'Set password to enable admin login' ?>">
                </div>

            </div><!-- /form-grid -->

            <div style="margin-top:24px; display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary">
                    <?=  $isEdit ? '💾 Save Changes' : '✅ Add Member' ?>
                </button>
                <a href="index.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
