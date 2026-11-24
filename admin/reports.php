<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

 $pageTitle = 'Laporan & Statistik - Admin Panel';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

// Cek Login
if (!isset($_SESSION['role'])) {
    die("<h3>Akses Ditolak</h3>Anda harus login sebagai Admin.");
}

 $flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

// ===== FUNGSI PENUNJANG =====
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}
if (!function_exists('array_column')) {
    function array_column(array $array, $column_key) {
        return array_map(function($e) use ($column_key) { 
            return isset($e[$column_key]) ? $e[$column_key] : null; 
        }, $array);
    }
}

// ===== HELPERS =====
function sv($db, $sql, $def = 0) {
    try {
        $r = $db->query($sql);
        if ($r && $row = $r->fetch_assoc()) return (float)$row['t'];
    } catch (Exception $e) {}
    return $def;
}
function sq($db, $sql) {
    try { 
        $r = $db->query($sql); 
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : []; 
    }
    catch (Exception $e) { return []; }
}

// ===== DETEKSI KOLOM =====
 $oIdCol = 'id_order'; $oUserCol = 'id_user'; $oTotalCol = 'total_harga';
 $oStatusCol = 'status'; $oDateCol = 'tanggal_order'; $oKodeCol = 'kode_order';

 $uIdCol = 'id_user'; $uNameCol = 'nama';

 $lIdCol = 'id_layanan'; $lNameCol = 'nama_layanan';

 $odIdCol = 'id_layanan'; $odOrderCol = 'id_order';
 $odQty   = 'qty'; $odSub   = 'subtotal';

 $pIdCol     = 'id_pembayaran'; $pOrderCol  = 'id_order';
 $pMethodCol = 'metode_pembayaran'; $pStatusCol = 'status_pembayaran';
 $pAmountCol  = 'jumlah'; $pDateCol    = 'tanggal_bayar';

// Periode Filter (Default 'all' agar langsung muncul semua data)
 $period = $_GET['period'] ?? 'all'; 
 $dateFrom = $_GET['from'] ?? '';
 $dateTo = $_GET['to'] ?? '';

if ($dateFrom && $dateTo) {
    $period = 'custom';
} else {
    $periods = ['today'=>0,'7'=>7,'30'=>30,'90'=>90,'365'=>365];
    $days = isset($periods[$period]) ? $periods[$period] : 365;
    
    if ($period === 'today') {
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
    } elseif ($period === 'all') {
        $dateFrom = '2023-01-01'; 
        $dateTo = date('Y-m-d');
    } else {
        $dateTo = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime("-$days days"));
    }
}

// Previous Date untuk Growth
 $prevFrom = ''; $prevTo = '';
if ($period !== 'all' && $period !== 'today') {
    $diffDays = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
    $prevFrom = date('Y-m-d', strtotime("-" . $diffDays . " days", strtotime($dateFrom)));
    $prevTo = date('Y-m-d', strtotime("-1 day", strtotime($dateFrom)));
}

 $escFrom = $db->real_escape_string($dateFrom);
 $escTo = $db->real_escape_string($dateTo);
 $escPrevFrom = isset($prevFrom) ? $db->real_escape_string($prevFrom) : '';
 $escPrevTo = isset($prevTo) ? $db->real_escape_string($prevTo) : '';

// ===== STATISTIK UTAMA =====
 $totalRevenue = 0; $totalOrders = 0; $avgOrder = 0; $totalCustomers = 0;
 $revenueGrowth = 0; $orderGrowth = 0; $prevRevenue = 0; $prevOrders = 0;

// Revenue
if ($period === 'all') {
    $totalRevenue = sv($db, "SELECT COALESCE(SUM(p.$pAmountCol),0) as t FROM pembayaran p WHERE p.$pStatusCol='dibayar'", 0);
    $totalOrders = (int)sv($db, "SELECT COUNT(*) as t FROM orders o", 0);
    $totalCustomers = (int)sv($db, "SELECT COUNT(DISTINCT o.$oUserCol) as t FROM orders o", 0);
} else {
    $totalRevenue = sv($db, "SELECT COALESCE(SUM(p.$pAmountCol),0) as t FROM pembayaran p WHERE p.$pStatusCol='dibayar' AND DATE(p.$pDateCol) BETWEEN '$escFrom' AND '$escTo'", 0);
    $totalOrders = (int)sv($db, "SELECT COUNT(*) as t FROM orders o WHERE DATE(o.$oDateCol) BETWEEN '$escFrom' AND '$escTo'", 0);
    $totalCustomers = (int)sv($db, "SELECT COUNT(DISTINCT o.$oUserCol) as t FROM orders o WHERE DATE(o.$oDateCol) BETWEEN '$escFrom' AND '$escTo'", 0);
}
 $avgOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Growth
if ($period !== 'all' && $period !== 'today') {
    $prevRevenue = sv($db, "SELECT COALESCE(SUM(p.$pAmountCol),0) as t FROM pembayaran p WHERE p.$pStatusCol='dibayar' AND DATE(p.$pDateCol) BETWEEN '$escPrevFrom' AND '$escPrevTo'", 0);
    $prevOrders = (int)sv($db, "SELECT COUNT(*) as t FROM orders o WHERE DATE(o.$oDateCol) BETWEEN '$escPrevFrom' AND '$escPrevTo'", 0);
    $revenueGrowth = $prevRevenue > 0 ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100 : 0;
    $orderGrowth = $prevOrders > 0 ? (($totalOrders - $prevOrders) / $prevOrders) * 100 : 0;
}

// ===== REVENUE HARIAN =====
 $dailyRevenue = [];
if ($period === 'all') {
    // Jika semua, tampilkan 30 hari terakhir saja agar chart tidak kejebak
    $limitDate = date('Y-m-d', strtotime("-29 days"));
    $limitTo = date('Y-m-d');
    $raw = sq($db, "SELECT DATE(p.$pDateCol) as tgl, COALESCE(SUM(p.$pAmountCol),0) as total FROM pembayaran p WHERE p.$pStatusCol='dibayar' AND DATE(p.$pDateCol) >= '$limitDate' AND DATE(p.$pDateCol) <= '$limitTo' GROUP BY DATE(p.$pDateCol) ORDER BY p.$pDateCol ASC");
    for ($d = strtotime($limitDate); $d <= strtotime($limitTo); $d += 86400) {
        $dk = date('Y-m-d', $d);
        $found = 0;
        foreach ($raw as $r) { if ($r['tgl'] === $dk) { $found = (float)$r['total']; break; } }
        $dailyRevenue[] = ['tgl' => $dk, 'label' => date('d M', $d), 'total' => $found];
    }
} else {
    for ($d = strtotime($dateFrom); $d <= strtotime($dateTo); $d += 86400) {
        $dk = date('Y-m-d', $d);
        $val = sv($db, "SELECT COALESCE(SUM(p.$pAmountCol),0) as t FROM pembayaran p WHERE p.$pStatusCol='dibayar' AND DATE(p.$pDateCol)='$dk'", 0);
        $dailyRevenue[] = ['tgl' => $dk, 'label' => date('d M', $d), 'total' => $val];
    }
}

// ===== DATA GRAFIK =====
 $statusData = ($period === 'all') 
    ? sq($db, "SELECT o.$oStatusCol as status, COUNT(*) as cnt FROM orders o GROUP BY o.$oStatusCol ORDER BY cnt DESC")
    : sq($db, "SELECT o.$oStatusCol as status, COUNT(*) as cnt FROM orders o WHERE DATE(o.$oDateCol) BETWEEN '$escFrom' AND '$escTo' GROUP BY o.$oStatusCol ORDER BY cnt DESC");

 $topLayanan = ($period === 'all')
    ? sq($db, "SELECT l.nama_layanan as nama, SUM(od.qty) as cnt, COALESCE(SUM(od.subtotal),0) as rev FROM order_detail od JOIN orders o ON o.id_order = od.id_order JOIN layanan l ON l.id_layanan = od.id_layanan GROUP BY od.id_layanan ORDER BY cnt DESC LIMIT 8")
    : sq($db, "SELECT l.nama_layanan as nama, SUM(od.qty) as cnt, COALESCE(SUM(od.subtotal),0) as rev FROM order_detail od JOIN orders o ON o.id_order = od.id_order JOIN layanan l ON l.id_layanan = od.id_layanan WHERE DATE(o.tanggal_order) BETWEEN '$escFrom' AND '$escTo' GROUP BY od.id_layanan ORDER BY cnt DESC LIMIT 8");

 $topCustomers = ($period === 'all')
    ? sq($db, "SELECT u.nama, COUNT(o.id_order) as cnt, COALESCE(SUM(o.total_harga),0) as total FROM orders o JOIN users u ON u.id_user = o.id_user GROUP BY o.id_user ORDER BY total DESC LIMIT 8")
    : sq($db, "SELECT u.nama, COUNT(o.id_order) as cnt, COALESCE(SUM(o.total_harga),0) as total FROM orders o JOIN users u ON u.id_user = o.id_user WHERE DATE(o.tanggal_order) BETWEEN '$escFrom' AND '$escTo' GROUP BY o.id_user ORDER BY total DESC LIMIT 8");

 $methodData = ($period === 'all')
    ? sq($db, "SELECT p.metode_pembayaran as method, COUNT(*) as cnt, COALESCE(SUM(p.jumlah),0) as total FROM pembayaran p GROUP BY p.metode_pembayaran ORDER BY cnt DESC")
    : sq($db, "SELECT p.metode_pembayaran as method, COUNT(*) as cnt, COALESCE(SUM(p.jumlah),0) as total FROM pembayaran p WHERE DATE(p.tanggal_bayar) BETWEEN '$escFrom' AND '$escTo' GROUP BY p.metode_pembayaran ORDER BY cnt DESC");

// ===== HELPERS UI =====
 $statusLabels = ['baru'=>'Baru','pending'=>'Menunggu','proses'=>'Diproses','selesai'=>'Selesai','batal'=>'Batal','expired'=>'Kadaluarsa'];
 $statusColors = ['baru'=>'#3b82f6','pending'=>'#f59e0b','proses'=>'#8b5cf6','selesai'=>'#10b981','batal'=>'#ef4444','expired'=>'#6b7280'];

function growthBadge($val) {
    if ($val > 0) return '<span class="badge badge-up"><i class="fa-solid fa-arrow-up"></i> +' . number_format($val, 1) . '%</span>';
    if ($val < 0) return '<span class="badge badge-down"><i class="fa-solid fa-arrow-down"></i> ' . number_format(abs($val), 1) . '%</span>';
    return '<span class="badge badge-neutral">0%</span>';
}

 $sessName = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin';

// ===== JSON FOR JS =====
 $chartLabels = json_encode(array_column($dailyRevenue, 'label'));
 $chartValues = json_encode(array_column($dailyRevenue, 'total'));
 $statusLabelsJson = json_encode(array_map(function($s) use ($statusLabels) { return isset($statusLabels[$s]) ? $statusLabels[$s] : $s; }, array_column($statusData, 'status')));
 $statusValuesJson = json_encode(array_column($statusData, 'cnt'));
 $statusColorsJson = json_encode(array_map(function($s) use ($statusColors) { return isset($statusColors[$s]) ? $statusColors[$s] : '#c48098'; }, array_column($statusData, 'status')));
 $layananLabelsJson = json_encode(array_column($topLayanan, 'nama'));
 $layananValuesJson = json_encode(array_column($topLayanan, 'cnt'));
 $methodLabelsJson = json_encode(array_column($methodData, 'method'));
 $methodValuesJson = json_encode(array_column($methodData, 'cnt'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* --- VARIABEL TEMA PINK (ORIGINAL) --- */
        :root {
            --bg-body: #fff5f7;
            --bg-card: #ffffff;
            --pk-1: #be185d; /* Deep Pink */
            --pk-2: #db2777; /* Pink */
            --pk-3: #ec4899; /* Light Pink */
            --pk-4: #f472b6;
            --text-main: #4a1028;
            --text-muted: #9b4068;
            --border: rgba(190, 24, 93, 0.08);
            --sidebar-w: 260px;
            --shadow: 0 4px 20px rgba(190, 24, 93, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }

        /* --- LAYOUT --- */
        .layout { display: flex; min-height: 100vh; }
        
        /* Sidebar Pink Gradient */
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, #3a0a1e 0%, #2d0718 40%, #1a0410 100%); color: #fff; position: fixed; height: 100vh; left: 0; top: 0; z-index: 50; display: flex; flex-direction: column; border-right: 1px solid rgba(249,168,212,.08); transition: 0.3s; }
        .sidebar-header { padding: 1.5rem; font-size: 1.2rem; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 10px; }
        .sidebar-brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--pk-3), var(--pk-1)); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        
        .sidebar-nav { padding: 1rem; flex: 1; display: flex; flex-direction: column; gap: 2px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: rgba(255,255,255,0.6); font-weight: 500; transition: 0.2s; font-size: 0.9rem; }
        .nav-link:hover, .nav-link.active { background: rgba(236,72,153,0.15); color: #fff; }
        .nav-link.active { background: linear-gradient(135deg, rgba(236,72,153,.2), rgba(190,24,93,.1)); border: 1px solid rgba(236,72,153,.15); box-shadow: 0 2px 12px rgba(236,72,153,.1); }
        .nav-icon { width: 24px; text-align: center; font-size: 0.9rem; }

        .sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); }
        .user-profile { display: flex; align-items: center; gap: 10px; }
        .avatar { width: 40px; height: 40px; background: linear-gradient(135deg, var(--pk-3), var(--pk-1)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; }

        /* Main Content */
        .main { flex: 1; margin-left: var(--sidebar-w); padding: 2rem; max-width: 1600px; margin-right: auto; }
        
        /* --- HEADER & CONTROLS --- */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .title h1 { font-size: 1.5rem; font-weight: 900; color: var(--pk-1); display: flex; align-items: center; gap: 10px; }
        .date-display { color: var(--text-muted); font-size: 0.9rem; margin-top: 5px; }
        
        .btn { padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pink { background: linear-gradient(135deg, var(--pk-3), var(--pk-1)); color: #fff; box-shadow: 0 4px 12px rgba(236,72,153,0.3); }
        .btn-pink:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(236,72,153,0.4); }
        .btn-outline { background: #fff; border: 1px solid rgba(190,24,93,0.2); color: var(--pk-1); }
        .btn-outline:hover { border-color: var(--pk-3); background: #fff0f5; }

        .filters { background: #fff; padding: 1rem 1.5rem; border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--shadow); display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 2rem; }
        .filter-group { display: flex; gap: 5px; background: var(--bg-body); padding: 5px; border-radius: 12px; }
        .filter-btn { padding: 8px 16px; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); cursor: pointer; background: transparent; transition: 0.2s; }
        .filter-btn.active { background: #fff; color: var(--pk-2); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .date-inputs { display: flex; align-items: center; gap: 10px; margin-left: auto; }
        .date-box { position: relative; }
        .date-box input { padding: 8px 12px 8px 35px; border: 1px solid var(--border); border-radius: 10px; font-family: inherit; color: var(--text-main); font-size: 0.85rem; cursor: pointer; }
        .date-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; }

        /* --- STATS GRID --- */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: #fff; border-radius: 20px; padding: 1.5rem; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; overflow: hidden; transition: 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(190,24,93,0.15); }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .ib-pink { background: rgba(236,72,153,0.1); color: var(--pk-3); }
        .ib-blue { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .ib-purple { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .ib-green { background: rgba(16,185,129,0.1); color: #10b981; }

        .val { font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1.2; margin-bottom: 5px; letter-spacing: -0.5px; }
        .lbl { color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge-up { background: rgba(16,185,129,0.1); color: #10b981; }
        .badge-down { background: rgba(239,68,68,0.1); color: #ef4444; }
        .badge-neutral { background: rgba(148,163,184,0.1); color: #64748b; }

        /* --- CHARTS & LISTS --- */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .charts-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }

        .card-title { font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; }
        .chart-container { position: relative; height: 300px; width: 100%; }

        .list-row { display: flex; flex-direction: column; gap: 12px; }
        .list-item { display: flex; align-items: center; padding: 10px; border-radius: 12px; background: var(--bg-body); transition: 0.2s; border: 1px solid transparent; }
        .list-item:hover { background: #fff; border-color: rgba(236,72,153,0.2); }
        .rank { width: 26px; height: 26px; border-radius: 8px; background: rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; color: var(--text-muted); margin-right: 12px; flex-shrink: 0; }
        .rank.r-1 { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
        .rank.r-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
        .rank.r-3 { background: linear-gradient(135deg, #c2884d, #a16207); color: #fff; }
        
        .info { flex: 1; min-width: 0; }
        .name { font-weight: 600; font-size: 0.9rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sub { font-size: 0.75rem; color: var(--text-muted); }
        .amount { font-weight: 700; font-size: 0.9rem; color: var(--pk-1); text-align: right; }

        @media(max-width: 1024px) {
            .charts-row, .charts-split { grid-template-columns: 1fr; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .date-inputs { margin-left: 0; width: 100%; justify-content: space-between; margin-top: 10px; }
        }

        /* --- PRINT STYLES --- */
        @media print {
            .sidebar, .filters, .btn, .header { display: none; }
            .main { margin: 0; padding: 0; width: 100%; max-width: 100%; background: #fff; }
            .card { border: 1px solid #ddd; box-shadow: none; break-inside: avoid; margin-bottom: 20px; }
            .grid { display: block; }
            .card { margin-bottom: 20px; }
            body { background: #fff; color: #000; }
            .val, .name, .amount { color: #000; }
            .print-header { display: block !important; text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
            .print-header h1 { margin: 0; font-size: 24px; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand-icon"><i class="fa-solid fa-gem"></i></div>
            <div>Admin <span style="font-weight:300; opacity:0.7">Panel</span></div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="nav-icon fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="orders.php" class="nav-link"><i class="nav-icon fa-solid fa-box-open"></i> Pesanan</a>
            <a href="layanan.php" class="nav-link"><i class="nav-icon fa-solid fa-shirt"></i> Layanan</a>
            <a href="reports.php" class="nav-link active"><i class="nav-icon fa-solid fa-chart-line"></i> Laporan</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="avatar"><?= strtoupper(substr($sessName,0,1)) ?></div>
                <div style="font-size: 0.85rem;">
                    <div style="font-weight: 700; color: #fff;"><?= htmlspecialchars($sessName) ?></div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
        <!-- Header Cetak PDF -->
        <div class="print-header">
            <h1>Laporan Keuangan Laundry</h1>
            <p>Dicetak pada: <?= date('d F Y H:i') ?></p>
        </div>

        <header class="header">
            <div>
                <div class="title">
                    <h1><i class="fa-solid fa-chart-simple" style="color:var(--pk-3)"></i> Laporan Keuangan</h1>
                </div>
                <div class="date-display">Periode: <b><?= $period === 'all' ? 'Semua Waktu' : date('d M Y', strtotime($dateFrom)) . ' - ' . date('d M Y', strtotime($dateTo)) ?></b></div>
            </div>
            <button onclick="window.print()" class="btn btn-pink"><i class="fa-solid fa-print"></i> Cetak PDF</button>
        </header>

        <!-- Filter -->
        <div class="filters">
            <div class="filter-group">
                <form method="GET" style="display:contents;">
                    <button type="submit" name="period" value="7" class="filter-btn <?= $period == '7' ? 'active' : '' ?>">7 Hari</button>
                    <button type="submit" name="period" value="30" class="filter-btn <?= $period == '30' ? 'active' : '' ?>">30 Hari</button>
                    <button type="submit" name="period" value="all" class="filter-btn <?= $period == 'all' ? 'active' : '' ?>">Semua</button>
                </form>
            </div>
            <form method="GET" class="date-inputs">
                <div class="date-box">
                    <i class="fa-regular fa-calendar"></i>
                    <input type="date" name="from" value="<?= $dateFrom ?>">
                </div>
                <span style="color:var(--text-muted)">s/d</span>
                <div class="date-box">
                    <i class="fa-regular fa-calendar"></i>
                    <input type="date" name="to" value="<?= $dateTo ?>">
                </div>
                <button type="submit" class="btn btn-pink" style="padding: 8px 16px;"><i class="fa-solid fa-filter"></i></button>
            </form>
        </div>

        <!-- Stats Grid -->
        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <div class="icon-box ib-pink"><i class="fa-solid fa-wallet"></i></div>
                    <?= growthBadge($revenueGrowth) ?>
                </div>
                <div class="val"><?= formatRupiah($totalRevenue) ?></div>
                <div class="lbl">Total Revenue</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="icon-box ib-blue"><i class="fa-solid fa-box"></i></div>
                    <?= growthBadge($orderGrowth) ?>
                </div>
                <div class="val"><?= number_format($totalOrders) ?></div>
                <div class="lbl">Total Pesanan</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="icon-box ib-purple"><i class="fa-solid fa-users"></i></div>
                    <span class="badge badge-neutral">Total</span>
                </div>
                <div class="val"><?= number_format($totalCustomers) ?></div>
                <div class="lbl">Pelanggan</div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="icon-box ib-green"><i class="fa-solid fa-receipt"></i></div>
                    <span class="badge badge-neutral">AVG</span>
                </div>
                <div class="val"><?= formatRupiah($avgOrder) ?></div>
                <div class="lbl">Rata-rata Order</div>
            </div>
        </div>

        <!-- Chart Revenue & Status -->
        <div class="charts-row">
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-arrow-trend-up" style="color:var(--pk-3)"></i> Revenue Harian</div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-chart-pie" style="color:var(--pk-3)"></i> Status Pesanan</div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Layanan & Metode -->
        <div class="charts-split">
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-ranking-star" style="color:#f59e0b"></i> Top Layanan</div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="layananChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-credit-card" style="color:var(--pk-2)"></i> Metode Pembayaran</div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="methodChart"></canvas>
                </div>
            </div>
        </div>

        <!-- List: Top Customer & Status Detail -->
        <div class="charts-split">
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-crown" style="color:#fbbf24"></i> Top Customers</div>
                <div class="list-row" style="max-height: 350px; overflow-y: auto;">
                    <?php if (!empty($topCustomers)): ?>
                    <?php foreach($topCustomers as $i => $c): $rn = $i+1; $cls = $rn <=3 ? "r-$rn":""; ?>
                    <div class="list-item">
                        <div class="rank <?= $cls ?>"><?= $rn ?></div>
                        <div class="info">
                            <div class="name"><?= htmlspecialchars($c['nama']) ?></div>
                            <div class="sub"><?= (int)$c['cnt'] ?> pesanan</div>
                        </div>
                        <div class="amount"><?= formatRupiah($c['total']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align:center; color:#999; padding:20px;">Belum ada data customer</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-list-check" style="color:var(--text-muted)"></i> Detail Status</div>
                <div class="list-row" style="max-height: 350px; overflow-y: auto;">
                    <?php if (!empty($statusData)): ?>
                    <?php foreach($statusData as $i => $s): $rn = $i+1; $cls = $rn <=3 ? "r-$rn":""; $lbl = isset($statusLabels[$s['status']]) ? $statusLabels[$s['status']] : $s['status']; ?>
                    <div class="list-item">
                        <div class="rank <?= $cls ?>" style="background:<?= isset($statusColors[$s['status']]) ? $statusColors[$s['status']] : '#ccc' ?>; color:#fff"><?= $rn ?></div>
                        <div class="info">
                            <div class="name"><?= $lbl ?></div>
                            <div class="sub"><?= $totalOrders ? round(($s['cnt']/$totalOrders)*100) : 0 ?>% dari total</div>
                        </div>
                        <div class="amount"><?= $s['cnt'] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align:center; color:#999; padding:20px;">Belum ada data status</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

<script>
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = '#9b4068';

// Revenue Chart
if(document.getElementById('revenueChart')) {
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= $chartLabels ?>,
            datasets: [{
                label: 'Pendapatan',
                data: <?= $chartValues ?>,
                borderColor: '#ec4899',
                backgroundColor: (ctx) => {
                    const g = ctx.chart.ctx.createLinearGradient(0,0,0,300);
                    g.addColorStop(0, 'rgba(236, 72, 153, 0.2)');
                    g.addColorStop(1, 'rgba(236, 72, 153, 0)');
                    return g;
                },
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
            scales: {
                y: { grid: { color: 'rgba(0,0,0,0.05)' }, border: {display: false} },
                x: { grid: {display: false}, border: {display: false} }
            }
        }
    });
}

// Status Doughnut
if(document.getElementById('statusChart')) {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: <?= $statusLabelsJson ?>,
            datasets: [{
                data: <?= $statusValuesJson ?>,
                backgroundColor: <?= $statusColorsJson ?>,
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: {size: 11} } } }
        }
    });
}

// Top Layanan
if(document.getElementById('layananChart')) {
    new Chart(document.getElementById('layananChart'), {
        type: 'bar',
        data: {
            labels: <?= $layananLabelsJson ?>,
            datasets: [{
                data: <?= $layananValuesJson ?>,
                backgroundColor: '#ec4899',
                borderRadius: 6,
                barThickness: 20
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: {display: false} },
            scales: { x: { grid: {display: false} }, y: { grid: {display: false} } }
        }
    });
}

// Methods
if(document.getElementById('methodChart')) {
    new Chart(document.getElementById('methodChart'), {
        type: 'polarArea',
        data: {
            labels: <?= $methodLabelsJson ?>,
            datasets: [{
                data: <?= $methodValuesJson ?>,
                backgroundColor: ['#ec4899', '#db2777', '#be185d', '#f472b6', '#3b82f6']
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: {size: 11} } } },
            scales: { r: { grid: {color: 'rgba(0,0,0,0.05)'}, ticks: {display: false} } }
        }
    });
}
</script>
</body>
</html>