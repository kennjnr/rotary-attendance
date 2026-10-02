<?php
// admin/makeups/_form.php
// Shared form for create.php and edit.php.
// Expects: $members, $clubNames, $data, $isEdit, $record (edit only)
?>
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAKEUP_MAX_BYTES ?>">
    <div class="form-grid">

        <div class="form-group">
            <label>Member <span class="req">*</span></label>
            <select name="member_id" required>
                <option value="">— Select member —</option>
                <?php foreach ($members as $mem): ?>
                    <option value="<?= $mem['id'] ?>"
                        <?= ((int)$data['member_id'] === (int)$mem['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mem['last_name'] . ', ' . $mem['first_name']) ?>
                        <?= $mem['rotary_id'] ? ' (' . htmlspecialchars($mem['rotary_id']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Date of Make-up <span class="req">*</span></label>
            <input type="date" name="meeting_date" max="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($data['meeting_date']) ?>" required>
        </div>

        <div class="form-group">
            <label>Club Visited <span class="req">*</span></label>
            <input type="text" name="club_visited" list="club_list"
                   value="<?= htmlspecialchars($data['club_visited']) ?>"
                   placeholder="e.g. Rotary Club of Nairobi East" required>
            <datalist id="club_list">
                <?php foreach ($clubNames as $cn): ?>
                    <option value="<?= htmlspecialchars($cn) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label>District</label>
            <input type="text" name="district"
                   value="<?= htmlspecialchars($data['district']) ?>"
                   placeholder="e.g. 9212">
        </div>

        <div class="form-group">
            <label>Type of Make-up <span class="req">*</span></label>
            <select name="meeting_type" required>
                <?php foreach (MAKEUP_TYPES as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"
                        <?= ($data['meeting_type'] === $t) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Venue / Location</label>
            <input type="text" name="venue"
                   value="<?= htmlspecialchars($data['venue']) ?>"
                   placeholder="e.g. Serena Hotel, Nairobi">
        </div>

        <div class="form-group full">
            <label>
                Certificate / Notice of Attendance <small class="text-muted">(optional)</small>
            </label>
            <?php if ($isEdit && !empty($record['attachment_path'])): ?>
                <div style="font-size:0.85rem; margin-bottom:4px;">
                    Current file:
                    <a href="attachment.php?id=<?= $record['id'] ?>" target="_blank">
                        📎 <?= htmlspecialchars($record['attachment_name'] ?: 'View attachment') ?>
                    </a>
                    <span class="text-muted">— choose a new file below only to replace it.</span>
                </div>
            <?php endif; ?>
            <input type="file" name="attachment"
                   accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                   style="padding:9px; border:1.5px dashed var(--border); border-radius:8px; background:#fafbfc;">
            <small class="text-muted">PDF, JPG, PNG or WEBP — max 5 MB.</small>
        </div>

        <?php if (!$isEdit && canApproveMakeups()): ?>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="Pending"  <?= $data['status'] === 'Pending'  ? 'selected' : '' ?>>Pending review</option>
                <option value="Approved" <?= $data['status'] === 'Approved' ? 'selected' : '' ?>>Approved (verified now)</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group full">
            <label>Notes</label>
            <textarea name="notes"
                      placeholder="e.g. Speaker topic, who signed the notice, any remarks"
            ><?= htmlspecialchars($data['notes']) ?></textarea>
        </div>

    </div><!-- /form-grid -->

    <div style="margin-top:24px; display:flex; gap:12px;">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? '💾 Save Changes' : '✅ Record Make-up' ?>
        </button>
        <a href="<?= $isEdit ? 'view.php?id=' . (int)$record['id'] : 'index.php' ?>" class="btn btn-outline">Cancel</a>
    </div>
</form>
