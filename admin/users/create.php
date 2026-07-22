<?php
// admin/users/create.php

require_once '../includes/auth.php';
require_once '../includes/role_guard.php';
require_once '../../config/db.php';

requireRole(['Super Admin']);

$pageTitle = 'Create User';
$pdo       = getPDO();
$errors    = [];
$success   = false;

$roles = ['Super Admin','Secretary','President','Attendance Officer'];

// Fetch unlinked members (not yet having an admin account)
$members =  $pdo->query("
    SELECT m.id, m.first_name, m.last_name, m.email, m.role
    FROM   members m
    WHERE  m.is_active = 1
    AND    m.id NOT IN (SELECT member_id FROM admin_users WHERE member_id IS NOT NULL)
    ORDER  BY m.last_name, m.first_name
")->fetchAll();

$rolePermissions = [
    'Super Admin'        => [
        'Full system access',
        'User management',
        'Club settings',
        'All meetings and members',
        'All reports',
    ],
    'Secretary'          => [
        'Create and manage meetings',
        'Add and edit members',
        'View and export all reports',
        'Manage check-ins',
    ],
    'President'          => [
        'View dashboard',
        'View and export all reports',
        'Cannot edit meetings or members',
    ],
    'Attendance Officer' => [
        'Manage check-ins',
        'View meeting reports',
        'Cannot manage members or settings',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $username   = trim($_POST['username']    ?? '');
     $email      = trim($_POST['email']       ?? '');
     $role       = trim($_POST['role']        ?? '');
     $password   = trim($_POST['password']    ?? '');
     $password2  = trim($_POST['password2']   ?? '');
     $memberId   = (int)($_POST['member_id']  ?? 0);
     $isActive   = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (!$username)                           $errors[] = 'Username is required.';
    if (!$email)                              $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                                              $errors[] = 'Invalid email address.';
    if (!in_array($role,  $roles, true))       $errors[] = 'Invalid role selected.';
    if (!$password)                           $errors[] = 'Password is required.';
    elseif (strlen($password) < 8)            $errors[] = 'Password must be at least 8 characters.';
    elseif ($password !==  $password2)         $errors[] = 'Passwords do not match.';

    // Check username uniqueness
    if (empty($errors)) {
         $dup =  $pdo->prepare("SELECT id FROM admin_users WHERE username=?");
         $dup->execute([$username]);
        if ($dup->fetch())  $errors[] = 'Username already exists.';
    }

    // Check email uniqueness
    if (empty($errors)) {
         $dup =  $pdo->prepare("SELECT id FROM admin_users WHERE email=?");
         $dup->execute([$email]);
        if ($dup->fetch())  $errors[] = 'Email already registered.';
    }

    if (empty($errors)) {
         $hash = password_hash($password, PASSWORD_BCRYPT);

         $pdo->prepare("
            INSERT INTO admin_users
                (member_id, username, email, password_hash, role, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
             $memberId ?: null,
             $username,
             $email,
             $hash,
             $role,
             $isActive,
        ]);

        header('Location: index.php?created=1');
        exit;
    }
}

require_once '../includes/layout_top.php';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as  $e): ?>
            ⚠️ <?= htmlspecialchars($e) ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="page-actions">
    <a href="index.php" class="btn btn-outline">← Back to Users</a>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; align-items:start;">

    <!-- ── Create Form ── -->
    <div class="card">
        <div class="card-header">
            <h2>➕ Create New User</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">

                    <!-- Username -->
                    <div class="form-group">
                        <label>Username <span class="req">*</span></label>
                        <input type="text"
                               name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="e.g. john.doe"
                               required>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email"
                               name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="e.g. john@rotaryclub.org"
                               required>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label>Role <span class="req">*</span></label>
                        <select name="role" id="role_select" onchange="updatePermissions()">
                            <option value="">— Select Role —</option>
                            <?php foreach ($roles as  $r): ?>
                                <option value="<?=  $r ?>"
                                    <?= (($_POST['role'] ?? '') ===  $r) ? 'selected' : '' ?>>
                                    <?=  $r ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Link to Member -->
                    <div class="form-group">
                        <label>Link to Club Member
                            <span style="font-weight:400; color:#888;">
                                (optional)
                            </span>
                        </label>
                        <select name="member_id">
                            <option value="0">— Not linked to a member —</option>
                            <?php foreach ($members as  $m): ?>
                                <option value="<?=  $m['id'] ?>"
                                    <?= (($_POST['member_id'] ?? 0) ==  $m['id'])
                                        ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(
                                         $m['first_name'] . ' ' .  $m['last_name']
                                        . ' (' .  $m['role'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label>Password <span class="req">*</span></label>
                        <div style="position:relative;">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   placeholder="Min. 8 characters"
                                   style="padding-right:44px;"
                                   required>
                            <button type="button"
                                    onclick="togglePassword('password')"
                                    style="position:absolute; right:10px; top:50%;
                                           transform:translateY(-50%); background:none;
                                           border:none; cursor:pointer; font-size:1rem;">
                                👁
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div style="position:relative;">
                            <input type="password"
                                   name="password2"
                                   id="password2"
                                   placeholder="Repeat password"
                                   style="padding-right:44px;"
                                   required>
                            <button type="button"
                                    onclick="togglePassword('password2')"
                                    style="position:absolute; right:10px; top:50%;
                                           transform:translateY(-50%); background:none;
                                           border:none; cursor:pointer; font-size:1rem;">
                                👁
                            </button>
                        </div>
                    </div>

                    <!-- Password strength indicator -->
                    <div class="form-group full">
                        <div id="strength-bar"
                             style="height:6px; border-radius:20px;
                                    background:#f0f0f0; overflow:hidden; margin-top:-8px;">
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

                    <!-- Active Status -->
                    <div class="form-group full">
                        <label style="display:flex; align-items:center;
                                      gap:8px; cursor:pointer;">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   <?= (!isset($_POST['is_active'])
                                       ||  $_POST['is_active']) ? 'checked' : '' ?>
                                   style="width:18px; height:18px;">
                            Account Active (user can log in immediately)
                        </label>
                    </div>

                </div><!-- /form-grid -->

                <div style="margin-top:24px; display:flex; gap:12px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Create User
                    </button>
                    <a href="index.php" class="btn btn-outline">Cancel</a>
                </div>

            </form>
        </div>
    </div>

    <!-- ── Role Permissions Panel ── -->
    <div class="card" id="permissions-panel">
        <div class="card-header">
            <h2>🔑 Role Permissions</h2>
        </div>
        <div class="card-body">
            <p style="color:#888; font-size:0.88rem; margin-bottom:16px;">
                Select a role to see what this user will be able to do.
            </p>

            <?php foreach ($rolePermissions as  $r =>  $perms):
                 $colors = [
                    'Super Admin'        => '#003f87',
                    'Secretary'          => '#f7a800',
                    'President'          => '#009a44',
                    'Attendance Officer' => '#6c757d',
                ];
                 $color =  $colors[$r] ?? '#003f87';
            ?>
            <div class="role-perm-block"
                 id="perm_<?= str_replace(' ', '_',  $r) ?>"
                 style="display:none; margin-bottom:4px;">
                <div style="background:<?=  $color ?>; color:#fff;
                            padding:10px 14px; border-radius:8px 8px 0 0;
                            font-weight:700; font-size:0.9rem;">
                    <?=  $r ?>
                </div>
                <div style="border:1.5px solid <?=  $color ?>;
                            border-top:none; border-radius:0 0 8px 8px;
                            padding:12px 14px;">
                    <?php foreach ($perms as  $perm): ?>
                        <div style="display:flex; align-items:center;
                                    gap:8px; padding:5px 0;
                                    border-bottom:1px solid #f0f0f0;
                                    font-size:0.88rem; color:#444;">
                            <span style="color:<?=  $color ?>; font-size:1rem;">✓</span>
                            <?=  $perm ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Default message when no role selected -->
            <div id="perm_default">
                <div style="text-align:center; padding:30px 0; color:#ccc;">
                    <div style="font-size:2.5rem; margin-bottom:8px;">🔑</div>
                    <div style="font-size:0.88rem;">
                        Select a role to preview permissions
                    </div>
                </div>
            </div>

            <!-- Permission matrix -->
            <div style="margin-top:20px; border-top:1px solid #eee; padding-top:16px;">
                <p style="font-size:0.8rem; font-weight:700;
                          color:#555; margin-bottom:10px;">
                    PERMISSION MATRIX
                </p>
                <table style="width:100%; font-size:0.75rem; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f0f4f8;">
                            <th style="padding:6px 8px; text-align:left;">
                                Feature
                            </th>
                            <th style="padding:6px 8px; text-align:center;">SA</th>
                            <th style="padding:6px 8px; text-align:center;">SE</th>
                            <th style="padding:6px 8px; text-align:center;">PR</th>
                            <th style="padding:6px 8px; text-align:center;">AO</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                     $matrix = [
                        'Dashboard'          => [1,1,1,1],
                        'Create Meetings'    => [1,1,0,0],
                        'Edit Meetings'      => [1,1,0,0],
                        'Open/Close Check-In'=> [1,1,0,1],
                        'Add Members'        => [1,1,0,0],
                        'Edit Members'       => [1,1,0,0],
                        'View Reports'       => [1,1,1,1],
                        'Export PDF/CSV'     => [1,1,1,1],
                        'User Management'    => [1,0,0,0],
                        'Club Settings'      => [1,0,0,0],
                    ];
                    foreach ($matrix as  $feature =>  $access):
                    ?>
                    <tr style="border-bottom:1px solid #f5f5f5;">
                        <td style="padding:6px 8px; color:#555;">
                            <?=  $feature ?>
                        </td>
                        <?php foreach ($access as  $has): ?>
                        <td style="padding:6px 8px; text-align:center;">
                            <?=  $has
                                ? '<span style="color:#009a44; font-weight:700;">✓</span>'
                                : '<span style="color:#ddd;">✕</span>' ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f0f4f8;">
                            <td colspan="5"
                                style="padding:6px 8px; font-size:0.7rem; color:#888;">
                                SA=Super Admin &nbsp;
                                SE=Secretary &nbsp;
                                PR=President &nbsp;
                                AO=Attendance Officer
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div><!-- /grid -->

<script>
// ── Role permission preview ───────────────────────────────────────
function updatePermissions() {
    var role = document.getElementById('role_select').value;

    // Hide all permission blocks
    document.querySelectorAll('.role-perm-block').forEach(function (el) {
        el.style.display = 'none';
    });

    if (role) {
        document.getElementById('perm_default').style.display = 'none';
        var blockId = 'perm_' + role.replace(/ /g, '_');
        var block   = document.getElementById(blockId);
        if (block) block.style.display = 'block';
    } else {
        document.getElementById('perm_default').style.display = 'block';
    }
}

// ── Password visibility toggle ────────────────────────────────────
function togglePassword(fieldId) {
    var field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

// ── Password strength meter ───────────────────────────────────────
document.getElementById('password').addEventListener('input', function () {
    var val    = this.value;
    var fill   = document.getElementById('strength-fill');
    var label  = document.getElementById('strength-label');
    var score  = 0;

    if (val.length >= 8)                    score++;
    if (val.length >= 12)                   score++;
    if (/[A-Z]/.test(val))                  score++;
    if (/[0-9]/.test(val))                  score++;
    if (/[^A-Za-z0-9]/.test(val))           score++;

    var levels = [
        { pct: '0%',   bg: '#f0f0f0', text: '' },
        { pct: '25%',  bg: '#c0392b', text: 'Weak' },
        { pct: '50%',  bg: '#f7a800', text: 'Fair' },
        { pct: '75%',  bg: '#3498db', text: 'Good' },
        { pct: '100%', bg: '#009a44', text: 'Strong' },
    ];

    var level = levels[Math.min(score, 4)];
    fill.style.width      = level.pct;
    fill.style.background = level.bg;
    label.textContent     = val.length > 0 ? 'Strength: ' + level.text : '';
    label.style.color     = level.bg;
});

// ── Password match indicator ──────────────────────────────────────
document.getElementById('password2').addEventListener('input', function () {
    var p1 = document.getElementById('password').value;
    var p2 = this.value;
    this.style.borderColor = (p2 && p1 !== p2) ? '#c0392b' : '#dee2e6';
    if (p2 && p1 === p2) this.style.borderColor = '#009a44';
});

// Run on load if role is pre-selected (e.g. after form error)
updatePermissions();
</script>

<?php require_once '../includes/layout_bottom.php'; ?>
