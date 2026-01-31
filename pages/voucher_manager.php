<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
require '../includes/db.php';
// Fetch vouchers
$vouchers = $conn->query("SELECT * FROM vouchers ORDER BY date_created DESC");
// Fetch redemption log
$logs = $conn->query("SELECT * FROM redemption_log ORDER BY date_time DESC LIMIT 50");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
    <!-- Flatpickr Calendar CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    /* Flatpickr dark mode override and fix for day numbers */
    .flatpickr-calendar {
        background: #23243a;
        color: #fff;
        border: 1px solid #3a7afe;
    }
    .flatpickr-day, .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay {
        color: #fff !important;
        font-weight: 500;
    }
    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day:hover {
        background: #3a7afe;
        color: #fff !important;
    }
    .flatpickr-months .flatpickr-month {
        color: #fff;
    }
    .flatpickr-weekday {
        color: #1de9b6;
    }
    .flatpickr-day.today {
        border-color: #1de9b6;
    }
    </style>
    <!-- Flatpickr Calendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body { background: #23243a; min-height: 100vh; margin: 0; font-family: 'Manrope', 'Inter', Arial, Helvetica, sans-serif; }
        .layout { display: flex; }
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
            color: #1de9b6;
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
            background: #fff;
            color: #3a7afe;
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
        .table-scroll { width: 100%; overflow-x: auto; }
        .table-scroll table { min-width: 720px; }
        @media (min-width: 900px) {
            .main-content {
                flex-direction: column;
                align-items: flex-start;
                justify-content: flex-start;
            }
        }
        .voucher-container {
            background: #23243a;
            width: 100%;
            padding: 36px 36px 28px 36px;
            position: relative;
            overflow: visible;
            border-radius: 0;
            box-shadow: none;
            max-width: none;
            min-width: 0;
        }
        .voucher-title {
            color: #fff;
            font-size: 2rem;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-weight: 600;
            margin-bottom: 18px;
            letter-spacing: 2px;
            text-align: left;
        }
        .tab-btns {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .tab-btn {
            background: #18192b;
            color: #fff;
            border: none;
            border-radius: 6px 6px 0 0;
            padding: 10px 24px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s, color 0.18s;
        }
        .tab-btn.active, .tab-btn:hover {
            background: #3a7afe;
            color: #fff;
        }
        .btn-main {
            background: #1de9b6;
            color: #23243a;
            border: none;
            border-radius: 6px;
            padding: 10px 24px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 24px;
            margin-top: 8px;
            transition: background 0.18s, color 0.18s;
        }
        .btn-main:hover {
            background: #14b89c;
            color: #fff;
        }
        .table {
            width: 100%;
            min-width: 0;
            border-collapse: collapse;
            margin: 24px 0;
            background: #18192b;
            color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px #0002;
            table-layout: auto;
        }
        .table th, .table td {
            padding: 10px 14px;
            border-bottom: 1px solid #23243a;
        }
        .table th {
            background: #23243a;
            color: #1de9b6;
            font-weight: 600;
        }
        .table tr:last-child td { border-bottom: none; }
        .section-title { color: #fff; font-size: 2rem; margin-bottom: 24px; }
        .btn-main { background: #3a7afe; color: #fff; border: none; border-radius: 6px; padding: 10px 24px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-bottom: 24px; }
        .btn-main:hover { background: #2656c7; }
        .modal-bg { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000a; z-index: 1000; align-items: center; justify-content: center; }
        .modal-bg.active { display: flex; }
        .modal { background: #23243a; border-radius: 12px; box-shadow: 0 4px 32px #0008; padding: 32px 32px 24px 32px; min-width: 340px; max-width: 90vw; }
        .modal h3 { color: #fff; margin-top: 0; }
        .modal label { color: #fff; display: block; margin-top: 16px; }
        .modal input, .modal select { width: 100%; background: #18192b; color: #fff; border: 1px solid #3a7afe; border-radius: 6px; padding: 8px; margin-top: 6px; }
        .modal .btn-main { width: 100%; margin-top: 18px; }
        .modal .close-btn { background: none; color: #fff; border: none; font-size: 1.5rem; position: absolute; top: 12px; right: 18px; cursor: pointer; }
        .table { width: 100%; border-collapse: collapse; margin: 24px 0; background: #23243a; color: #fff; border-radius: 8px; overflow: hidden; }
        .table th, .table td { padding: 10px 14px; border-bottom: 1px solid #333; }
        .table th { background: #18192b; }
        .tab-btns { display: flex; gap: 12px; margin-bottom: 24px; }
        .tab-btn { background: #18192b; color: #fff; border: none; border-radius: 6px 6px 0 0; padding: 10px 24px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .tab-btn.active, .tab-btn:hover { background: #3a7afe; color: #fff; }
        .table th, .table td { padding: 7px 8px; }
        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title-badge {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            color: #1de9b6;
            background: rgba(29,233,182,0.08);
            border: 1px solid rgba(29,233,182,0.25);
            letter-spacing: 0.4px;
        }
        .actions-icons {
            display: inline-flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .icon-btn {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            text-decoration: none;
            transition: transform 0.1s ease, background 0.2s ease, border-color 0.2s ease;
        }
        .icon-btn:hover { transform: translateY(-1px); background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); }
        .icon-btn.pdf { background: rgba(58,122,254,0.16); border-color: rgba(58,122,254,0.4); }
        .icon-btn.print { background: rgba(29,233,182,0.16); border-color: rgba(29,233,182,0.4); color: #bff7e6; }
        .icon-btn.delete { background: rgba(220,53,69,0.2); border-color: rgba(220,53,69,0.5); }
        .icon-btn svg { width: 16px; height: 16px; }
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
                height: auto;
                min-height: 100vh;
            }
            .topbar { display: flex; }
            .topbar { position: fixed; left: 0; right: 0; top: 0; }
            .main-content { padding-top: 58px; }
            .voucher-container { padding: 24px 18px 28px; }
            .tab-btns { flex-wrap: wrap; }
        }
        @media (max-width: 640px) {
            body { font-size: 16px; }
            .voucher-title { font-size: 1.85rem; }
            .page-title-badge { font-size: 12px; padding: 5px 10px; }
            .tab-btn { font-size: 0.95rem; padding: 10px 16px; }
            .btn-main { width: 100%; font-size: 1rem; padding: 12px 16px; }
            .table { background: transparent; box-shadow: none; }
            .table thead { display: none; }
            .table tr {
                display: block;
                margin-bottom: 14px;
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 12px;
                background: #1a1c2d;
                padding: 10px 12px;
            }
            .table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                padding: 10px 4px;
                border-bottom: 1px dashed rgba(255,255,255,0.06);
                font-size: 0.95rem;
            }
            .table td:last-child { border-bottom: none; }
            .table td::before {
                content: attr(data-label);
                color: #8aa0c6;
                font-weight: 600;
                font-size: 0.82rem;
                letter-spacing: 0.2px;
                flex: 0 0 120px;
                text-transform: uppercase;
            }
            .actions-icons { justify-content: flex-start; }
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="layout">
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
                        <a href="dashboard.php" class="sidebar-link" style="margin-top:8px;display:flex;align-items:center;gap:16px;padding:12px 28px;">
                                <span class="icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M4 12L2 14m0 0l10-10 10 10m-2-2v6a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-6m0 0L2 14"/>
                                    </svg>
                                </span>
                                <span>Dashboard</span>
                        </a>
            <a href="voucher_manager.php" class="sidebar-link active" style="display:flex;align-items:center;gap:16px;padding:12px 28px;">
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
        <div style="width:100%;display:block;">
            <div class="voucher-container">
            <div class="voucher-title page-title">Voucher Manager <span class="page-title-badge">ADMIN</span></div>
            <div class="tab-btns">
                <button class="tab-btn active" data-tab="vouchers" onclick="showTab('vouchers')">Vouchers</button>
                <button class="tab-btn" data-tab="logs" onclick="showTab('logs')">Redemption Log</button>
            </div>
            <div id="vouchers-tab">
                <button class="btn-main" onclick="openModal()">+ Create Voucher</button>
                <div class="table-scroll">
      <table class="table" style="width:100%;margin:0;">
        <thead>
        <tr>
          <th style="text-align:center;">Code</th>
          <th style="text-align:center;">Minutes</th>
          <th style="text-align:center;">Status</th>
          <th style="text-align:center;">Date Created</th>
          <th style="text-align:center;">Date Used</th>
          <th style="text-align:center;">Expiry</th>
          <th style="text-align:center;">QR</th>
          <th style="text-align:center;">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php while($row = $vouchers->fetch_assoc()): ?>
        <tr id="voucher-row-<?= htmlspecialchars($row['code']) ?>">
          <td data-label="Code" style="text-align:center;vertical-align:middle;"><?= htmlspecialchars($row['code']) ?></td>
          <td data-label="Minutes" style="text-align:center;vertical-align:middle;"><?= $row['minutes'] ?></td>
          <td data-label="Status" style="text-align:center;vertical-align:middle;"><?= $row['status'] ?></td>
          <td data-label="Date Created" style="text-align:center;vertical-align:middle;"><?= $row['date_created'] ?></td>
          <td data-label="Date Used" style="text-align:center;vertical-align:middle;"><?= $row['date_used'] ?></td>
          <td data-label="Expiry" style="text-align:center;vertical-align:middle;"><?= $row['expiry_date'] ?></td>
          <td data-label="QR" style="text-align:center;vertical-align:middle;"><a href="/qrcodes/<?= urlencode($row['qr_image']) ?>" download style="color:#1de9b6;font-weight:600;">QR</a></td>
          <td data-label="Actions" style="text-align:center;vertical-align:middle;">
            <div class="actions-icons">
              <a class="icon-btn pdf" href="download_pdf.php?code=<?= urlencode($row['code']) ?>" title="Download PDF" aria-label="Download PDF">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>
              </a>
              <a class="icon-btn print" href="print_voucher.php?code=<?= urlencode($row['code']) ?>" title="Print" aria-label="Print">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18h12v4H6z"/><path d="M6 14h12"/><path d="M4 12h16"/></svg>
              </a>
              <a class="icon-btn delete" href="#" title="Delete" aria-label="Delete" onclick="return confirmDeleteAjax('<?= htmlspecialchars($row['code']) ?>', document.getElementById('voucher-row-<?= htmlspecialchars($row['code']) ?>'))">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6v12"/><path d="M16 6v12"/><path d="M5 6l1 14h12l1-14"/><path d="M10 6l1-2h2l1 2"/></svg>
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
            </div>
            <div id="logs-tab" style="display:none;">
                <div class="table-scroll">
                <table class="table">
                    <thead>
                    <tr><th>Voucher Code</th><th>Minutes</th><th>Date & Time</th><th>Source</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Voucher Code"><?= htmlspecialchars($row['voucher_code']) ?></td>
                        <td data-label="Minutes"><?= $row['minutes'] ?></td>
                        <td data-label="Date & Time"><?= $row['date_time'] ?></td>
                        <td data-label="Source"><?= $row['source'] ?></td>
                        <td data-label="Status"><?= $row['status'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <div class="modal-bg" id="modalBg">
            <div class="modal" style="position:relative;">
                <button class="close-btn" onclick="closeModal()">&times;</button>
                <h3 style="color:#fff;">Create Voucher</h3>
                <form method="post" action="voucher_manager.php" autocomplete="off">
                    <label style="color:#fff;">Minutes</label>
                    <input type="number" name="minutes" min="1" max="1440" step="1" placeholder="Enter minutes" required style="width:100%;background:#18192b;color:#fff;border:1px solid #3a7afe;border-radius:6px;padding:8px;margin-top:6px;">
                    <label style="color:#fff;">Expiry Date</label>
                    <input id="expiryDatePicker" type="text" name="expiry" placeholder="Select date" readonly>
                    <button class="btn-main" type="submit">Create</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function openModal() {
    document.getElementById('modalBg').classList.add('active');
}
function closeModal() {
    document.getElementById('modalBg').classList.remove('active');
}
function showTab(tab) {
    document.getElementById('vouchers-tab').style.display = (tab === 'vouchers') ? '' : 'none';
    document.getElementById('logs-tab').style.display = (tab === 'logs') ? '' : 'none';
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
}
// SweetAlert for delete (AJAX)
function confirmDeleteAjax(code, rowElem) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'Delete this voucher?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete_voucher.php?code=' + encodeURIComponent(code))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({icon:'success',title:'Voucher deleted!',timer:1200,showConfirmButton:false});
                        if(rowElem) rowElem.remove();
                    } else {
                        Swal.fire({icon:'error',title:data.message||'Delete failed!'});
                    }
                })
                .catch(()=>Swal.fire({icon:'error',title:'Delete failed!'}));
        }
    });
    return false;
}
// Initialize Flatpickr for expiry date
flatpickr("#expiryDatePicker", {
    dateFormat: "Y-m-d",
    minDate: "today",
    disableMobile: true
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
<?php
// Handle create voucher POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['minutes'])) {
    $code = strtoupper(bin2hex(random_bytes(4)));
    $minutes = intval($_POST['minutes']);
    $expiry = $_POST['expiry'] ? date('Y-m-d', strtotime($_POST['expiry'])) : null;
    $qr_image = $code . '.png';
    $stmt = $conn->prepare("INSERT INTO vouchers (code, minutes, expiry_date, qr_image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('siss', $code, $minutes, $expiry, $qr_image);
      if ($stmt->execute()) {
          require_once '../vendor/phpqrcode/qrlib.php';
          $qrDir = __DIR__ . '/../qrcodes';
          if (!is_dir($qrDir)) {
              mkdir($qrDir, 0775, true);
          }
          QRcode::png($code, $qrDir . '/' . $qr_image, QR_ECLEVEL_L, 6);
          echo "<script>Swal.fire({icon: 'success',title: 'Voucher created!',showConfirmButton: false,timer: 1200}).then(()=>{window.location='voucher_manager.php';});</script>";
          exit;
      } else {
        echo "<script>Swal.fire({icon: 'error',title: 'Error creating voucher.'});</script>";
    }
}
?>
</body>
</html>
