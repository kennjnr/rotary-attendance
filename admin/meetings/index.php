<?php
// admin/meetings/index.php

require_once '../includes/auth.php';
require_once '../../config/db.php';

$pageTitle = 'All Meetings';
$pdo = getPDO();

// Status filter
$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE m.status = " . $pdo->quote($statusFilter) : '';

$meetings = $pdo->query("
    SELECT m.*, c.club_name,
           COALESCE(ms.total_members_present,0)    AS members_present,
           COALESCE(ms.total_visiting_rotarians,0) AS visitors,
           COALESCE(ms.total_guests,0)             AS guests,
           COALESCE(ms.total_attendees,0)          AS total_attendees
    FROM   meetings m
    JOIN   clubs c ON c.id = m.club_id
    LEFT   JOIN meeting_summary ms ON ms.meeting_id = m.id
    $where
    ORDER  BY m.meeting_date DESC, m.start_time DESC
")->fetchAll();

// Handle quick status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $meetingId = (int)$_POST['meeting_id'];
    $newStatus = $_POST['new_status'];
    $allowed   = ['Open', 'Closed', 'Cancelled', 'Scheduled'];
    if (in_array($newStatus, $allowed)) {
        $pdo->prepare("UPDATE meetings SET status=? WHERE id=?")
            ->execute([$newStatus, $meetingId]);
    }
    header('Location: index.php'); exit;
}

require_once '../includes/layout_top.php';
?>

<div class="page-actions">
    <a href="create.php" class="btn btn-primary">➕ New Meeting</a>
    <div style="margin-left:auto; display:flex; gap:8px;">
        <?php foreach ([''=>'All','Scheduled'=>'Scheduled','Open'=>'Open','Closed'=>'Closed','Cancelled'=>'Cancelled'] as $val=>$lbl): ?>
            <a href="?status=<?= $val ?>"
               class="btn btn-sm <?= ($statusFilter===$val) ? 'btn-primary' : 'btn-outline' ?>">
                <?= $lbl ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📅 Meetings (<?= count($meetings) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Members</th>
                    <th>Visitors</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($meetings)): ?>
                <tr>
                    <td colspan="10"
                        style="text-align:center; color:#999; padding:30px;">
                        No meetings found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($meetings as $m):
                    $badgeMap = [
                        'Scheduled' => 'badge-gold',
                        'Open'      => 'badge-green',
                        'Closed'    => 'badge-gray',
                        'Cancelled' => 'badge-red',
                    ];
                    $badge = $badgeMap[$m['status']] ?? 'badge-gray';
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($m['title']) ?></strong>
                    </td>
                    <td><?= date('d M Y', strtotime($m['meeting_date'])) ?></td>
                    <td><?= date('h:i A', strtotime($m['start_time'])) ?></td>
                    <td><?= htmlspecialchars($m['venue'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $badge ?>">
                            <?= $m['status'] ?>
                        </span>
                    </td>
                    <td><?= $m['members_present'] ?></td>
                    <td><?= $m['visitors'] ?></td>
                    <td><?= $m['guests'] ?></td>
                    <td><strong><?= $m['total_attendees'] ?></strong></td>
                    <td>
                        <div class="actions">
                            <a href="view.php?id=<?= $m['id'] ?>"
                               class="btn btn-outline btn-sm">👁 View</a>
                            <a href="edit.php?id=<?= $m['id'] ?>"
                               class="btn btn-outline btn-sm">✏️</a>

                            <!-- Quick status toggle buttons -->
                            <?php if ($m['status'] === 'Scheduled'): ?>
                                <button class="btn btn-green btn-sm"
                                        onclick="confirmStatus(
                                            <?= $m['id'] ?>,
                                            'Open',
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= date('d M Y', strtotime($m['meeting_date'])) ?>'
                                        )">
                                    ▶ Open
                                </button>
                                <button class="btn btn-red btn-sm"
                                        onclick="confirmStatus(
                                            <?= $m['id'] ?>,
                                            'Cancelled',
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= date('d M Y', strtotime($m['meeting_date'])) ?>'
                                        )">
                                    ✕ Cancel
                                </button>

                            <?php elseif ($m['status'] === 'Open'): ?>
                                <button class="btn btn-red btn-sm"
                                        onclick="confirmStatus(
                                            <?= $m['id'] ?>,
                                            'Closed',
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= date('d M Y', strtotime($m['meeting_date'])) ?>'
                                        )">
                                    ⏹ Close
                                </button>
                                <button class="btn btn-outline btn-sm"
                                        onclick="confirmStatus(
                                            <?= $m['id'] ?>,
                                            'Cancelled',
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= date('d M Y', strtotime($m['meeting_date'])) ?>'
                                        )">
                                    ✕ Cancel
                                </button>

                            <?php elseif ($m['status'] === 'Closed'): ?>
                                <button class="btn btn-gold btn-sm"
                                        onclick="confirmStatus(
                                            <?= $m['id'] ?>,
                                            'Open',
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= date('d M Y', strtotime($m['meeting_date'])) ?>'
                                        )">
                                    ↩ Reopen
                                </button>

                            <?php elseif ($m['status'] === 'Cancelled'): ?>
                                <button class="btn btn-gold btn-sm"
                                        onclick="confirmStatus(
                                            <?= $m['id'] ?>,
                                            'Scheduled',
                                            '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>',
                                            '<?= date('d M Y', strtotime($m['meeting_date'])) ?>'
                                        )">
                                    ↩ Restore
                                </button>
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

<!-- ══════════════════════════════════════
     CONFIRMATION MODAL
══════════════════════════════════════ -->
<div id="statusModal" style="display:none;">
    <div class="modal-backdrop"></div>
    <div class="modal-box">

        <div class="modal-icon" id="modalIcon">▶</div>
        <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
        <p class="modal-body" id="modalBody"></p>

        <div class="modal-detail-box">
            <div class="modal-detail-row">
                <span class="key">Meeting</span>
                <span class="val" id="modalMeetingName"></span>
            </div>
            <div class="modal-detail-row">
                <span class="key">Date</span>
                <span class="val" id="modalMeetingDate"></span>
            </div>
            <div class="modal-detail-row">
                <span class="key">New Status</span>
                <span class="val" id="modalNewStatus"></span>
            </div>
        </div>

        <form method="POST" id="statusForm">
            <input type="hidden" name="meeting_id" id="modalMeetingId">
            <input type="hidden" name="new_status"  id="modalNewStatusInput">
            <div class="modal-actions">
                <button type="button"
                        class="btn btn-outline"
                        onclick="closeModal()">
                    ✕ Cancel
                </button>
                <button type="submit"
                        name="toggle_status"
                        class="btn"
                        id="modalConfirmBtn">
                    Confirm
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ══════════════════════════════════════
     MODAL STYLES
══════════════════════════════════════ -->
<style>
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        z-index: 999;
        animation: fadeIn 0.2s ease;
    }
    .modal-box {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        border-radius: 18px;
        padding: 36px 32px 28px;
        max-width: 420px;
        width: calc(100% - 40px);
        z-index: 1000;
        box-shadow: 0 24px 64px rgba(0,0,0,0.22);
        text-align: center;
        animation: slideUp 0.25s cubic-bezier(0.175,0.885,0.32,1.275);
    }
    @keyframes fadeIn  {
        from { opacity: 0; } to { opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translate(-50%, -40%); opacity: 0; }
        to   { transform: translate(-50%, -50%); opacity: 1; }
    }
    .modal-icon {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 18px;
    }
    .modal-title {
        font-size: 1.2rem;
        font-weight: 800;
        color: #003f87;
        margin-bottom: 8px;
    }
    .modal-body {
        color: #666;
        font-size: 0.92rem;
        margin-bottom: 20px;
        line-height: 1.6;
    }
    .modal-detail-box {
        background: #f0f4f8;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 22px;
        text-align: left;
    }
    .modal-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid #e0e7ef;
        font-size: 0.87rem;
    }
    .modal-detail-row:last-child { border-bottom: none; }
    .modal-detail-row .key { color: #888; }
    .modal-detail-row .val { font-weight: 600; color: #333; }
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    .modal-actions .btn {
        min-width: 130px;
        padding: 11px 20px;
    }
</style>

<!-- ══════════════════════════════════════
     MODAL JAVASCRIPT
══════════════════════════════════════ -->
<script>
const statusConfig = {
    Open: {
        icon:    '▶',
        iconBg:  '#e6f9ee',
        iconClr: '#009a44',
        title:   'Open Check-In?',
        body:    'This will allow members, visitors and guests to check in using the QR code.',
        btnCls:  'btn-green',
        badge:   '🟢 Open',
    },
    Closed: {
        icon:    '⏹',
        iconBg:  '#f8d7da',
        iconClr: '#c0392b',
        title:   'Close Check-In?',
        body:    'No further check-ins will be accepted after closing. You can reopen the meeting later if needed.',
        btnCls:  'btn-red',
        badge:   '🔒 Closed',
    },
    Cancelled: {
        icon:    '✕',
        iconBg:  '#f8d7da',
        iconClr: '#c0392b',
        title:   'Cancel This Meeting?',
        body:    'The meeting will be marked as cancelled. Attendees will not be able to check in.',
        btnCls:  'btn-red',
        badge:   '❌ Cancelled',
    },
    Scheduled: {
        icon:    '↩',
        iconBg:  '#fff3cd',
        iconClr: '#856404',
        title:   'Restore to Scheduled?',
        body:    'The meeting will be restored to Scheduled status.',
        btnCls:  'btn-gold',
        badge:   '🕐 Scheduled',
    },
};

function confirmStatus(meetingId, newStatus, meetingName, meetingDate) {
    const cfg = statusConfig[newStatus];
    if (!cfg) return;

    // Populate modal
    const icon = document.getElementById('modalIcon');
    icon.textContent        = cfg.icon;
    icon.style.background   = cfg.iconBg;
    icon.style.color        = cfg.iconClr;

    document.getElementById('modalTitle').textContent       = cfg.title;
    document.getElementById('modalBody').textContent        = cfg.body;
    document.getElementById('modalMeetingName').textContent = meetingName;
    document.getElementById('modalMeetingDate').textContent = meetingDate;
    document.getElementById('modalNewStatus').textContent   = cfg.badge;
    document.getElementById('modalMeetingId').value         = meetingId;
    document.getElementById('modalNewStatusInput').value    = newStatus;

    const confirmBtn = document.getElementById('modalConfirmBtn');
    confirmBtn.className = 'btn ' + cfg.btnCls;
    confirmBtn.textContent = '✓ Confirm';

    document.getElementById('statusModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close on backdrop click
document.getElementById('statusModal')
    .querySelector('.modal-backdrop')
    .addEventListener('click', closeModal);

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php require_once '../includes/layout_bottom.php'; ?>
