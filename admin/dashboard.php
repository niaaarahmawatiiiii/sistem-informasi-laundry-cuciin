<?php
// START BUFFER
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

// LOGIKA LOGOUT LANGSUNG DI SINI (Menghindari error 500 di file logout.php)
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = str_replace('/admin', '', dirname($_SERVER['SCRIPT_NAME']));
    $url = $protocol . '://' . $host . $baseDir . '/index.php';
    header("Location: " . $url);
    exit;
}

// Load database
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    die("<h3>Error Sistem</h3>Gagal memuat konfigurasi database: " . $e->getMessage());
}

// ===== DEFINISI VARIABEL PENTING =====
if (!defined('APP_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = str_replace('/admin', '', dirname($_SERVER['SCRIPT_NAME']));
    define('APP_URL', $protocol . '://' . $host . $baseDir);
}

// ===== FUNGSI PENUNJANG =====
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            $url = APP_URL . '/user/login.php';
            if (!headers_sent()) {
                header('Location: ' . $url);
                exit;
            } else {
                echo "<script>window.location.href='$url';</script>";
                exit;
            }
        }
    }
}
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        $role = $_SESSION['role'] ?? $_SESSION['level'] ?? '';
        if ($role !== 'admin') {
            die("<h3>Akses Ditolak</h3>Halaman ini khusus Administrator.");
        }
    }
}
if (!function_exists('getFlash')) {
    function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

requireLogin(); 
requireAdmin();

 $flash = getFlash();

// ===== CEK KONEKSI DB =====
if (!isset($db) || !$db instanceof mysqli) {
    die("<h3>Koneksi Database Hilang</h3>Variable \$db tidak terdeteksi.");
}

// ===== SAFE HELPERS =====
function sq($db, $sql) {
    try { $r = $db->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : []; }
    catch (Exception $e) { return []; }
}
function sv($db, $sql, $def = 0) {
    try { $r = $db->query($sql); if ($r && $row = $r->fetch_assoc()) return (float)$row['t']; }
    catch (Exception $e) {} return $def;
}
function cols($db, $table) {
    try { $r = $db->query("SHOW COLUMNS FROM $table"); return $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'Field') : []; }
    catch (Exception $e) { return []; }
}
function pick($arr, $candidates) {
    foreach ($candidates as $c) if (in_array($c, $arr)) return $c;
    return $candidates[0];
}
function tableExists($db, $name) {
    try { $r = $db->query("SHOW TABLES LIKE '$name'"); return $r && $r->num_rows > 0; }
    catch (Exception $e) { return false; }
}

// ===== DETECT TABLES =====
 $hasOrders = tableExists($db, 'orders');
 $hasUsers  = tableExists($db, 'users');
 $hasLayanan = tableExists($db, 'layanan');
 $hasOD = tableExists($db, 'order_detail');
 $odTable = 'order_detail'; // Sesuai schema Anda
 $hasPay = tableExists($db, 'pembayaran');
 $payTable = 'pembayaran'; // Sesuai schema Anda
 $hasJadwal = tableExists($db, 'jadwal_self_service');

// ===== DETECT KOLOM =====
 $oCols = $hasOrders ? cols($db, 'orders') : [];
 $uCols = $hasUsers ? cols($db, 'users') : [];
 $lCols = $hasLayanan ? cols($db, 'layanan') : [];
 $pCols = $hasPay ? cols($db, $payTable) : [];
 $odCols = $hasOD ? cols($db, $odTable) : [];
 $jCols = $hasJadwal ? cols($db, 'jadwal_self_service') : [];

// Orders
 $colDate   = pick($oCols, ['tanggal_order','created_at','tgl_order','tanggal','date_created','created','order_date']);
 $colStatus = pick($oCols, ['status','order_status','state']);
 $colTotal  = pick($oCols, ['total_harga','total','total_bayar','grand_total','amount']);
 $colUser   = pick($oCols, ['id_user','user_id','customer_id']);
 $colId     = pick($oCols, ['id_order','order_id','id']);
 $colKode   = in_array('kode_order', $oCols) ? 'kode_order' : '';
 $colMetode = in_array('metode', $oCols) ? 'metode' : '';

// Users
 $uNameCol  = pick($uCols, ['nama','name','username','nama_lengkap','full_name']);
 $uIdCol    = pick($uCols, ['id_user','user_id','id']);
 $uRoleCol  = pick($uCols, ['role','level','user_role','tipe']);
 $uDateCol  = pick($uCols, ['created_at','tgl_daftar','tanggal','date_created','created','registered']);
 $uEmailCol = in_array('email', $uCols) ? 'email' : '';
 $uPhoneCol = pick($uCols, ['no_hp','telepon','phone','whatsapp']);

// Layanan
 $lIdCol    = pick($lCols, ['id_layanan','layanan_id','id']);
 $lNameCol  = pick($lCols, ['nama_layanan','nama','name','layanan','service_name']);
 $lIconCol  = in_array('icon', $lCols) ? 'icon' : '';
 $lActiveCol = in_array('is_active', $lCols) ? 'is_active' : '';

// Pembayaran
 $payIdCol    = pick($pCols, ['id_pembayaran','id_payment','payment_id','id']);
 $payOrderCol = pick($pCols, ['id_order','order_id']);
 $payMethodCol = pick($pCols, ['metode_pembayaran','metode','method','payment_method']);
 $payStatusCol = pick($pCols, ['status_pembayaran','status','payment_status']);
 $payAmountCol = pick($pCols, ['jumlah','amount','total','nominal']);
 $payDateCol  = pick($pCols, ['tanggal_bayar','created_at','tgl_bayar','tanggal','date_created']);

// Order Detail
 $odLayId = pick($odCols, ['id_layanan','layanan_id','service_id']);
 $odOrdId = pick($odCols, ['id_order','order_id']);
 $odQty   = pick($odCols, ['qty','jumlah','quantity']);
 $odSub   = pick($odCols, ['subtotal','sub_total','harga_total','amount']);
 $odPrice = pick($odCols, ['harga_satuan','harga']);

// Jadwal
 $jTimeCol = pick($jCols, ['jam','waktu','time']);
 $jDateCol = pick($jCols, ['tanggal','date']);
 $jUserCol = pick($jCols, ['id_user','user_id']);

// ===== STATISTIK =====
 $today = date('Y-m-d');

// 1. Hitung Order
 $tOrders = $hasOrders ? (int)sv($db, "SELECT COUNT(*) as t FROM orders", 0) : 0;
 $tOrdersToday = ($hasOrders && $colDate) ? (int)sv($db, "SELECT COUNT(*) as t FROM orders WHERE DATE($colDate)='$today'", 0) : 0;

// 2. Hitung REVENUE (SESUAI DATA BASE)
if ($hasPay && $payAmountCol && $payStatusCol) {
    // Total Revenue (Semua Waktu) - Uang yang SUDAH MASUK
    $tRevenue = sv($db, "SELECT COALESCE(SUM($payAmountCol),0) as t FROM $payTable WHERE $payStatusCol='dibayar'", 0);
    // Revenue Bulan Ini (Hanya yang DIBAYAR di bulan ini)
    $tRevenueMonth = sv($db, "SELECT COALESCE(SUM($payAmountCol),0) as t FROM $payTable WHERE $payStatusCol='dibayar' AND MONTH($payDateCol)=MONTH(NOW()) AND YEAR($payDateCol)=YEAR(NOW())", 0);
} else {
    $tRevenue = 0;
    $tRevenueMonth = 0;
}

// 3. Hitung Users
 $tUsers = $hasUsers ? (int)sv($db, "SELECT COUNT(*) as t FROM users WHERE $uRoleCol='user'", 0) : 0;
 $tUsersNew = ($hasUsers && $uDateCol) ? (int)sv($db, "SELECT COUNT(*) as t FROM users WHERE DATE($uDateCol)='$today'", 0) : 0;

// 4. Hitung Layanan
 $tLayanan = $hasLayanan ? (int)sv($db, "SELECT COUNT(*) as t FROM layanan WHERE $lActiveCol=1", 0) : 0;

// 5. Hitung PEMBAYARAN PENDING
 $payPending = 0;
if ($hasPay && $payStatusCol) { 
    $payPending = (int)sv($db, "SELECT COUNT(*) as t FROM $payTable WHERE $payStatusCol='pending'", 0); 
}

// FIX: Hitung ORDER PENDING (BARU/MENUNGGU) untuk Quick Action
 $tOrdersPending = 0;
if ($hasOrders && $colStatus) {
    $tOrdersPending = (int)sv($db, "SELECT COUNT(*) as t FROM orders WHERE $colStatus IN ('baru','pending','menunggu','dikonfirmasi')", 0);
}

 $ssToday = ($hasJadwal && $jDateCol) ? (int)sv($db, "SELECT COUNT(*) as t FROM jadwal_self_service WHERE DATE($jDateCol)='$today'", 0) : 0;

// Status counts (untuk donut chart)
 $statusCounts = [];
if ($hasOrders && $colStatus) {
    foreach (sq($db, "SELECT $colStatus as s, COUNT(*) as c FROM orders GROUP BY $colStatus") as $r) $statusCounts[$r['s']] = (int)$r['c'];
}

// Top layanan
 $topLayanan = [];
if ($hasOD && $hasLayanan && $odLayId && $odOrdId && $odQty) {
    $iconSel = $lIconCol ? ", l.$lIconCol as icon" : "";
    $topLayanan = sq($db, "SELECT od.$odLayId as lid, l.$lNameCol as nama_layanan$iconSel, SUM(od.$odQty) as total_qty, SUM(od.$odSub) as total_rev FROM $odTable od JOIN layanan l ON l.$lIdCol=od.$odLayId JOIN orders o ON o.$colId=od.$odOrdId GROUP BY od.$odLayId ORDER BY total_qty DESC LIMIT 5");
}

// Recent orders
 $recentOrders = [];
if ($hasOrders && $colId && $colUser && $colDate && $colTotal && $colStatus && $hasUsers) {
    $phoneSel = $uPhoneCol ? ", u.$uPhoneCol as user_phone" : "";
    $recentOrders = sq($db, "SELECT o.*, u.$uNameCol as user_name$phoneSel FROM orders o LEFT JOIN users u ON u.$uIdCol=o.$colUser ORDER BY o.$colDate DESC LIMIT 5");
}

// Recent users
 $recentUsers = [];
if ($hasUsers && $uDateCol) {
    $emailSel = $uEmailCol ? ", $uEmailCol as email" : "";
    $phoneSel2 = $uPhoneCol ? ", $uPhoneCol as phone" : "";
    $recentUsers = sq($db, "SELECT *, $uRoleCol as role$emailSel$phoneSel2 FROM users ORDER BY $uDateCol DESC LIMIT 5");
}

// Pending payments detail
 $pendingPayments = [];
if ($hasPay && $payStatusCol && $payOrderCol && $payAmountCol && $hasOrders && $hasUsers) {
    $kodeSel = $colKode ? ", o.$colKode as kode_order" : "";
    $pendingPayments = sq($db, "SELECT p.*, o.$colStatus as order_status, o.$colTotal as order_total$kodeSel, u.$uNameCol as user_name FROM $payTable p LEFT JOIN orders o ON o.$colId=p.$payOrderCol LEFT JOIN users u ON u.$uIdCol=o.$colUser WHERE p.$payStatusCol='pending' ORDER BY p.$payIdCol DESC LIMIT 5");
}

// Self Service Schedule
 $scheduleToday = [];
if ($hasJadwal && $jDateCol) {
    $uJoin = ($hasUsers && $jUserCol && $uNameCol) ? "LEFT JOIN users u ON u.$uIdCol=j.$jUserCol" : "";
    $uNameSel = ($hasUsers && $jUserCol && $uNameCol) ? ", u.$uNameCol as user_name" : "";
    $scheduleToday = sq($db, "SELECT j.*$uNameSel FROM jadwal_self_service j $uJoin WHERE DATE(j.$jDateCol)='$today' ORDER BY j.$jTimeCol ASC LIMIT 5");
}

// Revenue 7 hari
 $revDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $val = 0;
    if ($hasPay && $payAmountCol && $payStatusCol && $payDateCol) {
        $val = sv($db, "SELECT COALESCE(SUM($payAmountCol),0) as t FROM $payTable WHERE $payStatusCol='dibayar' AND DATE($payDateCol)='$d'", 0);
    }
    $revDays[] = ['label' => date('D', strtotime($d)), 'value' => $val, 'full' => date('d M', strtotime($d))];
}
 $revMax = max(array_column($revDays, 'value')) ?: 1;
 $revTotal7 = array_sum(array_column($revDays, 'value'));

// Targets
 $targetBulanan = 5000000; 
 $targetPct = min(100, ($tRevenueMonth / $targetBulanan) * 100);
 $targetOrder = 100; 
 $targetOrderPct = min(100, ($tOrders / $targetOrder) * 100);

// Status & Payment maps
 $statusMap = [
    'pending'=>['label'=>'Pending','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)'],
    'menunggu'=>['label'=>'Menunggu','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)'],
    'dikonfirmasi'=>['label'=>'Dikonfirmasi','color'=>'#6366f1','bg'=>'rgba(99,102,241,.1)'],
    'diproses'=>['label'=>'Diproses','color'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)'],
    'dicuci'=>['label'=>'Dicuci','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.1)'],
    'disetrika'=>['label'=>'Disetrika','color'=>'#ec4899','bg'=>'rgba(236,72,153,.1)'],
    'siap_ambil'=>['label'=>'Siap Ambil','color'=>'#10b981','bg'=>'rgba(16,185,129,.1)'],
    'diantar'=>['label'=>'Diantar','color'=>'#06b6d4','bg'=>'rgba(6,182,212,.1)'],
    'selesai'=>['label'=>'Selesai','color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)'],
    'done'=>['label'=>'Selesai','color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)'],
    'dibatalkan'=>['label'=>'Dibatalkan','color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
    'batal'=>['label'=>'Dibatalkan','color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
    'cancelled'=>['label'=>'Dibatalkan','color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
];
 $payStatusMap = [
    'pending'=>['label'=>'Pending','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)'],
    'menunggu'=>['label'=>'Menunggu','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)'],
    'dibayar'=>['label'=>'Dibayar','color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)'],
    'sukses'=>['label'=>'Sukses','color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)'],
    'success'=>['label'=>'Sukses','color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)'],
    'lunas'=>['label'=>'Lunas','color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)'],
    'gagal'=>['label'=>'Gagal','color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
    'failed'=>['label'=>'Gagal','color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)'],
    'refund'=>['label'=>'Refund','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.1)'],
];

function statusBadge($s) {
    global $statusMap;
    $m = $statusMap[$s] ?? ['label'=>ucfirst($s),'color'=>'#94a3b8','bg'=>'rgba(148,163,184,.1)'];
    return "<span style='display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;color:{$m['color']};background:{$m['bg']};border:1px solid {$m['color']}22'><span style='width:6px;height:6px;border-radius:50%;background:{$m['color']}'></span>{$m['label']}</span>";
}
function payBadge($s) {
    global $payStatusMap;
    $m = $payStatusMap[$s] ?? ['label'=>ucfirst($s),'color'=>'#94a3b8','bg'=>'rgba(148,163,184,.1)'];
    return "<span style='display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;color:{$m['color']};background:{$m['bg']};border:1px solid {$m['color']}22'><span style='width:6px;height:6px;border-radius:50%;background:{$m['color']}'></span>{$m['label']}</span>";
}
function metodeLabel($m) {
    if ($m === 'antar-jemput') return '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:.68rem;font-weight:700;color:#3b82f6;background:rgba(59,130,246,.08)"><i class="fa-solid fa-motorcycle" style="font-size:.6rem"></i>Antar-Jemput</span>';
    if ($m === 'datang-sendiri') return '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:.68rem;font-weight:700;color:#10b981;background:rgba(16,185,129,.08)"><i class="fa-solid fa-store" style="font-size:.6rem"></i>Datang Sendiri</span>';
    return htmlspecialchars($m);
}

 $sessName = $_SESSION['nama'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#fff5f7;--bg2:#ffedf1;--bg3:#ffe4ec;--pk1:#be185d;--pk2:#db2777;--pk3:#ec4899;--pk4:#f472b6;--t1:#4a1028;--t2:#6b2040;--t3:#9b4068;--t4:#c48098;--bd:rgba(190,24,93,.08);--bd2:rgba(190,24,93,.15);--card:#fff;--shadow:0 2px 16px rgba(190,24,93,.06);--shadow-lg:0 8px 32px rgba(190,24,93,.1);--radius:14px;--radius-lg:20px;--sidebar-w:260px;--tr:all .3s cubic-bezier(.4,0,.2,1)}
body{font-family:'Plus Jakarta Sans',-apple-system,sans-serif;background:var(--bg);color:var(--t1);line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit;transition:var(--tr)}::selection{background:var(--pk3);color:#fff}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--bg2)}::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--pk3),var(--pk1));border-radius:10px}
.dashboard-layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);min-height:100vh;position:fixed;left:0;top:0;bottom:0;z-index:100;background:linear-gradient(180deg,#3a0a1e 0%,#2d0718 40%,#1a0410 100%);border-right:1px solid rgba(249,168,212,.08);display:flex;flex-direction:column;transition:var(--tr)}
.sidebar::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(249,168,212,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(249,168,212,.02) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
.sidebar-brand{padding:1.5rem 1.25rem;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(249,168,212,.08);position:relative}
.brand-icon{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--pk3),var(--pk1));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 14px rgba(236,72,153,.3)}
.sidebar-brand span{color:#fff;font-weight:800;font-size:1rem}.sidebar-brand em{color:#f9a8d4;font-style:normal}
.sidebar-nav{flex:1;padding:.75rem;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
.sidebar-section{display:flex;flex-direction:column;gap:2px}
.sidebar-section+.sidebar-section{margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(249,168,212,.06)}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,.5);font-size:.82rem;font-weight:600;transition:var(--tr);position:relative}
.sidebar-link:hover{color:rgba(255,255,255,.85);background:rgba(249,168,212,.06)}
.sidebar-link.active{color:#fff;background:linear-gradient(135deg,rgba(236,72,153,.2),rgba(190,24,93,.1));border:1px solid rgba(236,72,153,.15);box-shadow:0 2px 12px rgba(236,72,153,.1)}
.sidebar-link.active .link-icon{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;box-shadow:0 2px 8px rgba(236,72,153,.3)}
.link-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(249,168,212,.06);font-size:.78rem;transition:var(--tr);flex-shrink:0}
.sidebar-link.danger-link:hover{color:#fca5a5;background:rgba(239,68,68,.08)}
.sidebar-user{padding:1rem 1.25rem;border-top:1px solid rgba(249,168,212,.08);position:relative}
.sidebar-user-info{display:flex;align-items:center;gap:10px}
.user-avatar{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.8rem;flex-shrink:0}
.user-name{color:#fff;font-weight:700;font-size:.8rem}.user-role{color:rgba(249,168,212,.5);font-size:.68rem;font-weight:500}
.mobile-toggle{display:none;position:fixed;top:14px;left:14px;z-index:200;width:42px;height:42px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;font-size:1.1rem;cursor:pointer;box-shadow:0 4px 16px rgba(236,72,153,.3)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99}
.dashboard-content{flex:1;margin-left:var(--sidebar-w);padding:2rem 2.5rem;min-height:100vh;position:relative}
.dashboard-content::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(249,168,212,.08),transparent 70%);pointer-events:none;z-index:0}
.alert{padding:.85rem 1.25rem;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;animation:alertIn .4s ease}
@keyframes alertIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.15)}
.alert-danger{background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.15)}
.alert-warning{background:rgba(245,158,11,.1);color:#d97706;border:1px solid rgba(245,158,11,.15)}
.alert-info{background:rgba(59,130,246,.1);color:#1d4ed8;border:1px solid rgba(59,130,246,.15)}
.page-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;position:relative;z-index:1}
.page-title{font-size:1.6rem;font-weight:900;color:var(--t1)}
.page-sub{font-size:.82rem;color:var(--t4);margin-top:2px}
.header-date{font-size:.8rem;color:var(--t3);display:flex;align-items:center;gap:6px;background:var(--card);padding:8px 16px;border-radius:10px;border:1px solid var(--bd);box-shadow:var(--shadow)}
.header-date i{color:var(--pk3)}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:2rem;position:relative;z-index:1}
.stat-card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius-lg);padding:1.2rem 1.3rem;position:relative;overflow:hidden;transition:var(--tr);box-shadow:var(--shadow)}
.stat-card::before{content:'';position:absolute;top:0;right:0;width:90px;height:90px;border-radius:50%;transform:translate(30%,-30%);opacity:.5}
.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--bd2)}
.stat-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.7rem}
.stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.95rem;transition:var(--tr)}
.stat-card:hover .stat-icon{transform:scale(1.1)}
.stat-badge{font-size:.62rem;font-weight:700;padding:3px 8px;border-radius:6px;display:flex;align-items:center;gap:3px}
.stat-badge.up{color:#059669;background:rgba(16,185,129,.1)}
.stat-badge.warn{color:#d97706;background:rgba(245,158,11,.1)}
.stat-badge.new{color:#3b82f6;background:rgba(59,130,246,.1)}
.stat-badge.pink{color:var(--pk2);background:rgba(219,39,119,.1)}
.stat-value{font-size:1.5rem;font-weight:900;line-height:1;margin-bottom:2px}
.stat-label{font-size:.72rem;color:var(--t4);font-weight:500}
.sc-orders::before{background:radial-gradient(circle,rgba(236,72,153,.08),transparent 70%)}
.sc-orders .stat-icon{background:rgba(236,72,153,.1);color:var(--pk3)}
.sc-orders .stat-value{color:var(--pk1)}
.sc-revenue::before{background:radial-gradient(circle,rgba(245,158,11,.08),transparent 70%)}
.sc-revenue .stat-icon{background:rgba(245,158,11,.1);color:#f59e0b}
.sc-revenue .stat-value{color:#b45309}
.sc-users::before{background:radial-gradient(circle,rgba(59,130,246,.08),transparent 70%)}
.sc-users .stat-icon{background:rgba(59,130,246,.1);color:#3b82f6}
.sc-users .stat-value{color:#1d4ed8}
.sc-layanan::before{background:radial-gradient(circle,rgba(16,185,129,.08),transparent 70%)}
.sc-layanan .stat-icon{background:rgba(16,185,129,.1);color:#10b981}
.sc-layanan .stat-value{color:#047857}
.sc-pay::before{background:radial-gradient(circle,rgba(139,92,246,.08),transparent 70%)}
.sc-pay .stat-icon{background:rgba(139,92,246,.1);color:#8b5cf6}
.sc-pay .stat-value{color:#6d28d9}

/* CARDS & GRIDS */
.grid-2{display:grid;grid-template-columns:1.3fr 1fr;gap:1.25rem;margin-bottom:1.5rem;position:relative;z-index:1}
.grid-equal{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;position:relative;z-index:1}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;position:relative;z-index:1}
.card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;transition:var(--tr);display:flex;flex-direction:column}
.card:hover{box-shadow:var(--shadow-lg)}
.card-header{padding:1.1rem 1.4rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
.card-title{font-size:.92rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}
.card-title i{color:var(--pk3);font-size:.85rem}
.card-body{padding:1.25rem 1.4rem;flex:1}
.card-link{font-size:.75rem;font-weight:700;color:var(--pk2);display:flex;align-items:center;gap:4px;transition:var(--tr)}
.card-link:hover{color:var(--pk1);gap:6px}

/* TABLE */
.table-wrapper{overflow-x:auto}
.table{width:100%;border-collapse:collapse}
.table th{text-align:left;padding:10px 14px;font-size:.68rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--bd);background:var(--bg);white-space:nowrap}
.table td{padding:12px 14px;font-size:.82rem;border-bottom:1px solid var(--bd);vertical-align:middle}
.table tbody tr{transition:var(--tr)}.table tbody tr:hover{background:rgba(190,24,93,.02)}.table tbody tr:last-child td{border-bottom:none}
.order-id{font-weight:800;font-size:.75rem;color:var(--pk2);background:rgba(190,24,93,.06);padding:3px 8px;border-radius:6px;white-space:nowrap}
.user-cell{display:flex;align-items:center;gap:8px}
.user-cell-avatar{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,var(--pk4),var(--pk2));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;flex-shrink:0}
.user-cell-avatar.av-admin{background:linear-gradient(135deg,#8b5cf6,#6d28d9)}
.amount{font-weight:800;color:var(--t1)}
.time-cell{font-size:.72rem;color:var(--t4);white-space:nowrap}
.pay-amount{font-weight:800;color:#d97706}

/* CHART */
.chart-container{display:flex;align-items:flex-end;gap:8px;height:170px;padding-top:10px}
.chart-bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%}
.chart-bar-track{flex:1;width:100%;display:flex;align-items:flex-end;justify-content:center}
.chart-bar{width:70%;max-width:40px;border-radius:8px 8px 4px 4px;background:linear-gradient(180deg,var(--pk3),var(--pk1));transition:height 1s cubic-bezier(.16,1,.3,1);position:relative;min-height:4px}
.chart-bar:hover{filter:brightness(1.15)}
.chart-bar-val{position:absolute;top:-22px;left:50%;transform:translateX(-50%);font-size:.62rem;font-weight:700;color:var(--t3);white-space:nowrap;opacity:0;transition:opacity .3s}
.chart-bar:hover .chart-bar-val{opacity:1}
.chart-bar-label{font-size:.68rem;color:var(--t4);font-weight:600}
.chart-bar-full{font-size:.58rem;color:var(--t4);font-weight:500;opacity:0;transition:opacity .3s;position:absolute;top:-34px;left:50%;transform:translateX(-50%);white-space:nowrap}
.chart-bar:hover .chart-bar-full{opacity:1}

/* DONUT */
.donut-wrap{display:flex;align-items:center;gap:1.5rem}
.donut{width:130px;height:130px;border-radius:50%;position:relative;flex-shrink:0}
.donut-hole{position:absolute;inset:22px;border-radius:50%;background:var(--card);display:flex;align-items:center;justify-content:center;flex-direction:column}
.donut-hole-num{font-size:1.3rem;font-weight:900;color:var(--t1)}
.donut-hole-lbl{font-size:.58rem;color:var(--t4);font-weight:600;letter-spacing:.3px}
.donut-legend{display:flex;flex-direction:column;gap:6px;flex:1}
.legend-item{display:flex;align-items:center;gap:8px;font-size:.75rem}
.legend-dot{width:8px;height:8px;border-radius:3px;flex-shrink:0}
.legend-label{color:var(--t3);flex:1}
.legend-val{font-weight:700;color:var(--t1)}

/* PROGRESS BAR (Target) */
.progress-group{margin-bottom:1.2rem}
.progress-label{display:flex;justify-content:space-between;font-size:.72rem;font-weight:700;color:var(--t3);margin-bottom:6px}
.progress-track{height:10px;background:var(--bg);border-radius:20px;overflow:hidden;position:relative}
.progress-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#22c55e,#10b981);transition:width 1.5s cubic-bezier(.16,1,.3,1)}
.progress-fill.warning{background:linear-gradient(90deg,#f59e0b,#d97706)}
.progress-info{font-size:.68rem;color:var(--t4);margin-top:4px}

/* LIST ITEMS (Top, User, Pay) */
.list-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--bd)}
.list-item:last-child{border-bottom:none}
.rank-badge{width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0}
.rank-badge.gold{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff}
.rank-badge.silver{background:linear-gradient(135deg,#d1d5db,#9ca3af);color:#fff}
.rank-badge.bronze{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.rank-badge.normal{background:var(--bg2);color:var(--t4)}
.item-icon{font-size:1.3rem}
.item-info{flex:1;min-width:0}
.item-title{font-size:.8rem;font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.item-sub{font-size:.68rem;color:var(--t4)}
.item-val{font-size:.8rem;font-weight:800;color:var(--pk1);text-align:right;white-space:nowrap}

.user-avatar-lg{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.82rem;flex-shrink:0;color:#fff}
.user-avatar-lg.av-admin{background:linear-gradient(135deg,#8b5cf6,#6d28d9);box-shadow:0 3px 10px rgba(139,92,246,.2)}
.user-avatar-lg.av-user{background:linear-gradient(135deg,var(--pk4),var(--pk2));box-shadow:0 3px 10px rgba(236,72,153,.2)}
.role-badge{font-size:.62rem;font-weight:700;padding:2px 8px;border-radius:5px;flex-shrink:0}
.role-badge.admin{color:#8b5cf6;background:rgba(139,92,246,.1)}
.role-badge.user{color:#3b82f6;background:rgba(59,130,246,.08)}

.pay-item{display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:12px;background:var(--bg);border:1px solid var(--bd);margin-bottom:8px;transition:var(--tr)}
.pay-item:last-child{margin-bottom:0}
.pay-item:hover{border-color:var(--bd2);background:var(--bg2)}
.pay-icon{width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,.1);color:#f59e0b;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.pay-amount{font-size:.88rem;font-weight:900;color:#d97706;flex-shrink:0}

/* SCHEDULE */
.schedule-item{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:rgba(6,182,212,.05);border-radius:8px;margin-bottom:6px;border-left:3px solid #06b6d4}
.schedule-time{font-size:.75rem;font-weight:800;color:#06b6d4}
.schedule-info{font-size:.75rem;color:var(--t2);font-weight:600}

/* QUICK ACTIONS */
.quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
.quick-card{padding:1.2rem;text-align:center;background:var(--bg2);border:1px solid var(--bd);border-radius:var(--radius);transition:var(--tr);cursor:pointer}
.quick-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.quick-icon{width:46px;height:46px;border-radius:14px;margin:0 auto .7rem;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:var(--tr)}
.quick-card:hover .quick-icon{transform:scale(1.1)}
.quick-label{font-size:.8rem;font-weight:700;color:var(--t1)}
.quick-desc{font-size:.68rem;color:var(--t4);margin-top:2px}

/* ANNOUNCEMENT */
.announce-card{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;padding:1.5rem;border-radius:16px;position:relative;overflow:hidden}
.announce-card::before{content:'';position:absolute;right:-20px;bottom:-20px;width:100px;height:100px;background:rgba(255,255,255,.1);border-radius:50%}
.announce-icon{font-size:2rem;margin-bottom:.5rem}
.announce-title{font-size:1rem;font-weight:800;margin-bottom:4px}
.announce-text{font-size:.8rem;opacity:.9;line-height:1.4}

.empty{text-align:center;padding:2.5rem 1rem;color:var(--t4)}
.empty i{font-size:2rem;margin-bottom:.5rem;opacity:.3;display:block}
.empty p{font-size:.82rem}

@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr)}.grid-2,.grid-equal,.grid-3{grid-template-columns:1fr}}
@media(max-width:768px){.mobile-toggle{display:flex;align-items:center;justify-content:center}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.sidebar-overlay.open{display:block}.dashboard-content{margin-left:0;padding:1.2rem;padding-top:64px}.stats-grid{grid-template-columns:1fr 1fr}.page-header{flex-direction:column;align-items:flex-start}.quick-grid{grid-template-columns:1fr}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr}.donut-wrap{flex-direction:column;text-align:center}}
    </style>
</head>
<body>
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="dashboard-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-icon">⚙️</div><span>Admin <em>Panel</em></span></div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <a href="dashboard.php" class="sidebar-link active"><span class="link-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard</a>
                <a href="orders.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-box"></i></span> Pesanan</a>
                <a href="layanan.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-shirt"></i></span> Layanan</a>
                <a href="users.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-users"></i></span> Users</a>
                <a href="payments.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-credit-card"></i></span> Pembayaran</a>
                <a href="reports.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-line"></i></span> Laporan</a>
            </div>
            <div class="sidebar-section">
                <a href="<?= APP_URL ?>/index.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-globe"></i></span> Ke Website</a>
                <a href="dashboard.php?logout=true" class="sidebar-link danger-link"><span class="link-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Keluar</a>
            </div>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <div class="user-avatar" style="background:linear-gradient(135deg,var(--pk3),var(--pk1))"><?= strtoupper(substr($sessName,0,1)) ?></div>
                <div><div class="user-name"><?= htmlspecialchars($sessName) ?></div><div class="user-role">Administrator</div></div>
            </div>
        </div>
    </aside>
    <main class="dashboard-content">
        <?php if($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><i class="fa-solid <?= $flash['type']=='success'?'fa-circle-check':'fa-circle-xmark' ?>"></i> <?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <!-- PEMBAYARAN PENDING ALERT -->
        <?php if($payPending > 0): ?>
        <div class="alert alert-warning" style="margin-bottom:1rem;cursor:pointer" onclick="document.getElementById('payPendingSection').scrollIntoView({behavior:'smooth'})">
            <i class="fa-solid fa-bell"></i>
            <span><b><?= $payPending ?></b> pembayaran menunggu konfirmasi — Klik untuk melihat detail</span>
            <i class="fa-solid fa-arrow-down" style="margin-left:auto"></i>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div><div class="page-title">Dashboard</div><div class="page-sub">Ringkasan aktivitas dan performa bisnis laundry</div></div>
            <div class="header-date"><i class="fa-regular fa-calendar"></i> <?= date('l, d F Y') ?></div>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card sc-orders">
                <div class="stat-card-top">
                    <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                    <?php if($tOrdersToday>0): ?><span class="stat-badge up"><i class="fa-solid fa-arrow-up"></i> +<?= $tOrdersToday ?></span><?php endif; ?>
                </div>
                <div class="stat-value"><?= number_format($tOrders) ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <!-- FIX: MENAMPILKAN TOTAL REVENUE BUKAN BULAN INI -->
            <div class="stat-card sc-revenue">
                <div class="stat-card-top">
                    <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
                    <span class="stat-badge warn"><i class="fa-solid fa-chart-line"></i> All Time</span>
                </div>
                <div class="stat-value"><?= formatRupiah($tRevenue) ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
            <div class="stat-card sc-users">
                <div class="stat-card-top">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <?php if($tUsersNew>0): ?><span class="stat-badge new"><i class="fa-solid fa-user-plus"></i> +<?= $tUsersNew ?></span><?php endif; ?>
                </div>
                <div class="stat-value"><?= number_format($tUsers) ?></div>
                <div class="stat-label">Total Pelanggan</div>
            </div>
            <div class="stat-card sc-layanan">
                <div class="stat-card-top">
                    <div class="stat-icon"><i class="fa-solid fa-shirt"></i></div>
                </div>
                <div class="stat-value"><?= $tLayanan ?></div>
                <div class="stat-label">Layanan Aktif</div>
            </div>
            <div class="stat-card sc-pay">
                <div class="stat-card-top">
                    <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                    <?php if($payPending>0): ?><span class="stat-badge pink"><i class="fa-solid fa-exclamation"></i> <?= $payPending ?></span><?php endif; ?>
                </div>
                <div class="stat-value"><?= $payPending ?></div>
                <div class="stat-label">Bayar Pending</div>
            </div>
        </div>

        <!-- REVENUE CHART + TARGET + STATUS DONUT -->
        <div class="grid-3">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-bar"></i> Pendapatan 7 Hari</div>
                    <div style="font-size:.72rem;color:var(--t4);font-weight:600">Total: <?= formatRupiah($revTotal7) ?></div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <?php foreach($revDays as $d):
                            $h = $revMax > 0 ? max(($d['value']/$revMax)*140, 4) : 4;
                        ?>
                        <div class="chart-bar-wrap">
                            <div class="chart-bar-track">
                                <div class="chart-bar" style="height:0px" data-h="<?= $h ?>">
                                    <div class="chart-bar-full"><?= $d['full'] ?></div>
                                    <div class="chart-bar-val"><?= formatRupiah($d['value']) ?></div>
                                </div>
                            </div>
                            <div class="chart-bar-label"><?= $d['label'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TARGET PENDAPATAN (MEMBANDINGKAN BULAN INI VS TARGET BULANAN) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-bullseye"></i> Target Bulanan</div>
                    <span style="font-size:.72rem;color:var(--t4);font-weight:600"><?= number_format($targetPct, 1) ?>%</span>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;justify-content:center">
                    <div class="progress-group">
                        <div class="progress-label"><span>Pendapatan *Bulan Ini* (Target: 5jt)</span> <span><?= formatRupiah($tRevenueMonth) ?></span></div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width:<?= $targetPct ?>%"></div>
                        </div>
                        <div class="progress-info"><?= 5000000 - $tRevenueMonth > 0 ? 'Kurang ' . formatRupiah(5000000 - $tRevenueMonth) : 'Target Tercapai!' ?></div>
                    </div>
                    <div class="progress-group" style="margin-bottom:0">
                        <div class="progress-label"><span>Pesanan (Target: 100)</span> <span><?= $tOrders ?></span></div>
                        <div class="progress-track">
                            <div class="progress-fill warning" style="width:<?= $targetOrderPct ?>%"></div>
                        </div>
                        <div class="progress-info"><?= 100 - $tOrders > 0 ? 'Butuh ' . (100 - $tOrders) . ' pesanan lagi' : 'Target Tercapai!' ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list-check"></i> Status Pesanan</div>
                    <span style="font-size:.72rem;color:var(--t4);font-weight:600"><?= $tOrders ?> total</span>
                </div>
                <div class="card-body">
                    <?php if(!empty($statusCounts)):
                        $totalS = array_sum($statusCounts);
                        $gradients = []; $angle = 0;
                        foreach($statusCounts as $sk=>$sv){ $m = $statusMap[$sk] ?? ['color'=>'#94a3b8']; $deg = ($sv/$totalS)*360; $gradients[] = "{$m['color']} {$angle}deg ".($angle+$deg)."deg"; $angle += $deg; }
                    ?>
                    <div class="donut-wrap">
                        <div class="donut" style="background:conic-gradient(<?= implode(',',$gradients) ?>)">
                            <div class="donut-hole"><div class="donut-hole-num"><?= $totalS ?></div><div class="donut-hole-lbl">PESANAN</div></div>
                        </div>
                        <div class="donut-legend">
                            <?php foreach($statusCounts as $sk=>$sv): $m = $statusMap[$sk] ?? ['label'=>ucfirst($sk),'color'=>'#94a3b8']; $pct = $totalS > 0 ? round(($sv/$totalS)*100) : 0; ?>
                            <div class="legend-item"><div class="legend-dot" style="background:<?= $m['color'] ?>"></div><span class="legend-label"><?= $m['label'] ?></span><span class="legend-val"><?= $sv ?> (<?= $pct ?>%)</span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?><div class="empty"><i class="fa-solid fa-inbox"></i><p>Belum ada pesanan</p></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RECENT ORDERS + PENGUMUMAN + JADWAL -->
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Pesanan Terbaru</div>
                    <a href="orders.php" class="card-link">Lihat Semua <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="table-wrapper">
                    <?php if(!empty($recentOrders)): ?>
                    <table class="table">
                        <thead><tr><th>Order</th><th>Pelanggan</th><th>Metode</th><th>Total</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach($recentOrders as $o):
                            $initial = !empty($o['user_name']) ? strtoupper(substr($o['user_name'],0,1)) : '?';
                            $ordId = !empty($o[$colId]) ? $o[$colId] : '?';
                            $ordTotal = !empty($o[$colTotal]) ? $o[$colTotal] : 0;
                            $ordStatus = !empty($o[$colStatus]) ? $o[$colStatus] : '-';
                            $ordKode = $colKode && !empty($o[$colKode]) ? $o[$colKode] : '';
                            $ordMetode = $colMetode && !empty($o[$colMetode]) ? $o[$colMetode] : '';
                            $displayId = $ordKode ?: '#' . str_pad($ordId, 4, '0', STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td><span class="order-id"><?= htmlspecialchars($displayId) ?></span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar"><?= $initial ?></div>
                                    <span style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($o['user_name'] ?? 'Guest') ?></span>
                                </div>
                            </td>
                            <td><?= $ordMetode ? metodeLabel($ordMetode) : '-' ?></td>
                            <td><span class="amount"><?= formatRupiah($ordTotal) ?></span></td>
                            <td><?= statusBadge($ordStatus) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><div class="empty"><i class="fa-solid fa-inbox"></i><p>Belum ada pesanan</p></div><?php endif; ?>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem">
                <!-- PENGUMUMAN -->
                <div class="announce-card">
                    <div class="announce-icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <div class="announce-title">Pengumuman Admin</div>
                    <div class="announce-text">Jangan lupa untuk cek pembayaran yang pending dan update status pesanan 'selesai' setiap sore.</div>
                </div>

                <!-- JADWAL HARI INI -->
                <div class="card" style="flex:1">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-calendar-day"></i> Jadwal Self-Service</div>
                        <span style="font-size:.72rem;color:var(--t4);font-weight:600"><?= $ssToday ?> booking</span>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($scheduleToday)): ?>
                            <?php foreach($scheduleToday as $sj): 
                                $time = !empty($sj[$jTimeCol]) ? date('H:i', strtotime($sj[$jTimeCol])) : '';
                                $uName = !empty($sj['user_name']) ? htmlspecialchars($sj['user_name']) : 'Unknown';
                            ?>
                            <div class="schedule-item">
                                <div class="schedule-info"><?= $uName ?></div>
                                <div class="schedule-time"><?= $time ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty" style="padding:1rem"><i class="fa-solid fa-calendar-xmark" style="font-size:1.2rem"></i><p>Tidak ada jadwal hari ini</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP LAYANAN + PELANGGAN TERBARU -->
        <div class="grid-equal">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-trophy"></i> Layanan Terpopuler</div>
                    <a href="layanan.php" class="card-link">Kelola <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if(!empty($topLayanan)): foreach($topLayanan as $i=>$tl):
                        $rc = $i===0?'gold':($i===1?'silver':($i===2?'bronze':'normal'));
                        $icon = !empty($tl['icon']) ? $tl['icon'] : '🧺';
                    ?>
                    <div class="list-item">
                        <div class="rank-badge <?= $rc ?>"><?= $i+1 ?></div>
                        <span class="item-icon"><?= $icon ?></span>
                        <div class="item-info">
                            <div class="item-title"><?= htmlspecialchars($tl['nama_layanan']) ?></div>
                            <div class="item-sub"><?= $tl['total_qty'] ?>x dipesan</div>
                        </div>
                        <div class="item-val"><?= formatRupiah($tl['total_rev']) ?></div>
                    </div>
                    <?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-inbox"></i><p>Belum ada data</p></div><?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-clock"></i> Pelanggan Terbaru</div>
                    <a href="users.php" class="card-link">Semua <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if(!empty($recentUsers)): foreach($recentUsers as $u):
                        $uName = htmlspecialchars($u[$uNameCol] ?? '?');
                        $uRole = $u['role'] ?? 'user';
                        $avClass = $uRole === 'admin' ? 'av-admin' : 'av-user';
                        $roleClass = $uRole === 'admin' ? 'admin' : 'user';
                        $roleLabel = $uRole === 'admin' ? 'Admin' : 'User';
                        $detail = [];
                        if(!empty($u['email'])) $detail[] = $u['email'];
                        if(!empty($u['phone'])) $detail[] = $u['phone'];
                        $detailStr = implode(' · ', $detail) ?: '-';
                    ?>
                    <div class="list-item">
                        <div class="user-avatar-lg <?= $avClass ?>"><?= strtoupper(substr($uName,0,1)) ?></div>
                        <div class="item-info">
                            <div class="item-title"><?= $uName ?></div>
                            <div class="item-sub"><?= htmlspecialchars($detailStr) ?></div>
                        </div>
                        <span class="role-badge <?= $roleClass ?>"><?= $roleLabel ?></span>
                    </div>
                    <?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-inbox"></i><p>Belum ada user</p></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PEMBAYARAN PENDING + AKSI CEPAT -->
        <div class="grid-equal" id="payPendingSection">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-hourglass-half"></i> Pembayaran Menunggu</div>
                    <?php if($payPending > 0): ?><a href="payments.php?filter=pending" class="card-link">Lihat Semua <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if(!empty($pendingPayments)): foreach($pendingPayments as $pp):
                        $ppKode = !empty($pp['kode_order']) ? $pp['kode_order'] : '#' . str_pad($pp[$payOrderCol] ?? 0, 4, '0', STR_PAD_LEFT);
                        $ppAmount = $pp[$payAmountCol] ?? 0;
                    ?>
                    <div class="pay-item">
                        <div class="pay-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="item-info">
                            <div class="item-title"><?= htmlspecialchars($ppKode) ?></div>
                            <div class="item-sub"><?= htmlspecialchars($pp['user_name'] ?? 'Unknown') ?></div>
                        </div>
                        <div class="pay-amount"><?= formatRupiah($ppAmount) ?></div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="empty"><i class="fa-solid fa-circle-check"></i><p>Semua pembayaran sudah diproses</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-bolt"></i> Aksi Cepat</div></div>
                <div class="card-body">
                    <div class="quick-grid">
                        <a href="reports.php" class="quick-card"><div class="quick-icon" style="background:rgba(16,185,129,.1);color:#10b981"><i class="fa-solid fa-chart-line"></i></div><div class="quick-label">Total Revenue</div><div class="quick-desc"><?= formatRupiah($tRevenue) ?></div></a>
                        
                        <a href="orders.php?filter=pending" class="quick-card"><div class="quick-icon" style="background:rgba(245,158,11,.1);color:#f59e0b"><i class="fa-solid fa-eye"></i></div><div class="quick-label">Kelola Pesanan</div><div class="quick-desc"><?= $tOrdersPending ?> pending</div></a>
                        
                        <a href="layanan.php" class="quick-card"><div class="quick-icon" style="background:rgba(236,72,153,.1);color:var(--pk3)"><i class="fa-solid fa-plus"></i></div><div class="quick-label">Tambah Layanan</div><div class="quick-desc"><?= $tLayanan ?> aktif</div></a>
                        
                        <a href="users.php" class="quick-card"><div class="quick-icon" style="background:rgba(59,130,246,.1);color:#3b82f6"><i class="fa-solid fa-user-check"></i></div><div class="quick-label">Kelola User</div><div class="quick-desc"><?= $tUsers ?> pelanggan</div></a>
                        
                        <a href="payments.php" class="quick-card"><div class="quick-icon" style="background:rgba(139,92,246,.1);color:#8b5cf6"><i class="fa-solid fa-credit-card"></i></div><div class="quick-label">Pembayaran</div><div class="quick-desc"><?= $payPending ?> menunggu</div></a>
                        
                        <?php if($hasJadwal): ?>
                        <a href="#" class="quick-card"><div class="quick-icon" style="background:rgba(6,182,212,.1);color:#06b6d4"><i class="fa-solid fa-calendar-check"></i></div><div class="quick-label">Self-Service</div><div class="quick-desc"><?= $ssToday ?> hari ini</div></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align:center;padding:1.5rem 0 .5rem;font-size:.72rem;color:var(--t4);position:relative;z-index:1"><i class="fa-solid fa-shield-halved" style="margin-right:4px"></i> CUCI.IN Admin Panel v1.0 &mdash; <?= date('Y') ?></div>
    </main>
</div>
<script>
setTimeout(function(){document.querySelectorAll('.chart-bar').forEach(function(b){b.style.height=b.dataset.h+'px'})},300);
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
window.addEventListener('resize',function(){if(window.innerWidth>768){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}});
</script>
</body>
</html>