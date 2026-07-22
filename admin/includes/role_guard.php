<?php
// admin/includes/role_guard.php
// Include this after auth.php on pages that need role restriction

function requireRole(array  $allowedRoles): void
{
     $currentRole =  $_SESSION['admin_role'] ?? '';
    if (!in_array($currentRole,  $allowedRoles, true)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Access Denied</title>
            <style>
                body {
                    font-family: 'Segoe UI', sans-serif;
                    background: #f0f4f8;
                    display: flex; align-items: center;
                    justify-content: center; min-height: 100vh;
                    margin: 0;
                }
                .box {
                    background: #fff; border-radius: 16px;
                    padding: 48px 40px; max-width: 420px;
                    width: 100%; text-align: center;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.10);
                }
                h2  { color: #c0392b; margin-bottom: 12px; }
                p   { color: #555; margin-bottom: 24px; line-height: 1.6; }
                a   {
                    display: inline-block; padding: 10px 24px;
                    background: #003f87; color: #fff;
                    border-radius: 8px; text-decoration: none;
                    font-weight: 600;
                }
            </style>
        </head>
        <body>
        <div class="box">
            <div style="font-size:3rem; margin-bottom:12px;">🚫</div>
            <h2>Access Denied</h2>
            <p>
                Your role (<strong><?= htmlspecialchars($currentRole) ?></strong>)
                does not have permission to access this page.<br><br>
                Please contact your Super Admin if you need access.
            </p>
            <a href="<?= APP_URL ?>/admin/index.php">← Back to Dashboard</a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}

function isSuperAdmin(): bool
{
    return ($_SESSION['admin_role'] ?? '') === 'Super Admin';
}

function canManageUsers(): bool
{
    return in_array($_SESSION['admin_role'] ?? '', [
        'Super Admin'
    ], true);
}

function canManageMeetings(): bool
{
    return in_array($_SESSION['admin_role'] ?? '', [
        'Super Admin', 'Secretary', 'Attendance Officer'
    ], true);
}

function canViewReports(): bool
{
    return in_array($_SESSION['admin_role'] ?? '', [
        'Super Admin', 'Secretary', 'President', 'Attendance Officer'
    ], true);
}

function canManageMembers(): bool
{
    return in_array($_SESSION['admin_role'] ?? '', [
        'Super Admin', 'Secretary'
    ], true);
}
