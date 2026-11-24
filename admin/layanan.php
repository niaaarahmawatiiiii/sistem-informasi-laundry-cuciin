<?php
// admin/layanan.php - CRUD Layanan
 $pageTitle = 'Kelola Layanan - Admin Panel';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
requireLogin(); requireAdmin();

 $flash = getFlash();
 $action = $_GET['action'] ?? 'list';
 $editId = (int)($_GET['edit'] ?? 0);

// ===== DETECT KOLOM LAYANAN =====
 $lCols = [];
try {
    $lc = $db->query("SHOW COLUMNS FROM layanan");
    if ($lc) $lCols = array_column($lc->fetch_all(MYSQLI_ASSOC), 'Field');
} catch (Exception $e) {}

function pickCol($cols, $candidates) {
    foreach ($candidates as $c) if (in_array($c, $cols)) return $c;
    return $candidates[0];
}

 $colId       = pickCol($lCols, ['id_layanan','id','layanan_id']);
 $colNama     = pickCol($lCols, ['nama_layanan','nama','name','layanan']);
 $colJenis    = pickCol($lCols, ['jenis','type','tipe','kategori']);
 $colHarga    = pickCol($lCols, ['harga','harga_per_kg','price','biaya']);
 $colSatuan   = pickCol($lCols, ['satuan','unit']);
 $colDesc     = pickCol($lCols, ['deskripsi','description','desc','keterangan']);
 $colIcon     = pickCol($lCols, ['icon','emoji','ikon']);
 $colActive   = in_array('is_active', $lCols) ? 'is_active' : '';

// ===== HANDLE POST (Add/Edit) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $nama      = trim($_POST['nama_layanan'] ?? '');
    $jenis     = $_POST['jenis'] ?? 'jasa';
    $harga     = (float)($_POST['harga'] ?? 0);
    $satuan    = trim($_POST['satuan'] ?? 'kg');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $icon      = trim($_POST['icon'] ?? '🧺');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$nama || $harga <= 0) {
        setFlash('danger', 'Nama dan harga wajib diisi dengan benar.');
    } elseif ($id > 0) {
        if ($colActive) {
            $stmt = $db->prepare("UPDATE layanan SET $colNama=?, $colJenis=?, $colHarga=?, $colSatuan=?, $colDesc=?, $colIcon=?, $colActive=? WHERE $colId=?");
            $stmt->bind_param('ssdsssii', $nama, $jenis, $harga, $satuan, $deskripsi, $icon, $is_active, $id);
        } else {
            $stmt = $db->prepare("UPDATE layanan SET $colNama=?, $colJenis=?, $colHarga=?, $colSatuan=?, $colDesc=?, $colIcon=? WHERE $colId=?");
            $stmt->bind_param('ssdsssi', $nama, $jenis, $harga, $satuan, $deskripsi, $icon, $id);
        }
        $stmt->execute();
        setFlash('success', 'Layanan berhasil diperbarui.');
    } else {
        if ($colActive) {
            $stmt = $db->prepare("INSERT INTO layanan ($colNama, $colJenis, $colHarga, $colSatuan, $colDesc, $colIcon, $colActive) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('ssdsssii', $nama, $jenis, $harga, $satuan, $deskripsi, $icon, $is_active);
        } else {
            $stmt = $db->prepare("INSERT INTO layanan ($colNama, $colJenis, $colHarga, $colSatuan, $colDesc, $colIcon) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssdsss', $nama, $jenis, $harga, $satuan, $deskripsi, $icon);
        }
        $stmt->execute();
        setFlash('success', 'Layanan baru berhasil ditambahkan.');
    }
    header('Location: layanan.php');
    exit;
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($colActive) {
        $db->query("UPDATE layanan SET $colActive=0 WHERE $colId=$delId");
        setFlash('success', 'Layanan berhasil dinonaktifkan.');
    } else {
        $db->query("DELETE FROM layanan WHERE $colId=$delId");
        setFlash('success', 'Layanan berhasil dihapus.');
    }
    header('Location: layanan.php');
    exit;
}

// ===== HANDLE TOGGLE ACTIVE =====
if (isset($_GET['toggle'])) {
    $togId = (int)$_GET['toggle'];
    if ($colActive) {
        $cur = $db->query("SELECT $colActive FROM layanan WHERE $colId=$togId")->fetch_assoc();
        if ($cur) {
            $newVal = $cur[$colActive] ? 0 : 1;
            $db->query("UPDATE layanan SET $colActive=$newVal WHERE $colId=$togId");
            setFlash('success', $newVal ? 'Layanan diaktifkan.' : 'Layanan dinonaktifkan.');
        }
    }
    header('Location: layanan.php');
    exit;
}

// ===== FETCH EDIT DATA =====
 $editData = [];
if ($editId) {
    $er = $db->query("SELECT * FROM layanan WHERE $colId=$editId");
    if ($er) $editData = $er->fetch_assoc() ?? [];
}

// ===== FETCH ALL =====
 $allLayanan = [];
 $ar = $db->query("SELECT * FROM layanan ORDER BY $colJenis, " . ($colActive ? "$colActive DESC, " : "") . "$colId");
if ($ar) $allLayanan = $ar->fetch_all(MYSQLI_ASSOC);

// Count stats
 $activeCount = 0;
 $inactiveCount = 0;
foreach ($allLayanan as $l) {
    if ($colActive) {
        if ($l[$colActive]) $activeCount++; else $inactiveCount++;
    } else {
        $activeCount++;
    }
}
 $jasaCount = 0;
 $selfCount = 0;
foreach ($allLayanan as $l) {
    if (($l[$colJenis] ?? '') === 'jasa') $jasaCount++; else $selfCount++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#fff5f7;--bg2:#ffedf1;--bg3:#ffe4ec;
            --pk1:#be185d;--pk2:#db2777;--pk3:#ec4899;--pk4:#f472b6;
            --t1:#4a1028;--t2:#6b2040;--t3:#9b4068;--t4:#c48098;
            --bd:rgba(190,24,93,.08);--bd2:rgba(190,24,93,.15);
            --card:#fff;--shadow:0 2px 16px rgba(190,24,93,.06);
            --shadow-lg:0 8px 32px rgba(190,24,93,.1);
            --radius:14px;--radius-lg:20px;
            --sidebar-w:260px;
            --transition:all .3s cubic-bezier(.4,0,.2,1);
        }
        body{font-family:'Plus Jakarta Sans',-apple-system,sans-serif;background:var(--bg);color:var(--t1);line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit;transition:var(--transition)}
        ::selection{background:var(--pk3);color:#fff}
        ::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--bg2)}::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--pk3),var(--pk1));border-radius:10px}
        .dashboard-layout{display:flex;min-height:100vh}

        /* ===== SIDEBAR ===== */
        .sidebar{width:var(--sidebar-w);min-height:100vh;position:fixed;left:0;top:0;bottom:0;z-index:100;background:linear-gradient(180deg,#3a0a1e 0%,#2d0718 40%,#1a0410 100%);border-right:1px solid rgba(249,168,212,.08);display:flex;flex-direction:column;transition:var(--transition)}
        .sidebar::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(249,168,212,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(249,168,212,.02) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
        .sidebar-brand{padding:1.5rem 1.25rem;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(249,168,212,.08);position:relative}
        .brand-icon{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--pk3),var(--pk1));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 14px rgba(236,72,153,.3)}
        .sidebar-brand span{color:#fff;font-weight:800;font-size:1rem}
        .sidebar-brand em{color:#f9a8d4;font-style:normal}
        .sidebar-nav{flex:1;padding:.75rem;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
        .sidebar-section{display:flex;flex-direction:column;gap:2px}
        .sidebar-section+.sidebar-section{margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(249,168,212,.06)}
        .sidebar-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,.5);font-size:.82rem;font-weight:600;transition:var(--transition);position:relative}
        .sidebar-link:hover{color:rgba(255,255,255,.85);background:rgba(249,168,212,.06)}
        .sidebar-link.active{color:#fff;background:linear-gradient(135deg,rgba(236,72,153,.2),rgba(190,24,93,.1));border:1px solid rgba(236,72,153,.15);box-shadow:0 2px 12px rgba(236,72,153,.1)}
        .sidebar-link.active .link-icon{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;box-shadow:0 2px 8px rgba(236,72,153,.3)}
        .link-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(249,168,212,.06);font-size:.78rem;transition:var(--transition);flex-shrink:0}
        .sidebar-link.danger-link:hover{color:#fca5a5;background:rgba(239,68,68,.08)}
        .sidebar-user{padding:1rem 1.25rem;border-top:1px solid rgba(249,168,212,.08);position:relative}
        .sidebar-user-info{display:flex;align-items:center;gap:10px}
        .user-avatar{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.8rem;flex-shrink:0}
        .user-name{color:#fff;font-weight:700;font-size:.8rem}
        .user-role{color:rgba(249,168,212,.5);font-size:.68rem;font-weight:500}
        .mobile-toggle{display:none;position:fixed;top:14px;left:14px;z-index:200;width:42px;height:42px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;font-size:1.1rem;cursor:pointer;box-shadow:0 4px 16px rgba(236,72,153,.3)}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99}

        /* ===== MAIN ===== */
        .dashboard-content{flex:1;margin-left:var(--sidebar-w);padding:2rem 2.5rem;min-height:100vh;position:relative}
        .dashboard-content::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(249,168,212,.08),transparent 70%);pointer-events:none;z-index:0}

        /* ===== ALERT ===== */
        .alert{padding:.85rem 1.25rem;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;animation:alertIn .4s ease}
        @keyframes alertIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.15)}
        .alert-danger{background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.15)}

        /* ===== PAGE HEADER ===== */
        .page-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;position:relative;z-index:1}
        .page-title{font-size:1.5rem;font-weight:900;color:var(--t1);display:flex;align-items:center;gap:10px}
        .page-title i{color:var(--pk3)}
        .page-sub{font-size:.82rem;color:var(--t4);margin-top:2px}

        /* ===== MINI STATS ===== */
        .mini-stats{display:flex;gap:.75rem;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.5rem}
        .mini-stat{display:flex;align-items:center;gap:8px;padding:8px 16px;background:var(--card);border:1px solid var(--bd);border-radius:10px;box-shadow:var(--shadow);font-size:.78rem;font-weight:600}
        .mini-stat i{font-size:.75rem}
        .mini-stat.ms-total i{color:var(--pk3)}
        .mini-stat.ms-active i{color:#10b981}
        .mini-stat.ms-inactive i{color:#94a3b8}
        .mini-stat.ms-jasa i{color:#3b82f6}
        .mini-stat.ms-self i{color:#f59e0b}
        .mini-stat b{color:var(--t1)}
        .mini-stat span{color:var(--t4)}

        /* ===== CARD ===== */
        .card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;transition:var(--transition)}
        .card-header{padding:1rem 1.4rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
        .card-title{font-size:.92rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}
        .card-title i{color:var(--pk3);font-size:.85rem}

        /* ===== SEARCH ===== */
        .search-box{position:relative;width:260px}
        .search-box input{width:100%;padding:8px 14px 8px 36px;border:1px solid var(--bd);border-radius:10px;font-size:.8rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--transition);outline:none}
        .search-box input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)}
        .search-box input::placeholder{color:var(--t4)}
        .search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--t4);font-size:.78rem}

        /* ===== TABLE ===== */
        .table-wrapper{overflow-x:auto}
        .table{width:100%;border-collapse:collapse}
        .table th{text-align:left;padding:12px 16px;font-size:.7rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--bd);background:var(--bg)}
        .table td{padding:14px 16px;font-size:.82rem;border-bottom:1px solid var(--bd);vertical-align:middle}
        .table tbody tr{transition:var(--transition)}
        .table tbody tr:hover{background:rgba(190,24,93,.02)}
        .table tbody tr:last-child td{border-bottom:none}
        .table tbody tr.row-inactive{opacity:.5}
        .table tbody tr.row-inactive:hover{opacity:.75}

        .layanan-cell{display:flex;align-items:center;gap:12px}
        .layanan-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;background:rgba(190,24,93,.06);border:1px solid var(--bd);flex-shrink:0;transition:var(--transition)}
        tr:hover .layanan-icon{transform:scale(1.08);background:rgba(190,24,93,.1)}
        .layanan-info{min-width:0}
        .layanan-name{font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px}
        .layanan-desc{font-size:.72rem;color:var(--t4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;margin-top:1px}

        .type-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:8px;font-size:.7rem;font-weight:700}
        .type-jasa{color:#3b82f6;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.12)}
        .type-self{color:#f59e0b;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.12)}
        .type-jasa i,.type-self i{font-size:.6rem}

        .price-cell{font-weight:800;color:var(--pk1);font-size:.9rem}
        .satuan-cell{color:var(--t4);font-size:.78rem;font-weight:500}

        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:20px;font-size:.7rem;font-weight:700}
        .status-pill.s-active{color:#059669;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.12)}
        .status-pill.s-inactive{color:#94a3b8;background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.1)}
        .status-dot{width:6px;height:6px;border-radius:50%}
        .s-active .status-dot{background:#10b981}
        .s-inactive .status-dot{background:#94a3b8}

        .action-btns{display:flex;gap:6px}
        .act-btn{width:34px;height:34px;border-radius:9px;border:1px solid var(--bd);background:var(--card);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.75rem;transition:var(--transition)}
        .act-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
        .act-btn.act-edit{color:#3b82f6;border-color:rgba(59,130,246,.15)}
        .act-btn.act-edit:hover{background:rgba(59,130,246,.08);border-color:rgba(59,130,246,.25)}
        .act-btn.act-toggle{color:#f59e0b;border-color:rgba(245,158,11,.15)}
        .act-btn.act-toggle:hover{background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.25)}
        .act-btn.act-delete{color:#ef4444;border-color:rgba(239,68,68,.12)}
        .act-btn.act-delete:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25)}

        /* ===== BTN ===== */
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;font-weight:700;font-size:.82rem;border:none;cursor:pointer;transition:var(--transition);font-family:inherit}
        .btn-primary{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;box-shadow:0 4px 14px rgba(236,72,153,.25)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.35)}
        .btn-light{background:var(--bg2);color:var(--t2);border:1px solid var(--bd)}
        .btn-light:hover{background:var(--bg3);border-color:var(--bd2)}

        /* ===== MODAL ===== */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,4,16,.45);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:1rem}
        .modal-overlay.open{display:flex}
        .modal-box{background:var(--card);border-radius:var(--radius-lg);padding:0;max-width:580px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 24px 60px rgba(26,4,16,.3);animation:modalIn .35s cubic-bezier(.16,1,.3,1)}
        @keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
        .modal-head{padding:1.4rem 1.6rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
        .modal-head h2{font-size:1.15rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}
        .modal-head h2 i{color:var(--pk3);font-size:.95rem}
        .modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t4);font-size:.8rem;transition:var(--transition)}
        .modal-close:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.15);color:#ef4444}
        .modal-body{padding:1.6rem;overflow-y:auto;max-height:calc(90vh - 140px)}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .form-group{display:flex;flex-direction:column;gap:5px}
        .form-group.full{grid-column:1/-1}
        .form-label{font-size:.78rem;font-weight:700;color:var(--t2)}
        .form-input{padding:10px 14px;border:1.5px solid var(--bd);border-radius:10px;font-size:.85rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--transition);outline:none}
        .form-input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)}
        .form-input::placeholder{color:var(--t4)}
        select.form-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23c48098' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
        textarea.form-input{resize:vertical;min-height:80px}
        .checkbox-group{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg);border:1.5px solid var(--bd);border-radius:10px;cursor:pointer;transition:var(--transition)}
        .checkbox-group:hover{border-color:var(--bd2)}
        .checkbox-group input[type="checkbox"]{width:18px;height:18px;accent-color:var(--pk3);cursor:pointer}
        .checkbox-group label{font-size:.82rem;font-weight:600;color:var(--t2);cursor:pointer}
        .modal-foot{padding:1.2rem 1.6rem;border-top:1px solid var(--bd);display:flex;gap:.75rem;justify-content:flex-end}
        .btn-cancel{padding:10px 20px;border-radius:10px;font-weight:700;font-size:.82rem;border:1.5px solid var(--bd);background:var(--card);color:var(--t3);cursor:pointer;font-family:inherit;transition:var(--transition)}
        .btn-cancel:hover{background:var(--bg2);border-color:var(--bd2)}
        .btn-submit{padding:10px 24px;border-radius:10px;font-weight:700;font-size:.82rem;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(236,72,153,.25);transition:var(--transition)}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.35)}

        /* ===== EMPTY ===== */
        .empty{text-align:center;padding:3rem 1rem;color:var(--t4)}
        .empty i{font-size:2.5rem;margin-bottom:.75rem;opacity:.25;display:block}
        .empty p{font-size:.85rem}

        /* ===== RESPONSIVE ===== */
        @media(max-width:768px){
            .mobile-toggle{display:flex;align-items:center;justify-content:center}
            .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
            .sidebar-overlay.open{display:block}
            .dashboard-content{margin-left:0;padding:1.2rem;padding-top:64px}
            .page-header{flex-direction:column;align-items:flex-start}
            .card-header{flex-direction:column;align-items:flex-start}
            .search-box{width:100%}
            .form-grid{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="dashboard-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-icon">⚙️</div><span>Admin <em>Panel</em></span></div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <a href="dashboard.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard</a>
                <a href="orders.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-box"></i></span> Pesanan</a>
                <a href="layanan.php" class="sidebar-link active"><span class="link-icon"><i class="fa-solid fa-shirt"></i></span> Layanan</a>
                <a href="users.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-users"></i></span> Users</a>
                <a href="payments.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-credit-card"></i></span> Pembayaran</a>
                <a href="reports.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-line"></i></span> Laporan</a>
            </div>
            <div class="sidebar-section">
                <a href="<?= APP_URL ?>/index.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-globe"></i></span> Ke Website</a>
                <a href="<?= APP_URL ?>/logout.php" class="sidebar-link danger-link"><span class="link-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Keluar</a>
            </div>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <?php $sessName = $_SESSION['nama'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? $_SESSION['fullname'] ?? $_SESSION['user_name'] ?? 'Admin'; ?>
<div class="user-avatar" style="background:linear-gradient(135deg,var(--pk3),var(--pk1))"><?= strtoupper(substr($sessName,0,1)) ?></div>
<div><div class="user-name"><?= htmlspecialchars($sessName) ?></div><div class="user-role">Administrator</div></div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="dashboard-content">
        <?php if($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <i class="fa-solid <?= $flash['type']==='success'?'fa-circle-check':'fa-circle-xmark' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="page-title"><i class="fa-solid fa-shirt"></i> Kelola Layanan</div>
                <div class="page-sub">Kelola semua layanan laundry yang tersedia</div>
            </div>
            <button onclick="openModal()" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Tambah Layanan
            </button>
        </div>

        <!-- MINI STATS -->
        <div class="mini-stats">
            <div class="mini-stat ms-total">
                <i class="fa-solid fa-layer-group"></i>
                <b><?= count($allLayanan) ?></b> <span>Total</span>
            </div>
            <div class="mini-stat ms-active">
                <i class="fa-solid fa-circle-check"></i>
                <b><?= $activeCount ?></b> <span>Aktif</span>
            </div>
            <?php if($colActive): ?>
            <div class="mini-stat ms-inactive">
                <i class="fa-solid fa-circle-pause"></i>
                <b><?= $inactiveCount ?></b> <span>Nonaktif</span>
            </div>
            <?php endif; ?>
            <div class="mini-stat ms-jasa">
                <i class="fa-solid fa-hand-sparkles"></i>
                <b><?= $jasaCount ?></b> <span>Jasa</span>
            </div>
            <div class="mini-stat ms-self">
                <i class="fa-solid fa-washing-machine"></i>
                <b><?= $selfCount ?></b> <span>Self Service</span>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card" style="position:relative;z-index:1;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-list"></i> Daftar Layanan</div>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Cari layanan..." oninput="filterTable()">
                </div>
            </div>
            <div class="table-wrapper">
                <?php if(!empty($allLayanan)): ?>
                <table class="table" id="layananTable">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Layanan</th>
                            <th>Jenis</th>
                            <th>Harga</th>
                            <th>Satuan</th>
                            <th>Status</th>
                            <th style="width:130px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($allLayanan as $idx => $l):
                        $isActive = $colActive ? $l[$colActive] : 1;
                        $rowClass = !$isActive ? 'row-inactive' : '';
                    ?>
                    <tr class="<?= $rowClass ?>" data-search="<?= strtolower($l[$colNama] . ' ' . $l[$colJenis] . ' ' . $l[$colDesc]) ?>">
                        <td style="font-weight:700;color:var(--t4);font-size:.78rem"><?= $idx + 1 ?></td>
                        <td>
                            <div class="layanan-cell">
                                <div class="layanan-icon"><?= $l[$colIcon] ?? '🧺' ?></div>
                                <div class="layanan-info">
                                    <div class="layanan-name"><?= htmlspecialchars($l[$colNama]) ?></div>
                                    <div class="layanan-desc"><?= htmlspecialchars($l[$colDesc] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="type-badge <?= ($l[$colJenis]==='jasa') ? 'type-jasa' : 'type-self' ?>">
                                <i class="fa-solid <?= ($l[$colJenis]==='jasa') ? 'fa-hand-sparkles' : 'fa-washing-machine' ?>"></i>
                                <?= ucfirst($l[$colJenis]) ?>
                            </span>
                        </td>
                        <td><span class="price-cell"><?= formatRupiah($l[$colHarga]) ?></span></td>
                        <td><span class="satuan-cell"><?= htmlspecialchars($l[$colSatuan] ?? '-') ?></span></td>
                        <td>
                            <span class="status-pill <?= $isActive ? 's-active' : 's-inactive' ?>">
                                <span class="status-dot"></span>
                                <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="act-btn act-edit" title="Edit" onclick='openEdit(<?= htmlspecialchars(json_encode($l)) ?>)'>
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if($colActive): ?>
                                <a href="?toggle=<?= $l[$colId] ?>" class="act-btn act-toggle" title="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i class="fa-solid <?= $isActive ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?delete=<?= $l[$colId] ?>" onclick="return confirm('Yakin ingin menghapus layanan ini?')" class="act-btn act-delete" title="Hapus">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <i class="fa-solid fa-shirt"></i>
                    <p>Belum ada layanan. Klik "Tambah Layanan" untuk memulai.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="modalTitle"><i class="fa-solid fa-plus"></i> Tambah Layanan</h2>
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="layananForm">
            <input type="hidden" name="id" id="fId" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Nama Layanan *</label>
                        <input type="text" name="nama_layanan" id="fNama" class="form-input" placeholder="Contoh: Cuci Setrika Express" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jenis Layanan</label>
                        <select name="jenis" id="fJenis" class="form-input">
                            <option value="jasa">Jasa Laundry</option>
                            <option value="self-service">Self Service</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon (Emoji)</label>
                        <input type="text" name="icon" id="fIcon" class="form-input" value="🧺" placeholder="🧺">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga (Rp) *</label>
                        <input type="number" name="harga" id="fHarga" class="form-input" min="0" step="500" placeholder="8000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="satuan" id="fSatuan" class="form-input" value="kg" placeholder="kg / pcs / sesi">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" id="fDesc" class="form-input" rows="3" placeholder="Deskripsi singkat layanan..."></textarea>
                    </div>
                    <?php if($colActive): ?>
                    <div class="form-group full">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="fActive" value="1" checked>
                            <label for="fActive">Layanan aktif dan tampil di website</label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-submit"><i class="fa-solid fa-check"></i> Simpan Layanan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Layanan';
    document.getElementById('layananForm').reset();
    document.getElementById('fId').value = '';
    document.getElementById('fIcon').value = '🧺';
    document.getElementById('fSatuan').value = 'kg';
    <?php if($colActive): ?>document.getElementById('fActive').checked = true;<?php endif; ?>
    document.getElementById('modalOverlay').classList.add('open');
}

function openEdit(data) {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Layanan';
    document.getElementById('fId').value     = data.<?= $colId ?>;
    document.getElementById('fNama').value   = data.<?= $colNama ?>;
    document.getElementById('fJenis').value  = data.<?= $colJenis ?>;
    document.getElementById('fIcon').value   = data.<?= $colIcon ?> || '🧺';
    document.getElementById('fHarga').value  = data.<?= $colHarga ?>;
    document.getElementById('fSatuan').value = data.<?= $colSatuan ?> || 'kg';
    document.getElementById('fDesc').value   = data.<?= $colDesc ?> || '';
    <?php if($colActive): ?>document.getElementById('fActive').checked = data.<?= $colActive ?> == 1;<?php endif; ?>
    document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function filterTable() {
    var q = document.getElementById('searchInput').value.toLowerCase();
    var rows = document.querySelectorAll('#layananTable tbody tr');
    rows.forEach(function(row) {
        var text = row.getAttribute('data-search') || '';
        row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
    });
}

<?php if($action === 'add'): ?>
openModal();
<?php elseif($editId && !empty($editData)): ?>
openEdit(<?= htmlspecialchars(json_encode($editData)) ?>);
<?php endif; ?>

function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
window.addEventListener('resize',function(){
    if(window.innerWidth>768){
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
    }
});
</script>
</body>
</html>