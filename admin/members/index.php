<?php
// admin/members/index.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'Members';
$pdo = getPDO();

// Search
$search = trim($_GET['q'] ?? '');
$where  = '';
$params = [];
if ($search) {
     $where  = "WHERE (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.rotary_id LIKE ?)";
     $params = ["%$search%","%$search%","%$search%","%$search%"];
}

$stmt =  $pdo->prepare("
    SELECT m.*, c.club_name,
           COUNT(ma.id) AS meetings_attended
    FROM   members m
    JOIN   clubs c ON c.id = m.club_id
    LEFT   JOIN member_attendance ma ON ma.member_id = m.id
     $where
    GROUP  BY m.id
    ORDER  BY m.last_name, m.first_name
");
$stmt->execute($params);
$members =  $stmt->fetchAll();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_member'])) {
     $delId = (int)$_POST['member_id'];
     $pdo->prepare("UPDATE members SET is_active=0 WHERE id=?")->execute([$delId]);
    header('Location: index.php'); exit;
}

require_once '../includes/layout_top.php';
?>

<div class="page-actions">
    <a href="create.php" class="btn btn-primary">➕ Add Member</a>
    <form method="GET" style="margin-left:auto; display:flex; gap:8px;">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search members..." style="width:240px;">
        <button type="submit" class="btn btn-outline">🔍 Search</button>
        <?php if ($search): ?>
            <a href="index.php" class="btn btn-outline">✕ Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>👥 Members (<?= count($members) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Phone</th>
                    <th>Role</th><th>Rotary ID</th>
                    <th>Meetings</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($members)): ?>
                <tr><td colspan="8" style="text-align:center;color:#999;padding:30px">No members found.</td></tr>
            <?php else: ?>
                <?php foreach ($members as  $m): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($m['last_name'].', '.$m['first_name']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td><?= htmlspecialchars($m['phone'] ?? '—') ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($m['role']) ?></span></td>
                    <td><?= htmlspecialchars($m['rotary_id'] ?? '—') ?></td>
                    <td><?=  $m['meetings_attended'] ?></td>
                    <td>
                        <?=  $m['is_active']
                            ? '<span class="badge badge-green">Active</span>'
                            : '<span class="badge badge-gray">Inactive</span>' ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="edit.php?id=<?=  $m['id'] ?>"
                               class="btn btn-outline btn-sm">✏️ Edit</a>
                            <?php if ($m['is_active']): ?>
                            <form method="POST" onsubmit="return confirm('Deactivate this member?')">
                                <input type="hidden" name="member_id" value="<?=  $m['id'] ?>">
                                <button name="delete_member" class="btn btn-red btn-sm">🗑</button>
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
