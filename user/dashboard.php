<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}
if (!function_exists('getFlash')) {
    function getFlash() {
        $msg = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $msg;
    }
}
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}
if (!function_exists('getCartCount')) {
    function getCartCount($uid) {
        global $db;
        if (!$db) return 0;
        $r = $db->query("SELECT COUNT(*) as c FROM keranjang WHERE id_user=".(int)$uid);
        return $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;
    }
}
if (!defined('APP_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = str_replace('/user', '', dirname($_SERVER['SCRIPT_NAME']));
    define('APP_URL', $protocol . '://' . $host . $baseDir);
}

if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/login.php');
    exit;
}

 $userId = (int)$_SESSION['user_id'];
 $userNama = $_SESSION['userNama'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User';
 $userEmail = $_SESSION['user_email'] ?? '';
 $flash = getFlash();

// ===== STATS =====
 $totalOrders = 0; $activeOrders = 0; $totalSpent = 0;
 $cartCount = getCartCount($userId);
 $completedOrders = 0; $cancelledOrders = 0;
 $pendingOrders = 0; $totalBerat = 0;

if (isset($db) && $db) {
    try {
        $r = $db->query("SELECT COUNT(*) as c FROM orders WHERE id_user=$userId AND status NOT IN ('dibatalkan')");
        $totalOrders = (int)($r ? $r->fetch_assoc()['c'] : 0);
        $r = $db->query("SELECT COUNT(*) as c FROM orders WHERE id_user=$userId AND status NOT IN ('selesai','dibatalkan')");
        $activeOrders = (int)($r ? $r->fetch_assoc()['c'] : 0);
        $r = $db->query("SELECT COALESCE(SUM(total_harga),0) as t FROM orders WHERE id_user=$userId AND status='selesai'");
        $totalSpent = (float)($r ? $r->fetch_assoc()['t'] : 0);
        $r = $db->query("SELECT COUNT(*) as c FROM orders WHERE id_user=$userId AND status='selesai'");
        $completedOrders = (int)($r ? $r->fetch_assoc()['c'] : 0);
        $r = $db->query("SELECT COUNT(*) as c FROM orders WHERE id_user=$userId AND status='dibatalkan'");
        $cancelledOrders = (int)($r ? $r->fetch_assoc()['c'] : 0);
        $r = $db->query("SELECT COUNT(*) as c FROM orders WHERE id_user=$userId AND status='pending'");
        $pendingOrders = (int)($r ? $r->fetch_assoc()['c'] : 0);
        $r = $db->query("SELECT COALESCE(SUM(berat),0) as t FROM orders WHERE id_user=$userId AND status='selesai'");
        $totalBerat = (float)($r ? $r->fetch_assoc()['t'] : 0);
    } catch (Exception $e) {}
}

// ===== DETECT COLUMN NAMES =====
 $colCode = 'order_code'; $colDate = 'created_at';
if (isset($db) && $db) {
    try {
        $cols = $db->query("SHOW COLUMNS FROM orders")->fetch_all(MYSQLI_ASSOC);
        $colNames = array_column($cols, 'Field');
        if (in_array('kode_order', $colNames)) $colCode = 'kode_order';
        if (in_array('tanggal_order', $colNames)) $colDate = 'tanggal_order';
    } catch (Exception $e) {}
}

// ===== RECENT ORDERS (max 4) =====
 $recentOrders = [];
if (isset($db) && $db) {
    try {
        $res = $db->query("SELECT o.*, p.status_pembayaran FROM orders o LEFT JOIN pembayaran p ON o.id_order=p.id_order WHERE o.id_user=$userId ORDER BY o.$colDate DESC LIMIT 4");
        if ($res) $recentOrders = $res->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

// ===== ACTIVE ORDERS WITH TRACKING =====
 $activeOrderList = []; $trackingData = [];
if (isset($db) && $db && $activeOrders > 0) {
    try {
        $res = $db->query("SELECT * FROM orders WHERE id_user=$userId AND status NOT IN ('selesai','dibatalkan') ORDER BY $colDate DESC LIMIT 5");
        if ($res) $activeOrderList = $res->fetch_all(MYSQLI_ASSOC);
        if (!empty($activeOrderList)) {
            $ids = array_column($activeOrderList, 'id_order');
            $idsStr = implode(',', array_map('intval', $ids));
            $tRes = $db->query("SELECT * FROM tracking WHERE id_order IN ($idsStr) ORDER BY updated_at DESC");
            if ($tRes) { while ($t = $tRes->fetch_assoc()) { $trackingData[$t['id_order']][] = $t; } }
        }
    } catch (Exception $e) {}
}

// ===== LAYANAN =====
 $layanan = [];
if (isset($db) && $db) {
    try {
        $layanan = $db->query("SELECT * FROM layanan WHERE is_active=1 ORDER BY harga_per_kg ASC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

// ===== MONTHLY SPENDING (6 bulan terakhir) =====
 $monthlySpent = [];
if (isset($db) && $db) {
    try {
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-$i months"));
            $r = $db->query("SELECT COALESCE(SUM(total_harga),0) as t FROM orders WHERE id_user=$userId AND status='selesai' AND DATE_FORMAT($colDate,'%Y-%m')='$m'");
            $val = $r ? (float)$r->fetch_assoc()['t'] : 0;
            $monthlySpent[] = ['month' => date('M', strtotime($m)), 'value' => $val];
        }
    } catch (Exception $e) {}
}
 $maxMonthly = max(array_column($monthlySpent, 'value')) ?: 1;

// ===== STATUS BREAKDOWN =====
 $statusBreakdown = [];
if (isset($db) && $db) {
    try {
        $r = $db->query("SELECT status, COUNT(*) as c FROM orders WHERE id_user=$userId GROUP BY status");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $statusBreakdown[$row['status']] = (int)$row['c'];
            }
        }
    } catch (Exception $e) {}
}
 $totalAllOrders = array_sum($statusBreakdown) ?: 1;

// ===== MOST USED SERVICE =====
 $favLayanan = null;
if (isset($db) && $db) {
    try {
        $r = $db->query("SELECT l.nama_layanan, l.harga_per_kg, COUNT(*) as cnt FROM orders o JOIN layanan l ON o.id_layanan=l.id_layanan WHERE o.id_user=$userId GROUP BY o.id_layanan ORDER BY cnt DESC LIMIT 1");
        if ($r && $r->num_rows > 0) $favLayanan = $r->fetch_assoc();
    } catch (Exception $e) {}
}

// ===== ACTIVITY LOG =====
 $activityLog = [];
if (isset($db) && $db) {
    try {
        $r = $db->query("SELECT t.*, o.$colCode as kode FROM tracking t JOIN orders o ON t.id_order=o.id_order WHERE o.id_user=$userId ORDER BY t.updated_at DESC LIMIT 8");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $activityLog[] = $row;
            }
        }
    } catch (Exception $e) {}
}

// ===== HELPERS =====
 $statusLabels = ['pending'=>'Pending','dikonfirmasi'=>'Dikonfirmasi','diproses'=>'Diproses','dicuci'=>'Dicuci','selesai'=>'Selesai','diantar'=>'Diantar','dibatalkan'=>'Dibatalkan'];
 $statusColors = ['pending'=>'#f59e0b','dikonfirmasi'=>'#3b82f6','diproses'=>'#ec4899','dicuci'=>'#8b5cf6','selesai'=>'#22c55e','diantar'=>'#06b6d4','dibatalkan'=>'#ef4444'];
 $statusIcons = ['pending'=>'fa-clock','dikonfirmasi'=>'fa-circle-check','diproses'=>'fa-gears','dicuci'=>'fa-droplet','selesai'=>'fa-flag-checkered','diantar'=>'fa-truck','dibatalkan'=>'fa-ban'];

function getProgressPercent($status) {
    $map = ['pending'=>0,'dikonfirmasi'=>25,'diproses'=>50,'dicuci'=>75,'selesai'=>100,'diantar'=>90];
    return $map[$status] ?? 0;
}

// ===== HANDLE POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel_order') {
        $idOrder = (int)$_POST['id_order'];
        $cRes = $db->query("SELECT status FROM orders WHERE id_order=$idOrder AND id_user=$userId LIMIT 1");
        if ($cRes && $cRes->num_rows > 0) {
            $ord = $cRes->fetch_assoc();
            if ($ord['status'] === 'pending') {
                $db->query("UPDATE orders SET status='dibatalkan' WHERE id_order=$idOrder AND id_user=$userId");
                $db->query("INSERT INTO tracking (id_order, status, keterangan, updated_by) VALUES ($idOrder, 'dibatalkan', 'Dibatalkan oleh user', $userId)");
                echo json_encode(['success' => true]);
            } else { echo json_encode(['success' => false, 'message' => 'Hanya pesanan pending yang bisa dibatalkan']); }
        } else { echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']); }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali']); exit;
}

 $pageTitle = 'Dashboard — CUCI.IN';
 $hour = (int)date('H');
if ($hour < 11) $greeting = 'Selamat Pagi';
elseif ($hour < 15) $greeting = 'Selamat Siang';
elseif ($hour < 18) $greeting = 'Selamat Sore';
else $greeting = 'Selamat Malam';
 $dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
 $dayName = $dayNames[date('w')];

// Tips laundry
 $tips = [
    ['icon'=>'fa-temperature-low','title'=>'Pisahkan Warna','desc'=>'Cuci pakaian gelap dan terang terpisah untuk menghindari luntur.'],
    ['icon'=>'fa-shirt','title'=>'Jangan Overload','desc'=>'Mesin cuci yang terlalu penuh membuat hasil cuci kurang bersih.'],
    ['icon'=>'fa-spray-can-sparkles','title'=>'Pretreatment Noda','desc'=>'Semprotkan cairan pembersih pada noda sebelum dicuci untuk hasil maksimal.'],
    ['icon'=>'fa-sun','title'=>'Jemur Terbalik','desc'=>'Jemur pakaian dengan bagian dalam keluar agar warna tidak cepat pudar.']
];
 $randomTip = $tips[date('d') % count($tips)];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --pk50:#fdf2f8;--pk100:#fce7f3;--pk200:#fbcfe8;--pk300:#f9a8d4;
            --pk400:#f472b6;--pk500:#ec4899;--pk600:#db2777;--pk700:#be185d;--pk800:#9d174d;
            --w:#fff;--g50:#f9fafb;--g100:#f3f4f6;--g200:#e5e7eb;--g300:#d1d5db;
            --g400:#9ca3af;--g500:#6b7280;--g600:#4b5563;--g700:#374151;--g800:#1f2937;--g900:#111827;
            --sidebar-w:250px;
            --sh:0 4px 20px rgba(236,72,153,.08);--shl:0 12px 40px rgba(236,72,153,.12);
            --tr:all .3s cubic-bezier(.4,0,.2,1);
        }
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--g50);color:var(--g800);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit;transition:var(--tr)}
        button{cursor:pointer;border:none;font-family:inherit}

        /* SIDEBAR */
        .sidebar{width:var(--sidebar-w);min-height:100vh;background:linear-gradient(180deg,#4a1225 0%,#6b1535 100%);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:var(--tr);border-right:1px solid rgba(236,72,153,.15)}
        .sidebar-brand{padding:1.5rem 1.2rem;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.08)}
        .sidebar-brand img{height:34px;width:34px;object-fit:contain;filter:brightness(10)}
        .sidebar-brand span{font-size:1.2rem;font-weight:900;background:linear-gradient(135deg,var(--pk400),var(--pk600));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .sidebar-nav{flex:1;padding:1rem .7rem;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
        .sidebar-label{font-size:.65rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:2px;padding:1rem .8rem .4rem}
        .sidebar-link{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:12px;font-size:.85rem;font-weight:500;color:rgba(255,255,255,.5);transition:var(--tr);cursor:pointer}
        .sidebar-link:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.85)}
        .sidebar-link.active{background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;font-weight:600;box-shadow:0 4px 18px rgba(236,72,153,.3)}
        .sidebar-link i{width:20px;text-align:center;font-size:.9rem}
        .sidebar-link .badge-ct{margin-left:auto;background:rgba(255,255,255,.2);color:#fff;padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:700}
        .sidebar-link:not(.active) .badge-ct{background:rgba(255,255,255,.1);color:rgba(255,255,255,.6)}
        .sidebar-link.danger{color:#fca5a5}.sidebar-link.danger:hover{background:rgba(239,68,68,.12);color:#f87171}
        .sidebar-user{padding:1.2rem;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px}
        .sidebar-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--pk400),var(--pk700));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.9rem;flex-shrink:0}
        .sidebar-uname{font-size:.85rem;font-weight:600;color:#fff}
        .sidebar-uemail{font-size:.7rem;color:rgba(255,255,255,.4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}
        .sidebar-toggle{display:none;position:fixed;top:1rem;left:1rem;z-index:200;width:44px;height:44px;border-radius:12px;background:#4a1225;border:none;color:#fff;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:90}

        /* MAIN */
        .main-ct{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column}
        .topbar{height:60px;background:var(--w);border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:50}
        .topbar-title{font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .topbar-title i{color:var(--pk500)}
        .topbar-right{display:flex;align-items:center;gap:8px}
        .topbar-btn{color:var(--g500);font-size:.82rem;font-weight:600;padding:7px 14px;border-radius:10px;transition:var(--tr);display:flex;align-items:center;gap:6px}
        .topbar-btn:hover{background:var(--g50);color:var(--g700)}
        .topbar-btn.logout:hover{background:#fef2f2;color:#ef4444}
        .content{flex:1;padding:1.5rem 2rem 2rem}
        .content-in{max-width:1100px;margin:0 auto}

        /* HERO */
        .hero{background:linear-gradient(135deg,#4a1225 0%,#831843 50%,#be185d 100%);border-radius:24px;padding:2.5rem 2.5rem;margin-bottom:2rem;color:#fff;position:relative;overflow:hidden}
        .hero::after{content:'';position:absolute;top:-50px;right:-30px;width:220px;height:220px;border-radius:50%;background:rgba(244,114,182,.15)}
        .hero::before{content:'';position:absolute;bottom:-80px;right:120px;width:180px;height:180px;border-radius:50%;background:rgba(236,72,153,.1)}
        .hero-greeting{font-size:.9rem;color:rgba(255,255,255,.6);font-weight:500;margin-bottom:4px;position:relative;z-index:1}
        .hero h1{font-size:1.8rem;font-weight:900;margin-bottom:6px;position:relative;z-index:1}
        .hero h1 span{color:var(--pk300)}
        .hero-sub{font-size:.88rem;color:rgba(255,255,255,.55);margin-bottom:1.5rem;position:relative;z-index:1}
        .hero-actions{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1}
        .hero-date{position:absolute;top:2rem;right:2.5rem;text-align:right;z-index:1}
        .hero-day{font-size:2.5rem;font-weight:900;line-height:1}
        .hero-month{font-size:.72rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;margin-top:2px}
        .hero-dow{font-size:.82rem;color:rgba(255,255,255,.6);font-weight:600;margin-top:4px}

        /* BUTTONS */
        .btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:12px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:var(--tr)}
        .btn-w{background:#fff;color:var(--pk700);box-shadow:0 4px 15px rgba(0,0,0,.15)}
        .btn-w:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.2)}
        .btn-wo{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.3)}
        .btn-wo:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.5)}
        .btn-primary{background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;box-shadow:0 4px 15px rgba(236,72,153,.25)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.35)}
        .btn-ghost{background:var(--w);color:var(--g600);border:1.5px solid var(--g200)}
        .btn-ghost:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}
        .btn-sm{padding:6px 14px;border-radius:8px;font-size:.78rem;font-weight:600}
        .btn-track{background:var(--pk50);color:var(--pk700)}.btn-track:hover{background:var(--pk100)}
        .btn-cancel{background:#fef2f2;color:#ef4444;border:1px solid #fecaca}.btn-cancel:hover{background:#fee2e2}

        /* STATS */
        .stats-g{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
        .st-c{background:var(--w);border:1px solid var(--g100);border-radius:18px;padding:1.4rem 1.3rem;display:flex;align-items:center;gap:1rem;transition:var(--tr);position:relative;overflow:hidden}
        .st-c:hover{transform:translateY(-3px);box-shadow:var(--shl)}
        .st-c::after{content:'';position:absolute;top:0;right:0;width:80px;height:80px;border-radius:50%;opacity:.06;transform:translate(20px,-20px)}
        .st-c.c1::after{background:var(--pk500)}.st-c.c2::after{background:#f59e0b}.st-c.c3::after{background:#22c55e}.st-c.c4::after{background:#3b82f6}
        .st-ic{width:50px;height:50px;min-width:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
        .st-ic.c1{background:var(--pk50);color:var(--pk600)}.st-ic.c2{background:#fef3c7;color:#f59e0b}.st-ic.c3{background:#dcfce7;color:#22c55e}.st-ic.c4{background:#dbeafe;color:#3b82f6}
        .st-val{font-size:1.6rem;font-weight:900;color:var(--g900);line-height:1}
        .st-lb{font-size:.78rem;color:var(--g500);font-weight:500;margin-top:3px}
        .st-change{font-size:.7rem;font-weight:600;margin-top:2px;display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:6px}
        .st-change.up{color:#059669;background:#d1fae5}
        .st-change.warn{color:#d97706;background:#fef3c7}

        /* SECTION */
        .sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
        .sec-title{font-size:1.05rem;font-weight:800;color:var(--g900);display:flex;align-items:center;gap:8px}
        .sec-title i{color:var(--pk500);font-size:.9rem}
        .sec-link{font-size:.82rem;font-weight:600;color:var(--pk500);display:flex;align-items:center;gap:4px}
        .sec-link:hover{color:var(--pk700);gap:6px}

        /* QUICK ACTIONS */
        .quick-g{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
        .qa-c{background:var(--w);border:1px solid var(--g100);border-radius:16px;padding:1.5rem 1.2rem;text-align:center;transition:var(--tr);cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:10px}
        .qa-c:hover{transform:translateY(-4px);box-shadow:var(--shl);border-color:rgba(236,72,153,.2)}
        .qa-ic{width:54px;height:54px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;transition:var(--tr)}
        .qa-c:hover .qa-ic{transform:scale(1.1)}
        .qa-ic.c1{background:linear-gradient(135deg,#fce7f3,#fbcfe8);color:var(--pk600)}
        .qa-ic.c2{background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#2563eb}
        .qa-ic.c3{background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#16a34a}
        .qa-ic.c4{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#d97706}
        .qa-lb{font-size:.88rem;font-weight:700;color:var(--g800)}
        .qa-desc{font-size:.75rem;color:var(--g400);line-height:1.4}

        /* GRID */
        .grid2{display:grid;grid-template-columns:1.3fr 1fr;gap:1.5rem;margin-bottom:2rem}
        .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:2rem}

        /* CARD */
        .card{background:var(--w);border:1px solid var(--g100);border-radius:18px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.02)}
        .card-hd{padding:1rem 1.4rem;border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between}
        .card-hd h2{font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .card-hd h2 i{color:var(--pk500);font-size:.85rem}
        .card-bd{padding:1.4rem}

        /* ACTIVE ORDER */
        .ao-card{padding:1.2rem;border:1.5px solid var(--g100);border-radius:14px;margin-bottom:1rem;transition:var(--tr);background:var(--w)}
        .ao-card:hover{border-color:var(--pk200);box-shadow:var(--sh)}
        .ao-card:last-child{margin-bottom:0}
        .ao-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px}
        .ao-code{font-size:.9rem;font-weight:800;color:var(--g800)}
        .ao-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;color:#fff}
        .ao-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:#fff}
        .ao-info{font-size:.78rem;color:var(--g500);margin-bottom:12px;display:flex;gap:14px;flex-wrap:wrap}
        .ao-info span{display:flex;align-items:center;gap:4px}
        .prog-wrap{margin-bottom:6px}
        .prog-bar{height:8px;background:var(--g100);border-radius:10px;overflow:hidden}
        .prog-fill{height:100%;border-radius:10px;transition:width .8s cubic-bezier(.4,0,.2,1);position:relative}
        .prog-fill::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);animation:shimmer 2s infinite}
        @keyframes shimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
        .prog-labels{display:flex;justify-content:space-between;font-size:.65rem;color:var(--g400);font-weight:600;margin-top:5px}
        .prog-labels .current{color:var(--pk600);font-weight:700}
        .ao-actions{display:flex;gap:8px;margin-top:12px}

        /* RECENT */
        .ro-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--g50)}
        .ro-item:last-child{border-bottom:none}
        .ro-ic{width:42px;height:42px;min-width:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem}
        .ro-ic.selesai{background:#dcfce7;color:#22c55e}
        .ro-ic.active{background:var(--pk50);color:var(--pk600)}
        .ro-ic.batal{background:#fee2e2;color:#ef4444}
        .ro-ic.pending{background:#fef3c7;color:#f59e0b}
        .ro-info{flex:1;min-width:0}
        .ro-code{font-size:.85rem;font-weight:700;color:var(--g800)}
        .ro-meta{font-size:.75rem;color:var(--g400);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ro-right{text-align:right}
        .ro-price{font-size:.88rem;font-weight:800;color:var(--pk600)}
        .ro-status{font-size:.68rem;font-weight:600;color:var(--g500);margin-top:2px}

        /* BAR CHART */
        .chart-bars{display:flex;align-items:flex-end;gap:8px;height:140px;padding-top:10px}
        .chart-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px}
        .chart-bar-wrap{width:100%;height:110px;display:flex;align-items:flex-end;justify-content:center}
        .chart-bar{width:70%;max-width:40px;border-radius:8px 8px 4px 4px;transition:height 1s cubic-bezier(.4,0,.2,1);position:relative;min-height:4px}
        .chart-bar:hover{filter:brightness(1.1)}
        .chart-bar .chart-val{position:absolute;top:-22px;left:50%;transform:translateX(-50%);font-size:.65rem;font-weight:700;color:var(--g600);white-space:nowrap;opacity:0;transition:var(--tr)}
        .chart-bar:hover .chart-val{opacity:1}
        .chart-lbl{font-size:.68rem;font-weight:600;color:var(--g400)}

        /* DONUT (CSS only) */
        .donut-wrap{display:flex;align-items:center;gap:1.5rem}
        .donut{width:120px;height:120px;border-radius:50%;position:relative;flex-shrink:0}
        .donut-center{position:absolute;inset:20px;background:var(--w);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-direction:column;z-index:1}
        .donut-center .dc-val{font-size:1.3rem;font-weight:900;color:var(--g900);line-height:1}
        .donut-center .dc-lbl{font-size:.6rem;color:var(--g400);font-weight:600;margin-top:1px}
        .donut-legend{display:flex;flex-direction:column;gap:6px;flex:1}
        .donut-legend-item{display:flex;align-items:center;gap:8px;font-size:.78rem}
        .donut-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}
        .donut-legend-label{color:var(--g600);flex:1}
        .donut-legend-val{font-weight:700;color:var(--g800)}

        /* FAVORITE SERVICE */
        .fav-card{display:flex;align-items:center;gap:1rem;padding:1.2rem;background:linear-gradient(135deg,var(--pk50),var(--pk100));border-radius:14px;border:1.5px solid var(--pk200)}
        .fav-ic{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,var(--pk400),var(--pk600));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;box-shadow:0 4px 12px rgba(236,72,153,.3)}
        .fav-info{flex:1}
        .fav-label{font-size:.72rem;font-weight:600;color:var(--pk600);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px}
        .fav-name{font-size:1rem;font-weight:800;color:var(--g800)}
        .fav-meta{font-size:.78rem;color:var(--g500);margin-top:2px}

        /* ACTIVITY FEED */
        .act-item{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--g50);align-items:flex-start}
        .act-item:last-child{border-bottom:none}
        .act-ic{width:34px;height:34px;min-width:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.8rem}
        .act-ic.pink{background:var(--pk50);color:var(--pk600)}
        .act-ic.green{background:#dcfce7;color:#22c55e}
        .act-ic.red{background:#fee2e2;color:#ef4444}
        .act-ic.blue{background:#dbeafe;color:#3b82f6}
        .act-ic.yellow{background:#fef3c7;color:#f59e0b}
        .act-info{flex:1;min-width:0}
        .act-text{font-size:.82rem;color:var(--g700);line-height:1.4}
        .act-text strong{color:var(--g900)}
        .act-time{font-size:.7rem;color:var(--g400);margin-top:2px}

        /* TIP CARD */
        .tip-card{display:flex;align-items:flex-start;gap:1rem;padding:1.2rem;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:14px;border:1.5px solid #bfdbfe}
        .tip-ic{width:46px;height:46px;min-width:46px;border-radius:12px;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem}
        .tip-info{flex:1}
        .tip-label{font-size:.68rem;font-weight:700;color:#2563eb;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px}
        .tip-title{font-size:.9rem;font-weight:700;color:var(--g800);margin-bottom:2px}
        .tip-desc{font-size:.78rem;color:var(--g500);line-height:1.5}

        /* PROMO BANNER */
        .promo-banner{background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:16px;padding:1.5rem 1.8rem;display:flex;align-items:center;gap:1.2rem;border:1.5px solid #fcd34d;position:relative;overflow:hidden}
        .promo-banner::after{content:'';position:absolute;top:-20px;right:-10px;width:100px;height:100px;border-radius:50%;background:rgba(251,191,36,.2)}
        .promo-ic{font-size:2.2rem;z-index:1}
        .promo-text{flex:1;z-index:1}
        .promo-text h3{font-size:1rem;font-weight:800;color:#92400e;margin-bottom:2px}
        .promo-text p{font-size:.82rem;color:#a16207}
        .promo-btn{z-index:1;background:#f59e0b;color:#fff;padding:8px 18px;border-radius:10px;font-size:.82rem;font-weight:700;transition:var(--tr);white-space:nowrap}
        .promo-btn:hover{background:#d97706;transform:translateY(-2px)}

        /* LAYANAN */
        .lay-g{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
        .lay-c{background:var(--w);border:1px solid var(--g100);border-radius:14px;padding:1.2rem;transition:var(--tr);display:flex;align-items:center;gap:12px}
        .lay-c:hover{transform:translateY(-3px);box-shadow:var(--shl);border-color:rgba(236,72,153,.15)}
        .lay-ic{width:46px;height:46px;min-width:46px;border-radius:12px;background:var(--pk50);color:var(--pk500);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
        .lay-info{flex:1}
        .lay-name{font-size:.85rem;font-weight:700;color:var(--g800)}
        .lay-type{font-size:.72rem;color:var(--g400);margin-top:1px}
        .lay-price{font-size:.9rem;font-weight:800;color:var(--pk600);white-space:nowrap}
        .lay-unit{font-size:.7rem;font-weight:500;color:var(--g400)}

        /* EMPTY */
        .empty-state{text-align:center;padding:2.5rem 1rem;color:var(--g400)}
        .empty-state i{font-size:2.5rem;margin-bottom:.8rem;color:var(--g300)}
        .empty-state p{font-size:.88rem}
        .empty-state .btn{margin-top:1rem}

        /* MODAL */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;align-items:center;justify-content:center;padding:1rem}
        .modal-overlay.show{display:flex}
        .modal{background:var(--w);border-radius:20px;width:100%;max-width:520px;max-height:85vh;overflow-y:auto;animation:fadeUp .3s ease}
        .modal-head{padding:1.2rem 1.5rem;border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between}
        .modal-head h3{font-size:1rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .modal-head h3 i{color:var(--pk500)}
        .modal-close{width:32px;height:32px;border-radius:8px;border:none;background:var(--g100);color:var(--g500);cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:var(--tr)}
        .modal-close:hover{background:#fee2e2;color:#ef4444}
        .modal-body{padding:1.5rem}
        .timeline{position:relative;padding-left:28px}
        .timeline::before{content:'';position:absolute;left:8px;top:4px;bottom:4px;width:2px;background:var(--g200)}
        .tl-item{position:relative;padding-bottom:1.2rem}
        .tl-item:last-child{padding-bottom:0}
        .tl-dot{position:absolute;left:-24px;top:3px;width:16px;height:16px;border-radius:50%;border:2.5px solid var(--g300);background:var(--w)}
        .tl-item:first-child .tl-dot{border-color:var(--pk500);background:var(--pk500)}
        .tl-item:first-child .tl-dot::after{content:'';position:absolute;inset:3px;border-radius:50%;background:#fff}
        .tl-status{font-size:.82rem;font-weight:700;color:var(--g800)}
        .tl-item:first-child .tl-status{color:var(--pk600)}
        .tl-time{font-size:.72rem;color:var(--g400);margin-top:2px}
        .tl-desc{font-size:.78rem;color:var(--g500);margin-top:1px}

        /* FLASH */
        .flash-box{padding:1rem 1.2rem;border-radius:16px;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;font-size:.88rem;font-weight:600;animation:fadeUp .5s ease}
        .flash-ok{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}

        @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
        .fi{opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease}
        .fi.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}.d5{transition-delay:.25s}.d6{transition-delay:.3s}.d7{transition-delay:.35s}.d8{transition-delay:.4s}

        .toast{position:fixed;bottom:2rem;right:2rem;z-index:999;padding:14px 22px;border-radius:14px;font-size:.85rem;font-weight:600;color:#fff;display:flex;align-items:center;gap:10px;animation:toastIn .4s ease,toastOut .4s 3s ease forwards;pointer-events:none}
        .toast.success{background:#22c55e}.toast.error{background:#ef4444}
        @keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
        @keyframes toastOut{to{opacity:0;transform:translateY(20px)}}

        @media(max-width:1024px){
            .stats-g{grid-template-columns:repeat(2,1fr)}
            .quick-g{grid-template-columns:repeat(2,1fr)}
            .grid2{grid-template-columns:1fr}
            .grid3{grid-template-columns:1fr}
            .lay-g{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}.sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}.topbar{padding:0 1rem}.content{padding:1rem}
            .stats-g{grid-template-columns:1fr 1fr}
            .quick-g{grid-template-columns:1fr 1fr}
            .hero{padding:1.5rem}.hero h1{font-size:1.3rem}.hero-date{display:none}
            .hero-actions{flex-direction:column}.hero-actions .btn{justify-content:center}
            .lay-g{grid-template-columns:1fr}
            .donut-wrap{flex-direction:column}.donut{width:100px;height:100px}
            .promo-banner{flex-direction:column;text-align:center}
        }
    </style>
</head>
<body>

<button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>" style="display:flex;align-items:center;gap:10px">
            <img src="<?= APP_URL ?>/img/logo.png" alt="CUCI.IN" onerror="this.style.display='none'">
            <span>CUCI.IN</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-label">Menu Utama</div>
        <a href="dashboard.php" class="sidebar-link active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="layanan.php" class="sidebar-link"><i class="fa-solid fa-shirt"></i> Layanan</a>
        <a href="cart.php" class="sidebar-link"><i class="fa-solid fa-cart-shopping"></i> Keranjang <?php if($cartCount > 0): ?><span class="badge-ct"><?= $cartCount ?></span><?php endif; ?></a>
        <a href="orders.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> Pesanan Saya</a>
        <div class="sidebar-label">Akun</div>
        <a href="<?= APP_URL ?>" class="sidebar-link"><i class="fa-solid fa-globe"></i> Beranda</a>
        <a href="logout.php" class="sidebar-link danger"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </nav>
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($userNama, 0, 1)) ?></div>
        <div>
            <div class="sidebar-uname"><?= htmlspecialchars($userNama) ?></div>
            <div class="sidebar-uemail"><?= htmlspecialchars($userEmail) ?></div>
        </div>
    </div>
</aside>

<div class="main-ct">
    <div class="topbar">
        <div class="topbar-title"><i class="fa-solid fa-shield-halved"></i> Dashboard</div>
        <div class="topbar-right">
            <a href="dashboard.php" class="topbar-btn"><i class="fa-solid fa-arrows-rotate"></i> Refresh</a>
            <a href="logout.php" class="topbar-btn logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>

    <div class="content">
    <div class="content-in">

        <?php if ($flash): ?>
        <div class="flash-box flash-ok fi">
            <i class="fa-solid fa-check-circle"></i>
            <span><?= htmlspecialchars(is_array($flash) ? ($flash['message'] ?? json_encode($flash)) : $flash) ?></span>
        </div>
        <?php endif; ?>

        <!-- HERO -->
        <div class="hero fi d1">
            <div class="hero-greeting"><?= $greeting ?>,</div>
            <h1><span><?= htmlspecialchars($userNama) ?></span> 👋</h1>
            <p class="hero-sub">Pantau cucianmu, pesan layanan baru, semua dari satu tempat.</p>
            <div class="hero-actions">
                <a href="layanan.php" class="btn btn-w"><i class="fa-solid fa-plus"></i> Pesan Layanan Baru</a>
                <a href="orders.php" class="btn btn-wo"><i class="fa-solid fa-list-check"></i> Lihat Semua Pesanan</a>
            </div>
            <div class="hero-date">
                <div class="hero-day"><?= date('d') ?></div>
                <div class="hero-month"><?= date('F Y') ?></div>
                <div class="hero-dow"><?= $dayName ?></div>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-g">
            <div class="st-c c1 fi d2">
                <div class="st-ic c1"><i class="fa-solid fa-box-archive"></i></div>
                <div>
                    <div class="st-val"><?= $totalOrders ?></div>
                    <div class="st-lb">Total Pesanan</div>
                </div>
            </div>
            <div class="st-c c2 fi d3">
                <div class="st-ic c2"><i class="fa-solid fa-spinner"></i></div>
                <div>
                    <div class="st-val"><?= $activeOrders ?></div>
                    <div class="st-lb">Sedang Proses</div>
                    <?php if($activeOrders > 0): ?><div class="st-change up"><i class="fa-solid fa-circle-dot"></i> Aktif</div><?php endif; ?>
                    <?php if($pendingOrders > 0): ?><div class="st-change warn"><i class="fa-solid fa-clock"></i> <?= $pendingOrders ?> pending</div><?php endif; ?>
                </div>
            </div>
            <div class="st-c c3 fi d4">
                <div class="st-ic c3"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="st-val"><?= $completedOrders ?></div>
                    <div class="st-lb">Selesai</div>
                </div>
            </div>
            <div class="st-c c4 fi d5">
                <div class="st-ic c4"><i class="fa-solid fa-wallet"></i></div>
                <div>
                    <div class="st-val"><?= formatRupiah($totalSpent) ?></div>
                    <div class="st-lb">Total Belanja</div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="sec-hd fi d3"><div class="sec-title"><i class="fa-solid fa-bolt"></i> Aksi Cepat</div></div>
        <div class="quick-g fi d4">
            <a href="layanan.php" class="qa-c">
                <div class="qa-ic c1"><i class="fa-solid fa-plus"></i></div>
                <div class="qa-lb">Pesan Baru</div>
                <div class="qa-desc">Pilih layanan & buat pesanan</div>
            </a>
            <a href="cart.php" class="qa-c">
                <div class="qa-ic c2"><i class="fa-solid fa-cart-shopping"></i></div>
                <div class="qa-lb">Keranjang<?= $cartCount > 0 ? ' ('.$cartCount.')' : '' ?></div>
                <div class="qa-desc"><?= $cartCount > 0 ? $cartCount.' item menunggu' : 'Belum ada item' ?></div>
            </a>
            <a href="orders.php" class="qa-c">
                <div class="qa-ic c3"><i class="fa-solid fa-list-check"></i></div>
                <div class="qa-lb">Riwayat Pesanan</div>
                <div class="qa-desc"><?= $totalOrders ?> pesanan total</div>
            </a>
            <a href="orders.php?status=pending" class="qa-c">
                <div class="qa-ic c4"><i class="fa-solid fa-clock"></i></div>
                <div class="qa-lb">Pending</div>
                <div class="qa-desc"><?= $pendingOrders > 0 ? $pendingOrders.' perlu dibayar' : 'Semua selesai' ?></div>
            </a>
        </div>

        <!-- PROMO BANNER -->
        <div class="promo-banner fi d4" style="margin-bottom:2rem">
            <div class="promo-ic">🎁</div>
            <div class="promo-text">
                <h3>Pesan Sekarang, Dapat Diskon!</h3>
                <p>Gratis ongkir untuk pesanan antar-jemput pertamamu. Berlaku sampai akhir bulan.</p>
            </div>
            <a href="layanan.php" class="promo-btn">Pesan Sekarang →</a>
        </div>

        <!-- GRID: Active + Recent -->
        <div class="grid2 fi d5">
            <div class="card">
                <div class="card-hd">
                    <h2><i class="fa-solid fa-chart-line"></i> Sedang Proses</h2>
                    <?php if($activeOrders > 0): ?><a href="orders.php" class="sec-link">Lihat semua <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
                </div>
                <div class="card-bd">
                    <?php if(empty($activeOrderList)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-check-double"></i>
                        <p>Tidak ada pesanan aktif. Semua selesai! 🎉</p>
                        <a href="layanan.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Pesan Sekarang</a>
                    </div>
                    <?php else: ?>
                    <?php foreach($activeOrderList as $ao):
                        $aoKode = $ao[$colCode] ?? '-';
                        $aoStatus = $ao['status'] ?? 'pending';
                        $aoColor = $statusColors[$aoStatus] ?? '#6b7280';
                        $aoLabel = $statusLabels[$aoStatus] ?? ucfirst($aoStatus);
                        $aoPct = getProgressPercent($aoStatus);
                        $aoTracks = $trackingData[$ao['id_order']] ?? [];
                        $lastTrack = !empty($aoTracks) ? $aoTracks[0] : null;
                    ?>
                    <div class="ao-card">
                        <div class="ao-top">
                            <div class="ao-code"><?= htmlspecialchars($aoKode) ?></div>
                            <span class="ao-badge" style="background:<?= $aoColor ?>"><?= $aoLabel ?></span>
                        </div>
                        <div class="ao-info">
                            <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($ao[$colDate] ?? 'now')) ?></span>
                            <span><i class="fa-solid fa-weight-hanging"></i> <?= number_format($ao['berat'] ?? 0, 1) ?> kg</span>
                            <span><i class="fa-solid fa-tag"></i> <?= formatRupiah($ao['total_harga'] ?? 0) ?></span>
                        </div>
                        <div class="prog-wrap">
                            <div class="prog-bar">
                                <div class="prog-fill" style="width:<?= $aoPct ?>%;background:linear-gradient(90deg,<?= $aoColor ?>,<?= $aoColor ?>cc)"></div>
                            </div>
                            <div class="prog-labels">
                                <span class="current"><?= $lastTrack ? htmlspecialchars($lastTrack['keterangan'] ?? $aoLabel) : $aoLabel ?></span>
                                <span><?= $aoPct ?>%</span>
                            </div>
                        </div>
                        <div class="ao-actions">
                            <button class="btn btn-sm btn-track" onclick="showTracking(<?= $ao['id_order'] ?>, '<?= htmlspecialchars(addslashes($aoKode)) ?>')"><i class="fa-solid fa-route"></i> Tracking</button>
                            <?php if($aoStatus === 'pending'): ?>
                            <button class="btn btn-sm btn-cancel" onclick="cancelOrder(<?= $ao['id_order'] ?>)"><i class="fa-solid fa-xmark"></i> Batalkan</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-hd">
                    <h2><i class="fa-solid fa-clock-rotate-left"></i> Terbaru</h2>
                    <a href="orders.php" class="sec-link">Semua <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="card-bd">
                    <?php if(empty($recentOrders)): ?>
                    <div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada riwayat pesanan</p></div>
                    <?php else: ?>
                    <?php foreach($recentOrders as $ro):
                        $roKode = $ro[$colCode] ?? '-';
                        $roStatus = $ro['status'] ?? 'pending';
                        if($roStatus === 'selesai') $roIconClass = 'selesai';
                        elseif($roStatus === 'dibatalkan') $roIconClass = 'batal';
                        elseif(in_array($roStatus, ['diproses','dicuci','dikonfirmasi','diantar'])) $roIconClass = 'active';
                        else $roIconClass = 'pending';
                        $roIcon = match($roIconClass) { 'selesai'=>'fa-circle-check','batal'=>'fa-circle-xmark','active'=>'fa-spinner fa-spin-pulse', default=>'fa-clock' };
                    ?>
                    <div class="ro-item">
                        <div class="ro-ic <?= $roIconClass ?>"><i class="fa-solid <?= $roIcon ?>"></i></div>
                        <div class="ro-info">
                            <div class="ro-code"><?= htmlspecialchars($roKode) ?></div>
                            <div class="ro-meta"><?= date('d M Y', strtotime($ro[$colDate] ?? 'now')) ?> · <?= number_format($ro['berat'] ?? 0, 1) ?> kg</div>
                        </div>
                        <div class="ro-right">
                            <div class="ro-price"><?= formatRupiah($ro['total_harga'] ?? 0) ?></div>
                            <div class="ro-status"><?= ucfirst($roStatus) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- GRID 3: Chart + Donut + Favorite -->
        <div class="grid3 fi d6">
            <!-- SPENDING CHART -->
            <div class="card">
                <div class="card-hd"><h2><i class="fa-solid fa-chart-column"></i> Pengeluaran 6 Bulan</h2></div>
                <div class="card-bd">
                    <?php if(empty($monthlySpent) || max(array_column($monthlySpent,'value')) == 0): ?>
                    <div class="empty-state" style="padding:1.5rem"><i class="fa-solid fa-chart-simple"></i><p>Belum ada data pengeluaran</p></div>
                    <?php else: ?>
                    <div class="chart-bars">
                        <?php foreach($monthlySpent as $m):
                            $h = max(4, ($m['value'] / $maxMonthly) * 100);
                        ?>
                        <div class="chart-col">
                            <div class="chart-bar-wrap">
                                <div class="chart-bar" style="height:<?= $h ?>%;background:linear-gradient(180deg,var(--pk500),var(--pk300))">
                                    <span class="chart-val"><?= $m['value'] > 0 ? formatRupiah($m['value']) : '-' ?></span>
                                </div>
                            </div>
                            <span class="chart-lbl"><?= $m['month'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STATUS DONUT -->
            <div class="card">
                <div class="card-hd"><h2><i class="fa-solid fa-chart-pie"></i> Distribusi Status</h2></div>
                <div class="card-bd">
                    <?php if(empty($statusBreakdown)): ?>
                    <div class="empty-state" style="padding:1.5rem"><i class="fa-solid fa-chart-pie"></i><p>Belum ada data</p></div>
                    <?php else:
                        $donutParts = [];
                        $conicStr = '';
                        $offset = 0;
                        foreach($statusBreakdown as $st => $cnt) {
                            $pct = round(($cnt / $totalAllOrders) * 100);
                            $color = $statusColors[$st] ?? '#6b7280';
                            $label = $statusLabels[$st] ?? ucfirst($st);
                            $donutParts[] = ['label'=>$label,'count'=>$cnt,'pct'=>$pct,'color'=>$color];
                            $conicStr .= "$color $offset% ".($offset+$pct)."% ,";
                            $offset += $pct;
                        }
                        $conicStr = rtrim($conicStr, ' ,');
                    ?>
                    <div class="donut-wrap">
                        <div class="donut" style="background:conic-gradient(<?= $conicStr ?>)">
                            <div class="donut-center">
                                <div class="dc-val"><?= $totalAllOrders ?></div>
                                <div class="dc-lbl">TOTAL</div>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <?php foreach($donutParts as $dp): ?>
                            <div class="donut-legend-item">
                                <div class="donut-legend-dot" style="background:<?= $dp['color'] ?>"></div>
                                <span class="donut-legend-label"><?= $dp['label'] ?></span>
                                <span class="donut-legend-val"><?= $dp['count'] ?> (<?= $dp['pct'] ?>%)</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FAVORITE + TIP -->
            <div style="display:flex;flex-direction:column;gap:1rem">
                <?php if($favLayanan): ?>
                <div class="card" style="flex:1">
                    <div class="card-hd"><h2><i class="fa-solid fa-heart"></i> Layanan Favorit</h2></div>
                    <div class="card-bd">
                        <div class="fav-card">
                            <div class="fav-ic"><i class="fa-solid fa-trophy"></i></div>
                            <div class="fav-info">
                                <div class="fav-label">Paling Sering Dipakai</div>
                                <div class="fav-name"><?= htmlspecialchars($favLayanan['nama_layanan']) ?></div>
                                <div class="fav-meta"><?= formatRupiah($favLayanan['harga_per_kg']) ?>/kg · <?= $favLayanan['cnt'] ?>x dipesan</div>
                            </div>
                        </div>
                        <?php if($totalBerat > 0): ?>
                        <div style="margin-top:1rem;padding:.8rem 1rem;background:var(--g50);border-radius:10px;display:flex;align-items:center;gap:10px">
                            <i class="fa-solid fa-weight-hanging" style="color:var(--pk500)"></i>
                            <div>
                                <div style="font-size:.78rem;color:var(--g500)">Total Berat Dicuci</div>
                                <div style="font-size:1rem;font-weight:800;color:var(--g800)"><?= number_format($totalBerat, 1) ?> kg</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TIP HARIAN -->
                <div class="card" style="flex:1">
                    <div class="card-hd"><h2><i class="fa-solid fa-lightbulb"></i> Tips Hari Ini</h2></div>
                    <div class="card-bd">
                        <div class="tip-card">
                            <div class="tip-ic"><i class="fa-solid <?= $randomTip['icon'] ?>"></i></div>
                            <div class="tip-info">
                                <div class="tip-label">Tips Laundry</div>
                                <div class="tip-title"><?= $randomTip['title'] ?></div>
                                <div class="tip-desc"><?= $randomTip['desc'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIVITY FEED -->
        <?php if(!empty($activityLog)): ?>
        <div class="card fi d7" style="margin-bottom:2rem">
            <div class="card-hd">
                <h2><i class="fa-solid fa-timeline"></i> Aktivitas Terbaru</h2>
                <a href="orders.php" class="sec-link">Semua pesanan <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="card-bd">
                <?php foreach($activityLog as $act):
                    $actStatus = $act['status'] ?? '';
                    $actColorClass = in_array($actStatus, ['selesai','diantar']) ? 'green' : (in_array($actStatus, ['dibatalkan']) ? 'red' : (in_array($actStatus, ['pending']) ? 'yellow' : 'pink'));
                    $actIcon = $statusIcons[$actStatus] ?? 'fa-circle-info';
                    $timeAgo = getTimeAgo($act['updated_at'] ?? '');
                ?>
                <div class="act-item">
                    <div class="act-ic <?= $actColorClass ?>"><i class="fa-solid <?= $actIcon ?>"></i></div>
                    <div class="act-info">
                        <div class="act-text">
                            <strong><?= htmlspecialchars($act['kode'] ?? '-') ?></strong> —
                            <?= htmlspecialchars($act['keterangan'] ?? ucfirst($actStatus)) ?>
                        </div>
                        <div class="act-time"><i class="fa-regular fa-clock"></i> <?= $timeAgo ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- LAYANAN -->
        <?php if(!empty($layanan)): ?>
        <div class="fi d8">
            <div class="sec-hd">
                <div class="sec-title"><i class="fa-solid fa-fire"></i> Layanan Tersedia</div>
                <a href="layanan.php" class="sec-link">Lihat semua <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="lay-g">
                <?php foreach($layanan as $s):
                    $layIcon = 'fa-shirt';
                    if(stripos($s['jenis'] ?? '', 'sepatu') !== false) $layIcon = 'fa-shoe-prints';
                    elseif(stripos($s['jenis'] ?? '', 'bed') !== false || stripos($s['nama_layanan'] ?? '', 'bed') !== false) $layIcon = 'fa-bed';
                    elseif(stripos($s['jenis'] ?? '', 'setrika') !== false) $layIcon = 'fa-temperature-high';
                    elseif(stripos($s['jenis'] ?? '', 'karpet') !== false) $layIcon = 'fa-rug';
                    elseif(stripos($s['jenis'] ?? '', 'dry') !== false) $layIcon = 'fa-wind';
                ?>
                <a href="layanan.php" class="lay-c">
                    <div class="lay-ic"><i class="fa-solid <?= $layIcon ?>"></i></div>
                    <div class="lay-info">
                        <div class="lay-name"><?= htmlspecialchars($s['nama_layanan'] ?? 'Layanan') ?></div>
                        <div class="lay-type"><?= htmlspecialchars(ucfirst($s['jenis'] ?? 'Reguler')) ?></div>
                    </div>
                    <div class="lay-price"><?= formatRupiah($s['harga_per_kg'] ?? 0) ?> <span class="lay-unit">/kg</span></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
    </div>
</div>

<!-- MODAL TRACKING -->
<div class="modal-overlay" id="trackingModal">
    <div class="modal">
        <div class="modal-head">
            <h3><i class="fa-solid fa-route"></i> Tracking: <span id="trackKode"></span></h3>
            <button class="modal-close" onclick="closeTracking()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="trackBody"></div>
    </div>
</div>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
const obs=new IntersectionObserver(e=>{e.forEach(en=>{if(en.isIntersecting)en.target.classList.add('v')})},{threshold:.05});
document.querySelectorAll('.fi').forEach(el=>obs.observe(el));

function cancelOrder(id){
    if(!confirm('Yakin ingin membatalkan pesanan ini?')) return;
    const fd=new FormData();
    fd.append('action','cancel_order');
    fd.append('id_order',id);
    fetch('dashboard.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
        if(d.success){showToast('Pesanan berhasil dibatalkan','success');setTimeout(()=>location.reload(),1000);}
        else{showToast(d.message||'Gagal membatalkan','error');}
    });
}

function showTracking(id, kode){
    document.getElementById('trackKode').textContent = kode;
    const body = document.getElementById('trackBody');
    const data = <?= json_encode($trackingData) ?>;
    const tracks = data[id] || [];
    if(tracks.length === 0){
        body.innerHTML = '<div class="empty-state"><i class="fa-solid fa-route"></i><p>Belum ada update tracking.</p></div>';
    } else {
        let html = '<div class="timeline">';
        tracks.forEach(t => {
            const waktu = t.updated_at ? new Date(t.updated_at).toLocaleString('id-ID',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
            const st = t.status ? t.status.charAt(0).toUpperCase()+t.status.slice(1) : '-';
            html += '<div class="tl-item"><div class="tl-dot"></div><div class="tl-status">'+st+'</div><div class="tl-desc">'+(t.keterangan||'-')+'</div><div class="tl-time"><i class="fa-regular fa-clock"></i> '+waktu+'</div></div>';
        });
        html += '</div>';
        body.innerHTML = html;
    }
    document.getElementById('trackingModal').classList.add('show');
}

function closeTracking(){document.getElementById('trackingModal').classList.remove('show');}
document.getElementById('trackingModal').addEventListener('click',function(e){if(e.target===this) closeTracking();});

function showToast(msg, type){
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<i class="fa-solid '+(type==='success'?'fa-circle-check':'fa-circle-xmark')+'"></i> '+msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(), 3500);
}
</script>
</body>
</html>

<?php
// Helper function untuk time ago
function getTimeAgo($datetime) {
    if (!$datetime) return '-';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}
?>