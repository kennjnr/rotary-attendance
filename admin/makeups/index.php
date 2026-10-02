<?php
// admin/makeups/index.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../includes/pagination.php';
require_once __DIR__ . '/_helpers.php';
requireRole(['Super Admin', 'Secretary', 'President', 'Attendance Officer']);

$pageTitle = 'Makeup Meetings';
$pdo = getPDO();

// Filters
$statusFilter = in_array($_GET['status'] ?? '', MAKEUP_STATUSES, true) ? $_GET['status'] : '';
$search       = trim($_GET['q'] ?? '');

$conds  = [];
$params = [];
if ($statusFilter) {
    $conds[]  = 'mm.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $conds[]  = '(m.first_name LIKE ? OR m.last_name LIKE ? OR mm.club_visited LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

// Status counts for the tabs
$counts = array_fill_keys(MAKEUP_STATUSES, 0);
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM makeup_meetings GROUP BY status") as $row) {
    $counts[$row['status']] = (int)$row['n'];
}

// Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM makeup_meetings mm JOIN members m ON m.id = mm.member_id $where");
$countStmt->execute($params);
$pager = paginate((int)$countStmt->fetchColumn(), 10);

$stmt = $pdo->prepare("
    SELECT mm.*, m.first_name, m.last_name, m.rotary_id
    FROM   makeup_meetings mm
    JOIN   members m ON m.id = mm.member_id
    $where
    ORDER  BY mm.meeting_date DESC, mm.id DESC
    LIMIT  {$pager['per_page']} OFFSET {$pager['offset']}
");
$stmt->execute($params);
$makeups = $stmt->fetchAll();

require_once '../includes/layout_top.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:22px;">
    <div class="stat-card gold">
        <div class="val"><?= $counts['Pending'] ?></div>
        <div class="lbl">Pending Review</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= $counts['Approved'] ?></div>
        <div class="lbl">Approved Make-ups</div>
    </div>
    <div class="stat-card red">
        <div class="val"><?= $counts['Rejected'] ?></div>
        <div class="lbl">Rejected</div>
    </div>
</div>

<div class="page-actions" style="flex-wrap:wrap;">
    <?php if (canRecordMakeups()): ?>
        <a href="create.php" class="btn btn-primary">➕ Record Make-up</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/makeup.php" target="_blank" class="btn btn-outline"
       title="Public page members use to submit make-ups. Share this link with members.">🔗 Public Form</a>
    <div style="display:flex; gap:8px;">
        <?php foreach ([''=>'All','Pending'=>'Pending','Approved'=>'Approved','Rejected'=>'Rejected'] as $val=>$lbl): ?>
            <a href="?<?= http_build_query(array_filter(['status'=>$val, 'q'=>$search])) ?>"
               class="btn btn-sm <?= ($statusFilter===$val) ? 'btn-primary' : 'btn-outline' ?>">
                <?= $lbl ?>
            </a>
        <?php endforeach; ?>
    </div>
    <form method="GET" style="margin-left:auto; display:flex; gap:8px;">
        <?php if ($statusFilter): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <?php endif; ?>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search member or club..." style="width:220px;">
        <button type="submit" class="btn btn-outline">🔍 Search</button>
        <?php if ($search): ?>
            <a href="?<?= http_build_query(array_filter(['status'=>$statusFilter])) ?>" class="btn btn-outline">✕ Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>🔁 Makeup Meetings (<?= $pager['total'] ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Date</th>
                    <th>Club Visited</th>
                    <th>Type</th>
                    <th>Proof</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($makeups)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:30px">No make-up meetings found.</td></tr>
            <?php else: ?>
                <?php foreach ($makeups as $mk): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($mk['last_name'] . ', ' . $mk['first_name']) ?></strong>
                        <?php if ($mk['rotary_id']): ?>
                            <br><small style="color:#888"><?= htmlspecialchars($mk['rotary_id']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($mk['meeting_date'])) ?></td>
                    <td>
                        <?= htmlspecialchars($mk['club_visited']) ?>
                        <?php if ($mk['district']): ?>
                            <br><small style="color:#888">District <?= htmlspecialchars($mk['district']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($mk['meeting_type']) ?></span></td>
                    <td>
                        <?php if ($mk['attachment_path']): ?>
                            <a href="attachment.php?id=<?= $mk['id'] ?>" target="_blank" title="<?= htmlspecialchars($mk['attachment_name'] ?? '') ?>">📎 View</a>
                        <?php else: ?>
                            <span style="color:#999">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= makeupBadge($mk['status']) ?>
                        <?php if (!$mk['created_by']): ?>
                            <br><small style="color:#888" title="Submitted by the member via the public form">🌐 Online</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="view.php?id=<?= $mk['id'] ?>" class="btn btn-outline btn-sm">👁 View</a>
                            <?php if (canRecordMakeups()): ?>
                                <a href="edit.php?id=<?= $mk['id'] ?>" class="btn btn-outline btn-sm">✏️</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= renderPagination($pager) ?>
</div>

<?php require_once '../includes/layout_bottom.php'; ?>
