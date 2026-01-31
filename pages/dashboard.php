<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
// Summary queries
$total_vouchers = $conn->query("SELECT COUNT(*) FROM vouchers")->fetch_row()[0];
$total_used = $conn->query("SELECT COUNT(*) FROM vouchers WHERE status='USED'")->fetch_row()[0];
$total_minutes = $conn->query("SELECT SUM(minutes) FROM redemption_log WHERE status='SUCCESS'")->fetch_row()[0] ?? 0;
$active_vouchers = $conn->query("SELECT COUNT(*) FROM vouchers WHERE status='UNUSED'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body.dashboard-bg {
            background: #23243a;
            min-height: 100vh;
            margin: 0;
            font-family: 'Manrope', 'Inter', Arial, Helvetica, sans-serif;
        }
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 12, 20, 0.55);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 9;
        }
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 230px;
            background: #15161e;
            box-shadow: 2px 0 16px #0003;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding-top: 32px;
            z-index: 10;
        }
        .sidebar.collapsed {
            width: 72px;
        }
        .sidebar.collapsed .sidebar-link span:not(.icon) {
            display: none;
        }
        .sidebar.collapsed .nav-title span span {
            display: none;
        }
        .sidebar.collapsed .nav-title {
            justify-content: center !important;
            margin-left: 0 !important;
            width: 100% !important;
        }
        .sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 12px 10px;
        }
        .sidebar-toggle {
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.12s ease, background 0.2s ease, border-color 0.2s ease;
        }
        .sidebar-toggle:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); }
        .sidebar-toggle:active { transform: translateY(1px); }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 11;
            width: 100%;
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            background: #15161e;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .topbar-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.6px;
        }
        .sidebar .nav-title {
            display: flex;
            align-items: center;
            gap: 10px;
                color: #23243a;
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 32px;
            margin-left: 28px;
            letter-spacing: 1.5px;
        }
        .sidebar .nav-title svg {
            width: 26px;
            height: 26px;
        }
        .sidebar-nav {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 28px;
            color: #fff;
            border-radius: 8px;
            font-size: 1.08rem;
            font-weight: 500;
            text-decoration: none;
            letter-spacing: 0.2px;
            transition: background 0.18s, color 0.18s, font-weight 0.18s;
            margin: 2px 0;
        }
        .sidebar-link.active, .sidebar-link:hover {
              background: #f0f4fa;
              color: #23243a;
            font-weight: 600;
        }
        .sidebar-link .icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .main-content {
            margin-left: 230px;
            min-height: 100vh;
            height: 100vh;
            width: calc(100vw - 230px);
            background: #23243a;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 0;
        }
        .main-content.sidebar-collapsed {
            margin-left: 72px;
            width: calc(100vw - 72px);
        }
        .dashboard-container {
            background: #23243a;
            width: 100%;
            height: 100vh;
            min-height: 100vh;
            max-height: 100vh;
            padding: 40px 36px 36px 36px;
            position: relative;
            overflow: hidden;
            margin-left: 0;
            margin-top: 0;
            border-radius: 0;
            box-shadow: none;
            max-width: none;
            display: flex;
            flex-direction: column;
        }
        .dashboard-title { color: #fff; font-size: 2.1rem; font-weight: 700; margin-bottom: 18px; letter-spacing: 1.2px; text-align: left; }
        .dashboard-summary {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
            width: 100%;
        }
        .summary-box {
            background: #18192b;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0002;
            padding: 24px 32px;
            color: #fff;
            min-width: 180px;
            text-align: left;
            border: 1.5px solid #3a7afe22;
            flex: 1 1 0;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .summary-box .label { font-size: 1.05rem; color: #aaa; margin-bottom: 6px; display: block; }
        .summary-box .value { font-size: 1.7rem; font-weight: 700; color: #1de9b6; }
        .dashboard-analytics {
            display: flex;
            gap: 32px;
            align-items: flex-start;
            width: 100%;
            flex: 1 1 0;
            height: 100%;
            max-height: 340px;
        }
        .dashboard-graph {
            background: #18192b;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0002;
            padding: 24px 24px 12px 24px;
            min-width: 320px;
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-height: 340px;
        }
        .dashboard-graph-title { color: #fff; font-size: 1.1rem; font-weight: 600; margin-bottom: 12px; }
        .dashboard-table {
            background: #18192b;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0002;
            padding: 18px 18px 8px 18px;
            min-width: 320px;
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            max-height: 340px;
        }
        .dashboard-table-title { color: #fff; font-size: 1.1rem; font-weight: 600; margin-bottom: 12px; }
        .dashboard-table table { width: 100%; border-collapse: collapse; color: #fff; }
        .dashboard-table th, .dashboard-table td { padding: 8px 10px; border-bottom: 1px solid #23243a; }
        .dashboard-table th { background: #23243a; color: #1de9b6; font-weight: 600; }
        .dashboard-table tr:last-child td { border-bottom: none; }
        .table-scroll { width: 100%; overflow-x: auto; }
        .table-scroll table { min-width: 520px; }
        @media (max-width: 980px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.25s ease;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .topbar { display: flex; position: fixed; left: 0; right: 0; top: 0; }
            .main-content { padding-top: 58px; }
            .dashboard-container { padding: 24px 18px 28px; }
            .dashboard-summary { flex-wrap: wrap; }
            .summary-box { min-width: 160px; }
            .dashboard-analytics { flex-direction: column; max-height: none; }
            .dashboard-graph, .dashboard-table { max-height: none; min-width: 0; }
        }
        @media (max-width: 640px) {
            body { font-size: 16px; }
            .dashboard-title { font-size: 1.8rem; }
            .dashboard-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            .summary-box { min-width: 0; padding: 16px 16px; }
            .summary-box .value { font-size: 1.35rem; }
            .dashboard-graph { padding: 18px 16px; }
            #usageChart { width: 100% !important; height: 200px !important; }
            .dashboard-table { padding: 14px 14px 8px; }
            .dashboard-table table { display: block; }
            .dashboard-table thead { display: none; }
            .dashboard-table tr {
                display: block;
                margin-bottom: 12px;
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 12px;
                background: #1a1c2d;
                padding: 8px 10px;
            }
            .dashboard-table td {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
                padding: 8px 4px;
                border-bottom: 1px dashed rgba(255,255,255,0.06);
                color: #eaf1ff;
            }
            .dashboard-table td:last-child { border-bottom: none; }
            .dashboard-table td::before {
                content: attr(data-label);
                color: #8aa0c6;
                font-weight: 700;
                font-size: 0.8rem;
                letter-spacing: 0.2px;
                text-transform: uppercase;
            }
        }
    </style>
</head>
<body class="dashboard-bg">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar">
        <div class="nav-title" style="display:flex;justify-content:space-between;width:90%;margin-bottom:24px;align-items:center;">
            <span style="display:flex;align-items:center;gap:8px;">
                <svg viewBox="0 0 32 32" width="28" height="28" fill="none"><rect x="2" y="2" width="28" height="28" rx="6" fill="#1de9b6"/><rect x="8" y="8" width="16" height="16" rx="3" fill="#fff"/></svg>
                <span style="color:#1de9b6;font-weight:700;font-size:1.25rem;letter-spacing:1px;">WiFi<span style="color:#fff;">Point</span></span>
            </span>
            <button class="sidebar-toggle" aria-label="Toggle sidebar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
        <nav class="sidebar-nav" style="width:100%;">
                        <a href="dashboard.php" class="sidebar-link active" style="margin-top:8px;display:flex;align-items:center;gap:16px;padding:12px 28px;">
                                <span class="icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M4 12L2 14m0 0l10-10 10 10m-2-2v6a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-6m0 0L2 14"/>
                                    </svg>
                                </span>
                                <span>Dashboard</span>
                        </a>
            <a href="voucher_manager.php" class="sidebar-link" style="display:flex;align-items:center;gap:16px;padding:12px 28px;">
                <span class="icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 3v4M8 3v4"/></svg></span>
                <span>Voucher Manager</span>
            </a>
            <a href="logout.php" class="sidebar-link" style="display:flex;align-items:center;gap:16px;padding:12px 28px;">
                <span class="icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    <div class="main-content">
        <div class="topbar">
            <button class="sidebar-toggle" aria-label="Open sidebar">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title">
                <svg viewBox="0 0 32 32" width="22" height="22" fill="none"><rect x="2" y="2" width="28" height="28" rx="6" fill="#1de9b6"/><rect x="8" y="8" width="16" height="16" rx="3" fill="#fff"/></svg>
                <span>WiFiPoint Admin</span>
            </div>
        </div>
        <div class="dashboard-container">
            <div class="dashboard-title">Dashboard Overview</div>
            <div class="dashboard-summary">
                <div class="summary-box">
                    <span class="label">Total Vouchers</span>
                    <span class="value"><?= $total_vouchers ?></span>
                </div>
                <div class="summary-box">
                    <span class="label">Used Vouchers</span>
                    <span class="value"><?= $total_used ?></span>
                </div>
                <div class="summary-box">
                    <span class="label">Total Minutes Redeemed</span>
                    <span class="value"><?= $total_minutes ?></span>
                </div>
                <div class="summary-box">
                    <span class="label">Active Vouchers</span>
                    <span class="value"><?= $active_vouchers ?></span>
                </div>
            </div>
            <div class="dashboard-analytics">
                <div class="dashboard-graph">
                    <div class="dashboard-graph-title">Voucher Usage (Last 7 Days)</div>
                    <canvas id="usageChart" width="380" height="180"></canvas>
                </div>
                <div class="dashboard-table">
                    <div class="dashboard-table-title">Recent Voucher Redemptions</div>
                    <div class="table-scroll">
                    <table>
                        <thead>
                        <tr><th>Code</th><th>Minutes</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $recent = $conn->query("SELECT voucher_code, minutes, date_time, status FROM redemption_log ORDER BY date_time DESC LIMIT 6");
                        while($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Code"><?= htmlspecialchars($row['voucher_code']) ?></td>
                            <td data-label="Minutes"><?= $row['minutes'] ?></td>
                            <td data-label="Date"><?= date('M d, H:i', strtotime($row['date_time'])) ?></td>
                            <td data-label="Status"><?= $row['status'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Example data for the last 7 days (replace with PHP if needed)
    const usageLabels = [
        <?php
        for($i=6;$i>=0;$i--) {
            echo "'".date('M d', strtotime("-$i days"))."',";
        }
        ?>
    ];
    const usageData = [
        <?php
        // Get used vouchers per day for last 7 days
        for($i=6;$i>=0;$i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = $conn->query("SELECT COUNT(*) FROM redemption_log WHERE DATE(date_time)='$date' AND status='SUCCESS'")->fetch_row()[0];
            echo $count . ",";
        }
        ?>
    ];
    const ctx = document.getElementById('usageChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: usageLabels,
            datasets: [{
                label: 'Vouchers Used',
                data: usageData,
                borderColor: '#1de9b6',
                backgroundColor: 'rgba(29,233,182,0.12)',
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: '#1de9b6',
                fill: true,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#23243a' }, ticks: { color: '#fff' } },
                y: { grid: { color: '#23243a' }, ticks: { color: '#fff' }, beginAtZero: true }
            }
        }
    });
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    document.querySelectorAll('.sidebar-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 980px)').matches) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active', sidebar.classList.contains('open'));
                return;
            }
            sidebar.classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });
    });
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
    </script>
</body>
</html>
