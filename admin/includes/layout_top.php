<?php
// admin/includes/layout_top.php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// Base path resolver — works from any subfolder depth
$adminBase   = str_repeat('../', substr_count(
    str_replace($_SERVER['DOCUMENT_ROOT'], '',  $_SERVER['PHP_SELF']), '/') - 2
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=  $pageTitle ?? 'Admin' ?> — Rotary Attendance</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue:   #003f87;
            --gold:   #f7a800;
            --green:  #009a44;
            --red:    #c0392b;
            --light:  #f0f4f8;
            --white:  #ffffff;
            --text:   #333333;
            --muted:  #6c757d;
            --border: #dee2e6;
            --sidebar-w: 240px;
        }

        body { font-family: 'Segoe UI', sans-serif; background: var(--light);
               color: var(--text); display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w); background: var(--blue);
            color: #fff; display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; height: 100vh;
            z-index: 100; overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }
        .sidebar-brand .logo { font-size: 1.4rem; font-weight: 800; color: var(--gold); }
        .sidebar-brand .sub  { font-size: 0.75rem; color: rgba(255,255,255,0.6); margin-top: 2px; }

        .nav-section { padding: 10px 0; }
        .nav-label {
            font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1px;
            color: rgba(255,255,255,0.4); padding: 10px 20px 4px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px; color: rgba(255,255,255,0.8);
            text-decoration: none; font-size: 0.92rem;
            transition: background 0.15s, color 0.15s;
            border-left: 3px solid transparent;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active {
            background: rgba(255,255,255,0.12); color: #fff;
            border-left-color: var(--gold);
        }
        .nav-item .icon { font-size: 1.1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            margin-top: auto; padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.12);
            font-size: 0.82rem; color: rgba(255,255,255,0.5);
        }
        .sidebar-footer a { color: #f7a800; text-decoration: none; }

        /* ── Main ── */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }

        .topbar {
            background: var(--white); padding: 14px 28px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .topbar h1 { font-size: 1.2rem; color: var(--blue); font-weight: 700; }
        .topbar .admin-info { font-size: 0.88rem; color: var(--muted); }
        .topbar .admin-info strong { color: var(--text); }

        .content { padding: 28px; flex: 1; }

        /* ── Cards ── */
        .card {
            background: var(--white); border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden;
        }
        .card-header {
            padding: 16px 22px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; color: var(--blue); }
        .card-body { padding: 22px; }

        /* ── Stat cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
            gap: 18px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--white); border-radius: 12px;
            padding: 20px 22px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border-left: 4px solid var(--blue);
        }
        .stat-card.gold  { border-left-color: var(--gold); }
        .stat-card.green { border-left-color: var(--green); }
        .stat-card.red   { border-left-color: var(--red); }
        .stat-card .val  { font-size: 2rem; font-weight: 800; color: var(--blue); }
        .stat-card.gold  .val { color: var(--gold); }
        .stat-card.green .val { color: var(--green); }
        .stat-card.red   .val { color: var(--red); }
        .stat-card .lbl  { font-size: 0.82rem; color: var(--muted); margin-top: 4px; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 8px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; border: none;
            text-decoration: none; transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: var(--blue);  color: #fff; }
        .btn-gold    { background: var(--gold);  color: #fff; }
        .btn-green   { background: var(--green); color: #fff; }
        .btn-red     { background: var(--red);   color: #fff; }
        .btn-outline {
            background: transparent; border: 1.5px solid var(--border);
            color: var(--text);
        }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        thead th {
            background: var(--light); padding: 11px 14px;
            text-align: left; font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--muted);
            border-bottom: 2px solid var(--border);
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; vertical-align: middle; }

        /* ── Badges ── */
        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .badge-blue  { background: #e3eeff; color: var(--blue); }
        .badge-gold  { background: #fff3cd; color: #856404; }
        .badge-green { background: #d4edda; color: #155724; }
        .badge-red   { background: #f8d7da; color: #721c24; }
        .badge-gray  { background: #e9ecef; color: #495057; }

        /* ── Forms ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 0.85rem; font-weight: 600; color: #555; }
        input[type=text], input[type=email], input[type=tel],
        input[type=date], input[type=time], input[type=password],
        select, textarea {
            padding: 10px 12px; border: 1.5px solid var(--border);
            border-radius: 8px; font-size: 0.93rem; width: 100%;
            transition: border-color 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: var(--blue);
        }
        textarea { resize: vertical; min-height: 80px; }
        .req { color: var(--red); }

        /* ── Alerts ── */
        .alert { padding: 12px 16px; border-radius: 8px;
                 margin-bottom: 18px; font-size: 0.9rem; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error   { background: #f8d7da; color: #721c24; }
        .alert-info    { background: #d1ecf1; color: #0c5460; }

        /* ── Misc ── */
        .page-actions {
            display: flex; gap: 10px;
            align-items: center; margin-bottom: 22px;
        }
        .text-muted { color: var(--muted); }
        .mt-4 { margin-top: 24px; }
        .mb-4 { margin-bottom: 24px; }
        .flex { display: flex; }
        .gap-2 { gap: 8px; }
        .actions { display: flex; gap: 6px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main    { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div style="text-align:center; padding-bottom:5px;">
            <?php
             $logoFile =  $_SERVER['DOCUMENT_ROOT']
                    . parse_url(APP_URL, PHP_URL_PATH)
                    . '/assets/images/logo.png';
             $logoUrl  = APP_URL . '/assets/images/logowhite.png';
            ?>
            <?php if (file_exists($logoFile)): ?>
                <img src="<?=  $logoUrl ?>"
                    alt="Club Logo"
                    style="max-width:200px; max-height:120px;
                            object-fit:contain;
                            display:block;
                            margin:0 auto 10px auto;">
            <?php else: ?>
                <!-- Fallback rotary wheel icon if logo file not found -->
                <div style="font-size:2.8rem; line-height:1; margin-bottom:6px;">
                    &#9900;
                </div>
            <?php endif; ?>
            <!-- <div class="logo">Rotary</div> -->
            <div class="sub">Attendance System</div>
        </div>
    </div>



    <nav class="nav-section">
        <div class="nav-label">Main</div>
        <a href="<?=  $adminBase ?>admin/index.php"
           class="nav-item <?= ($currentPage === 'index.php' &&  $currentDir === 'admin') ? 'active' : '' ?>">
            <span class="icon">📊</span> Dashboard
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Meetings</div>
        <a href="<?=  $adminBase ?>admin/meetings/index.php"
           class="nav-item <?= ($currentDir === 'meetings') ? 'active' : '' ?>">
            <span class="icon">📅</span> All Meetings
        </a>
        <a href="<?=  $adminBase ?>admin/meetings/create.php"
           class="nav-item <?= ($currentPage === 'create.php' &&  $currentDir === 'meetings') ? 'active' : '' ?>">
            <span class="icon">➕</span> New Meeting
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Members</div>
        <a href="<?=  $adminBase ?>admin/members/index.php"
           class="nav-item <?= ($currentDir === 'members') ? 'active' : '' ?>">
            <span class="icon">👥</span> All Members
        </a>
        <a href="<?=  $adminBase ?>admin/members/create.php"
           class="nav-item <?= ($currentPage === 'create.php' &&  $currentDir === 'members') ? 'active' : '' ?>">
            <span class="icon">➕</span> Add Member
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Reports</div>
        <a href="<?=  $adminBase ?>admin/reports/index.php"
        class="nav-item <?= ($currentPage==='index.php' &&  $currentDir==='reports') ? 'active':'' ?>">
            <span class="icon">📋</span> Meeting Reports
        </a>
        <a href="<?=  $adminBase ?>admin/reports/member_attendance.php"
        class="nav-item <?= ($currentPage==='member_attendance.php') ? 'active':'' ?>">
            <span class="icon">👥</span> Member Attendance
        </a>
        <a href="<?=  $adminBase ?>admin/reports/absentee.php"
        class="nav-item <?= ($currentPage==='absentee.php') ? 'active':'' ?>">
            <span class="icon">🚫</span> Absentee Report
        </a>
        <!-- <a href="<?=  $adminBase ?>admin/reports/visiting_clubs.php"
        class="nav-item <?= ($currentPage==='visiting_clubs.php') ? 'active':'' ?>">
            <span class="icon">🔵</span> Visiting Clubs
        </a> -->
        <a href="<?=  $adminBase ?>admin/reports/monthly.php"
        class="nav-item <?= ($currentPage==='monthly.php') ? 'active':'' ?>">
            <span class="icon">📊</span> Monthly Trends
        </a>
        <a href="<?=  $adminBase ?>admin/reports/certificates.php"
        class="nav-item <?= ($currentPage==='certificates.php') ? 'active':'' ?>">
            <span class="icon">📜</span> Certificates
        </a>
    </nav>


    <nav class="nav-section">
        <div class="nav-label">Administration</div>
        <a href="<?=  $adminBase ?>admin/users/index.php"
        class="nav-item <?= ($currentDir === 'users') ? 'active' : '' ?>">
            <span class="icon">🔐</span> User Management
        </a>
        <a href="<?=  $adminBase ?>admin/settings/club.php"
        class="nav-item <?= ($currentDir === 'settings') ? 'active' : '' ?>">
            <span class="icon">⚙️</span> Club Settings
        </a>
    </nav>


    <div class="sidebar-footer">
        Logged in as
        <strong style="color:#fff">
            <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>
        </strong><br>
        <a href="<?=  $adminBase ?>admin/logout.php">Logout</a>
    </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">
    <div class="topbar">
        <h1><?=  $pageTitle ?? 'Dashboard' ?></h1>
        <div class="admin-info">
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?></strong>
            &nbsp;·&nbsp;
            <?= htmlspecialchars($_SESSION['admin_role'] ?? '') ?>
        </div>
    </div>
    <div class="content">
