<?php
// admin/users/edit.php

require_once '../includes/auth.php';
require_once '../includes/role_guard.php';
require_once '../../config/db.php';

requireRole(['Super Admin']);

$pageTitle = 'Edit User';
$pdo       = getPDO();
$id        = (int)($_GET['id'] ?? 0);
$errors    = [];
$success   = false;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch user
$user =  $pdo->query("
    SELECT u.*, m.first_name, m.last_name
    FROM   admin_users u
    LEFT   JOIN members m ON m.id = u.member_id
    WHERE  u.id =  $id
")->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

$isCurrentUser = ((int)$user['id'] === (int)$_SESSION['admin_id']);
$roles         = ['Super Admin','Secretary','President','Attendance Officer'];

// Fetch members for link dropdown
$members =  $pdo->query("
    SELECT m.id, m.first_name, m.last_name, m.role
    FROM   members m
    WHERE  m.is_active = 1
    AND    (
        m.id NOT IN (
            SELECT member_id FROM admin_users
            WHERE  member_id IS NOT NULL AND id !=  $id
        )
    )
    ORDER  BY m.last_name, m.first_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

     $action =  $_POST['action'] ?? 'update';

    // ── Change Password only ──────────────────────────────────────
    if ($action === 'change_password') {
         $newPass  = trim($_POST['new_password']     ?? '');
         $newPass2 = trim($_POST['new_password2']    ?? '');

        if (!$newPass)                    $errors[] = 'New password is required.';
        elseif (strlen($newPass) < 8)    $errors[] = 'Password must be at least 8 characters.';
        elseif ($newPass !==  $newPass2)  $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
             $hash = password_hash($newPass, PASSWORD_BCRYPT);
             $pdo->prepare("UPDATE admin_users SET password_hash=?, updated_at=NOW() WHERE id=?")
                ->execute([$hash,  $id]);
             $success = 'password';
        }

    // ── Update profile ────────────────────────────────────────────
    } else {
         $username  = trim($_POST['username']   ?? '');
         $email     = trim($_POST['email']      ?? '');
         $role      = trim($_POST['role']       ?? '');
         $memberId  = (int)($_POST['member_id'] ?? 0);
         $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (!$username)                         $errors[] = 'Username is required.';
        if (!$email)                            $errors[] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                                                $errors[] = 'Invalid email address.';
        if (!in_array($role,  $roles, true))     $errors[] = 'Invalid role.';

        // Prevent removing own Super Admin role
        if ($isCurrentUser &&  $role !== 'Super Admin') {
             $errors[] = 'You cannot change your own role.';
        }

        // Prevent deactivating yourself
        if ($isCurrentUser && !$isActive) {
             $errors[] = 'You cannot deactivate your own account.';
        }

        // Check username uniqueness
        if (empty($errors)) {
             $dup =  $pdo->prepare("SELECT id FROM admin_users WHERE username=? AND id!=?");
             $dup->execute([$username,  $id]);
            if ($dup->fetch())  $errors[] = 'Username already taken.';
        }

        // Check email uniqueness
        if (empty($errors)) {
             $dup =  $pdo->prepare("SELECT id FROM admin_users WHERE email=? AND id!=?");
             $dup->execute([$email,  $id]);
            if ($dup->fetch())  $errors[] = 'Email already registered.';
        }

        if (empty($errors)) {
             $pdo->prepare("
                UPDATE admin_users
                SET username   = ?,
                    email      = ?,
                    role       = ?,
                    member_id  = ?,
                    is_active  = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                 $username,
                 $email,
                 $role,
                 $memberId ?: null,
                 $isActive,
                 $id,
            ]);

             $success = 'profile';

            // Refresh user data
             $user =  $pdo->query("
                SELECT u.*, m.first_name, m.last_name
                FROM   admin_users u
                LEFT   JOIN members m ON m.id = u.member_id
                WHERE  u.id =  $id
            ")->fetch();
        }
    }
}

require_once '../includes/layout_top.php';
?>

<?php if ($success === 'profile'): ?>
    <div class="alert alert-success">✅ User profile updated successfully.</div>
<?php endif; ?>

<?php if ($success === 'password'): ?>
    <div class="alert alert-success">🔒 Password changed successfully.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as  $e): ?>
            ⚠️ <?= htmlspecialchars($e) ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="page-actions">
    <a href="index.php" class="btn btn-outline">← All Users</a>
    <?php if ($isCurrentUser): ?>
        <span class="badge badge-blue" style="padding:8px 14px;">
            ✏️ Editing your own account
        </span>
    <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; align-items:start;">

    <!-- ── Left Column: Profile + Password ── -->
    <div style="display:flex; flex-direction:column; gap:24px;">

        <!-- Profile Form -->
        <div class="card">
            <div class="card-header">
                <h2>✏️ Edit User Profile</h2>
                <?php
                 $roleBadge = [
                    'Super Admin'        => 'badge-blue',
                    'Secretary'          => 'badge-gold',
                    'President'          => 'badge-green',
                    'Attendance Officer' => 'badge-gray',
                ];
                ?>
                <span class="badge <?=  $roleBadge[$user['role']] ?? 'badge-gray' ?>">
                    <?= htmlspecialchars($user['role']) ?>
                </span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <div class="form-grid">

                        <!-- Username -->
                        <div class="form-group">
                            <label>Username <span class="req">*</span></label>
                            <input type="text"
                                   name="username"
                                   value="<?= htmlspecialchars($user['username']) ?>"
                                   required>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label>Email Address <span class="req">*</span></label>
                            <input type="email"
                                   name="email"
                                   value="<?= htmlspecialchars($user['email']) ?>"
                                   required>
                        </div>

                        <!-- Role -->
                        <div class="form-group">
                            <label>Role <span class="req">*</span></label>
                            <select name="role"
                                    <?=  $isCurrentUser ? 'disabled' : '' ?>>
                                <?php foreach ($roles as  $r): ?>
                                    <option value="<?=  $r ?>"
                                        <?= ($user['role'] ===  $r) ? 'selected' : '' ?>>
                                        <?=  $r ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isCurrentUser): ?>
                                <!-- Hidden field to preserve value when disabled -->
                                <input type="hidden" name="role"
                                       value="<?= htmlspecialchars($user['role']) ?>">
                                <small style="color:#888;">
                                    You cannot change your own role.
                                </small>
                            <?php endif; ?>
                        </div>

                        <!-- Link to Member -->
                        <div class="form-group">
                            <label>Linked Club Member
                                <span style="font-weight:400; color:#888;">
                                    (optional)
                                </span>
                            </label>
                            <select name="member_id">
                                <option value="0">— Not linked —</option>
                                <?php foreach ($members as  $m): ?>
                                    <option value="<?=  $m['id'] ?>"
                                        <?= ((int)$user['member_id'] === (int)$m['id'])
                                            ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(
                                             $m['first_name'] . ' ' .  $m['last_name']
                                            . ' (' .  $m['role'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Active Status -->
                        <div class="form-group full">
                            <label style="display:flex; align-items:center;
                                          gap:8px; cursor:pointer;">
                                <input type="checkbox"
                                       name="is_active"
                                       value="1"
                                       <?=  $user['is_active'] ? 'checked' : '' ?>
                                       <?=  $isCurrentUser ? 'disabled' : '' ?>
                                       style="width:18px; height:18px;">
                                Account Active
                            </label>
                            <?php if ($isCurrentUser): ?>
                                <input type="hidden" name="is_active" value="1">
                                <small style="color:#888;">
                                    You cannot deactivate your own account.
                                </small>
                            <?php endif; ?>
                        </div>

                    </div><!-- /form-grid -->

                    <div style="margin-top:24px; display:flex; gap:12px;">
                        <button type="submit" class="btn btn-primary">
                            💾 Save Changes
                        </button>
                        <a href="index.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card">
            <div class="card-header">
                <h2>🔒 Change Password</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-grid">

                        <!-- New Password -->
                        <div class="form-group">
                            <label>New Password <span class="req">*</span></label>
                            <div style="position:relative;">
                                <input type="password"
                                       name="new_password"
                                       id="new_password"
                                       placeholder="Min. 8 characters"
                                       style="padding-right:44px;"
                                       required>
                                <button type="button"
                                        onclick="togglePassword('new_password')"
                                        style="position:absolute; right:10px; top:50%;
                                               transform:translateY(-50%);
                                               background:none; border:none;
                                               cursor:pointer; font-size:1rem;">
                                    👁
                                </button>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label>Confirm Password <span class="req">*</span></label>
                            <div style="position:relative;">
                                <input type="password"
                                       name="new_password2"
                                       id="new_password2"
                                       placeholder="Repeat new password"
                                       style="padding-right:44px;"
                                       required>
                                <button type="button"
                                        onclick="togglePassword('new_password2')"
                                        style="position:absolute; right:10px; top:50%;
                                               transform:translateY(-50%);
                                               background:none; border:none;
                                               cursor:pointer; font-size:1rem;">
                                    👁
                                </button>
                            </div>
                        </div>

                        <!-- Strength bar -->
                        <div class="form-group full">
                            <div id="strength-bar"
                                 style="height:6px; border-radius:20px;
                                        background:#f0f0f0; overflow:hidden;
                                        margin-top:-8px;">
                                <div id="strength-fill"
                                     style="height:100%; width:0%;
                                            border-radius:20px;
                                            transition:width 0.3s, background 0.3s;">
                                </div>
                            </div>
                            <div id="strength-label"
                                 style="font-size:0.78rem; color:#888; margin-top:4px;">
                            </div>
                        </div>

                    </div><!-- /form-grid -->

                    <div style="background:#fff3cd; border-left:4px solid #f7a800;
                                padding:12px 16px; border-radius:8px;
                                margin:16px 0; font-size:0.85rem; color:#856404;">
                        ⚠️ The user will need to use the new password on their
                        next login. They will not be notified automatically.
                    </div>

                    <div style="display:flex; gap:12px;">
                        <button type="submit" class="btn btn-gold">
                            🔒 Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /left column -->

    <!-- ── Right Column: User Info ── -->
    <div style="display:flex; flex-direction:column; gap:24px;">

        <!-- User Info Card -->
        <div class="card">
            <div class="card-header"><h2>👤 User Info</h2></div>
            <div class="card-body">
                <table style="width:100%; font-size:0.9rem; line-height:2.2;">
                    <tr>
                        <td style="color:#888; width:45%">User ID</td>
                        <td><strong>#<?=  $user['id'] ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color:#888">Username</td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                    </tr>
                    <tr>
                        <td style="color:#888">Role</td>
                        <td>
                            <span class="badge <?=  $roleBadge[$user['role']] ?? 'badge-gray' ?>">
                                <?= htmlspecialchars($user['role']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#888">Status</td>
                        <td>
                            <?=  $user['is_active']
                                ? '<span class="badge badge-green">Active</span>'
                                : '<span class="badge badge-red">Inactive</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#888">Linked Member</td>
                        <td>
                            <?=  $user['first_name']
                                ? htmlspecialchars(
                                     $user['first_name'] . ' ' .  $user['last_name'])
                                : '<span style="color:#999">Not linked</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#888">Last Login</td>
                        <td>
                            <?=  $user['last_login']
                                ? date('d M Y, h:i A', strtotime($user['last_login']))
                                : '<span style="color:#999">Never</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#888">Created</td>
                        <td>
                            <?= date('d M Y', strtotime($user['created_at'])) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#888">Last Updated</td>
                        <td>
                            <?= date('d M Y', strtotime($user['updated_at'])) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Danger Zone -->
        <?php if (!$isCurrentUser): ?>
        <div class="card"
             style="border:1.5px solid #f8d7da;">
            <div class="card-header"
                 style="background:#fff5f5; border-bottom-color:#f8d7da;">
                <h2 style="color:#c0392b;">⚠️ Danger Zone</h2>
            </div>
            <div class="card-body">

                <!-- Toggle Active -->
                <form method="POST" action="index.php" style="margin-bottom:12px;">
                    <input type="hidden" name="user_id"   value="<?=  $user['id'] ?>">
                    <input type="hidden" name="new_status"
                           value="<?=  $user['is_active'] ? 0 : 1 ?>">
                    <button name="toggle_active"
                            class="btn <?=  $user['is_active'] ? 'btn-gold' : 'btn-green' ?>"
                            style="width:100%;"
                            onclick="return confirm('<?=  $user['is_active']
                                ? 'Deactivate this user? They will not be able to log in.'
                                : 'Reactivate this user?' ?>')">
                        <?=  $user['is_active']
                            ? '⏸ Deactivate Account'
                            : '▶ Reactivate Account' ?>
                    </button>
                </form>

                <!-- Delete -->
                <form method="POST" action="index.php">
                    <input type="hidden" name="user_id" value="<?=  $user['id'] ?>">
                    <button name="delete_user"
                            class="btn btn-red"
                            style="width:100%;"
                            onclick="return confirm(
                                'Permanently delete this user?\n\n' +
                                'This cannot be undone. All audit logs for this ' +
                                'user will be preserved but unlinked.')">
                        🗑️ Delete User Permanently
                    </button>
                </form>

                <p style="font-size:0.78rem; color:#999; margin-top:12px;
                           text-align:center; line-height:1.5;">
                    Deleting a user removes their login access permanently.
                    Meeting data and attendance records are not affected.
                </p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /right column -->

</div><!-- /grid -->

<script>
function togglePassword(fieldId) {
    var field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

document.getElementById('new_password').addEventListener('input', function () {
    var val   = this.value;
    var fill  = document.getElementById('strength-fill');
    var label = document.getElementById('strength-label');
    var score = 0;

    if (val.length >= 8)              score++;
    if (val.length >= 12)             score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    var levels = [
        { pct: '0%',   bg: '#f0f0f0', text: '' },
        { pct: '25%',  bg: '#c0392b', text: 'Weak' },
        { pct: '50%',  bg: '#f7a800', text: 'Fair' },
        { pct: '75%',  bg: '#3498db', text: 'Good' },
        { pct: '100%', bg: '#009a44', text: 'Strong' },
    ];

    var level             = levels[Math.min(score, 4)];
    fill.style.width      = level.pct;
    fill.style.background = level.bg;
    label.textContent     = val.length > 0 ? 'Strength: ' + level.text : '';
    label.style.color     = level.bg;
});

document.getElementById('new_password2').addEventListener('input', function () {
    var p1 = document.getElementById('new_password').value;
    var p2 = this.value;
    this.style.borderColor = (p2 && p1 !== p2) ? '#c0392b' : '#dee2e6';
    if (p2 && p1 === p2) this.style.borderColor = '#009a44';
});
</script>

<?php require_once '../includes/layout_bottom.php'; ?>
