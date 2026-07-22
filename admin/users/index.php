<?php
// admin/users/index.php

require_once '../includes/auth.php';
require_once '../includes/role_guard.php';
require_once '../../config/db.php';

// Only Super Admin can manage users
requireRole(['Super Admin']);

$pageTitle = 'User Management';
$pdo       = getPDO();

$search = trim($_GET['q']      ?? '');
$role   = trim($_GET['role']   ?? '');
$status = trim($_GET['status'] ?? '');

$where  = ['1=1'];
$params = [];

if ($search) {
     $where[]  = '(u.username LIKE ? OR u.email LIKE ?)';
     $params[] = "%$search%";
     $params[] = "%$search%";
}
if ($role) {
     $where[]  = 'u.role = ?';
     $params[] =  $role;
}
if ($status !== '') {
     $where[]  = 'u.is_active = ?';
     $params[] = (int)$status;
}

$whereStr = implode(' AND ',  $where);

$stmt =  $pdo->prepare("
    SELECT u.*,
           m.first_name, m.last_name,
           m.rotary_id
    FROM   admin_users u
    LEFT   JOIN members m ON m.id = u.member_id
    WHERE   $whereStr
    ORDER  BY u.role, u.username
");
$stmt->execute($params);
$users =  $stmt->fetchAll();

// Handle toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
     $toggleId  = (int)$_POST['user_id'];
     $newStatus = (int)$_POST['new_status'];

    // Prevent deactivating yourself
    if ($toggleId === (int)$_SESSION['admin_id']) {
         $flashError = 'You cannot deactivate your own account.';
    } else {
         $pdo->prepare("UPDATE admin_users SET is_active=? WHERE id=?")
            ->execute([$newStatus,  $toggleId]);
        header('Location: index.php');
        exit;
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
     $deleteId = (int)$_POST['user_id'];
    if ($deleteId === (int)$_SESSION['admin_id']) {
         $flashError = 'You cannot delete your own account.';
    } else {
         $pdo->prepare("DELETE FROM admin_users WHERE id=?")
            ->execute([$deleteId]);
        header('Location: index.php?deleted=1');
        exit;
    }
}

$roles = ['Super Admin','Secretary','President','Attendance Officer'];

require_once '../includes/layout_top.php';
?>

<?php if (!empty($flashError)): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<?php if (!empty($_GET['created'])): ?>
    <div class="alert alert-success">✅ User created successfully.</div>
<?php endif; ?>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success">🗑️ User deleted successfully.</div>
<?php endif; ?>

<!-- Role legend -->
<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
    <?php
     $roleDesc = [
        'Super Admin'       => ['#003f87', 'Full system access'],
        'Secretary'         => ['#f7a800', 'Meetings, members, reports'],
        'President'         => ['#009a44', 'View reports only'],
        'Attendance Officer'=> ['#6c757d', 'Check-in and meeting reports'],
    ];
    foreach ($roleDesc as  $r => [$color,  $desc]):
    ?>
    <div style="background:#fff; border-left:4px solid <?=  $color ?>;
                padding:10px 16px; border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.06); font-size:0.82rem;">
        <strong style="color:<?=  $color ?>"><?=  $r ?></strong><br>
        <span style="color:#888"><?=  $desc ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters + Actions -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 22px;">
        <form method="GET"
              style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label>Search</label>
                <input type="text" name="q"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Username or email..."
                       style="width:220px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Role</label>
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as  $r): ?>
                        <option value="<?=  $r ?>"
                            <?= ($role ===  $r) ? 'selected' : '' ?>>
                            <?=  $r ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="1" <?= ($status==='1') ? 'selected':'' ?>>Active</option>
                    <option value="0" <?= ($status==='0') ? 'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Search</button>
            <a href="index.php" class="btn btn-outline">✕ Reset</a>
            <div style="margin-left:auto;">
                <a href="create.php" class="btn btn-primary">➕ Add User</a>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:24px;">
    <?php
     $allUsers    =  $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
     $activeUsers =  $pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_active=1")->fetchColumn();
     $superAdmins =  $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='Super Admin'")->fetchColumn();
     $lastLogin   =  $pdo->query("SELECT MAX(last_login) FROM admin_users")->fetchColumn();
    ?>
    <div class="stat-card">
        <div class="val"><?=  $allUsers ?></div>
        <div class="lbl">Total Users</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?=  $activeUsers ?></div>
        <div class="lbl">Active Users</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?=  $superAdmins ?></div>
        <div class="lbl">Super Admins</div>
    </div>
    <div class="stat-card">
        <div class="val" style="font-size:1rem;">
            <?=  $lastLogin ? date('d M, h:i A', strtotime($lastLogin)) : 'Never' ?>
        </div>
        <div class="lbl">Last System Login</div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h2>🔐 Admin Users (<?= count($users) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Linked Member</th>
                    <th>Role</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8"
                        style="text-align:center; color:#999; padding:30px;">
                        No users found.
                    </td>
                </tr>
            <?php else: ?>
                <?php
                 $roleBadge = [
                    'Super Admin'        => 'badge-blue',
                    'Secretary'          => 'badge-gold',
                    'President'          => 'badge-green',
                    'Attendance Officer' => 'badge-gray',
                ];
                foreach ($users as  $i =>  $u):
                     $isCurrentUser = ((int)$u['id'] === (int)$_SESSION['admin_id']);
                ?>
                <tr style="<?=  $isCurrentUser ? 'background:#f0f7ff;' : '' ?>">
                    <td style="color:#999"><?=  $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                        <?php if ($isCurrentUser): ?>
                            <span class="badge badge-blue"
                                  style="font-size:0.68rem; margin-left:4px;">
                                You
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if ($u['first_name']): ?>
                            <span style="color:#003f87;">
                                <?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?=  $roleBadge[$u['role']] ?? 'badge-gray' ?>">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <?=  $u['last_login']
                            ? date('d M Y, h:i A', strtotime($u['last_login']))
                            : '<span style="color:#999">Never</span>' ?>
                    </td>
                    <td>
                        <?=  $u['is_active']
                            ? '<span class="badge badge-green">Active</span>'
                            : '<span class="badge badge-red">Inactive</span>' ?>
                    </td>
                    <td>
                        <div class="actions">
                            <!-- Edit -->
                            <a href="edit.php?id=<?=  $u['id'] ?>"
                               class="btn btn-outline btn-sm">✏️ Edit</a>

                            <!-- Toggle Active -->
                            <?php if (!$isCurrentUser): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id"
                                           value="<?=  $u['id'] ?>">
                                    <input type="hidden" name="new_status"
                                           value="<?=  $u['is_active'] ? 0 : 1 ?>">
                                    <button name="toggle_active"
                                            class="btn btn-sm <?=  $u['is_active'] ? 'btn-gold' : 'btn-green' ?>"
                                            onclick="return confirm('<?=  $u['is_active']
                                                ? 'Deactivate this user?' : 'Activate this user?' ?>')">
                                        <?=  $u['is_active'] ? '⏸ Deactivate' : '▶ Activate' ?>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id"
                                           value="<?=  $u['id'] ?>">
                                    <button name="delete_user"
                                            class="btn btn-red btn-sm"
                                            onclick="return confirm(
                                                'Permanently delete this user? This cannot be undone.')">
                                        🗑
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
