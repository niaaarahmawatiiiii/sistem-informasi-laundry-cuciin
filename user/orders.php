<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

 $pageTitle = 'Pesanan Saya — CUCI.IN';
 $userId = (int)($_SESSION['user_id'] ?? 0);
 $userNama = $_SESSION['userNama'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User';
 $userEmail = $_SESSION['user_email'] ?? '';
 $flash = getFlash();
 $fromCheckout = isset($_GET['from']) && $_GET['from'] === 'checkout';

 $page = max(1, (int)($_GET['page'] ?? 1));
 $limit = 10;
 $offset = ($page - 1) * $limit;
 $filter = $_GET['status'] ?? 'all';

 $allowedStatus = ['all', 'pending', 'dikonfirmasi', 'diproses', 'dicuci', 'selesai', 'diantar', 'dibatalkan'];
if (!in_array($filter, $allowedStatus)) {
    $filter = 'all';
}

// Status flow untuk progress bar
// DIUBAH: 'diantar' dipindahkan sebelum 'selesai'
 $statusFlow = ['pending', 'dikonfirmasi', 'diproses', 'dicuci', 'diantar', 'selesai'];

 $statusLabel = [
    'pending' => 'Pending',
    'dikonfirmasi' => 'Dikonfirmasi',
    'diproses' => 'Diproses',
    'dicuci' => 'Dicuci',
    'selesai' => 'Selesai',
    'diantar' => 'Diantar',
    'dibatalkan' => 'Dibatalkan'
];
 $statusIcon = [
    'pending' => 'fa-clock',
    'dikonfirmasi' => 'fa-circle-check',
    'diproses' => 'fa-gears',
    'dicuci' => 'fa-droplet',
    'selesai' => 'fa-flag-checkered',
    'diantar' => 'fa-truck-fast',
    'dibatalkan' => 'fa-circle-xmark'
];
 $statusDesc = [
    'pending' => 'Menunggu konfirmasi dari admin',
    'dikonfirmasi' => 'Pesanan sudah dikonfirmasi',
    'diproses' => 'Sedang diproses oleh tim kami',
    'dicuci' => 'Cucian sedang dicuci',
    'selesai' => 'Cucian sudah selesai',
    'diantar' => 'Sedang dalam pengantaran',
    'dibatalkan' => 'Pesanan dibatalkan'
];

 $dbError = '';
 $total = 0;
 $orders = [];
 $pages = 0;

if (!isset($db) || !$db) {
    $dbError = 'Koneksi database gagal. Periksa config/database.php';
} else {
    $colCode = 'order_code';
    $colDate = 'created_at';
    try {
        $cols = $db->query("SHOW COLUMNS FROM orders")->fetch_all(MYSQLI_ASSOC);
        $colNames = array_column($cols, 'Field');
        if (in_array('kode_order', $colNames)) $colCode = 'kode_order';
        if (in_array('tanggal_order', $colNames)) $colDate = 'tanggal_order';
    } catch (Exception $e) {}

    $where = "WHERE o.id_user = $userId";
    if ($filter !== 'all') {
        $f = $db->real_escape_string($filter);
        $where .= " AND o.status = '$f'";
    }

    try {
        $result = $db->query("SELECT COUNT(*) as c FROM orders o $where");
        if ($result) {
            $row = $result->fetch_assoc();
            $total = (int)($row['c'] ?? 0);
        }
    } catch (Exception $e) {
        $dbError = 'Error query: ' . $e->getMessage();
    }

    $pages = ceil($total / $limit);

    if (empty($dbError)) {
        try {
            $res = $db->query("
                SELECT o.*, p.status_pembayaran, p.metode_pembayaran
                FROM orders o
                LEFT JOIN pembayaran p ON o.id_order = p.id_order
                $where
                ORDER BY o.$colDate DESC
                LIMIT $limit OFFSET $offset
            ");
            if ($res) {
                $orders = $res->fetch_all(MYSQLI_ASSOC);
            } else {
                $dbError = 'Query gagal: ' . $db->error;
            }
        } catch (Exception $e) {
            $dbError = 'Error: ' . $e->getMessage();
        }
    }
}

function getStatusColor($s) {
    $colors = [
        'pending' => '#f59e0b',
        'dikonfirmasi' => '#3b82f6',
        'diproses' => '#ec4899',
        'dicuci' => '#8b5cf6',
        'selesai' => '#22c55e',
        'diantar' => '#06b6d4',
        'dibatalkan' => '#ef4444'
    ];
    return $colors[$s] ?? '#6b7280';
}

function getStatusStep($s) {
    // DIUBAH: Sesuaikan urutan di sini juga agar perhitungan progress bar benar
    $flow = ['pending', 'dikonfirmasi', 'diproses', 'dicuci', 'diantar', 'selesai'];
    $idx = array_search($s, $flow);
    return $idx !== false ? $idx : -1;
}
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
        body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--g50);color:var(--g800);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        a{text-decoration:none;color:inherit;transition:var(--tr)}button{cursor:pointer;border:none;font-family:inherit}

        /* SIDEBAR */
        .sidebar{width:var(--sidebar-w);min-height:100vh;background:linear-gradient(180deg,#4a1225 0%,#6b1535 100%);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:var(--tr);border-right:1px solid rgba(236,72,153,.15)}
        .sidebar-brand{padding:1.5rem 1.2rem;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.08)}
        .sidebar-brand img{height:34px;width:34px;object-fit:contain;filter:brightness(10)}
        .sidebar-brand span{font-size:1.2rem;font-weight:900;background:linear-gradient(135deg,var(--pk400),var(--pk600));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .sidebar-nav{flex:1;padding:1rem .7rem;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
        .sidebar-label{font-size:.65rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:2px;padding:1rem .8rem .4rem}
        .sidebar-link{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:12px;font-size:.85rem;font-weight:500;color:rgba(255,255,255,.5);transition:var(--tr)}
        .sidebar-link:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.85)}
        .sidebar-link.active{background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;font-weight:600;box-shadow:0 4px 18px rgba(236,72,153,.3)}
        .sidebar-link i{width:20px;text-align:center;font-size:.9rem}
        .sidebar-link.danger{color:#fca5a5}.sidebar-link.danger:hover{background:rgba(239,68,68,.12);color:#f87171}
        .sidebar-user{padding:1.2rem;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px}
        .sidebar-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--pk400),var(--pk700));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.9rem;flex-shrink:0}
        .sidebar-uname{font-size:.85rem;font-weight:600;color:#fff}
        .sidebar-uemail{font-size:.7rem;color:rgba(255,255,255,.4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}
        .sidebar-toggle{display:none;position:fixed;top:1rem;left:1rem;z-index:200;width:44px;height:44px;border-radius:12px;background:#4a1225;border:none;color:#fff;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:90}

        .main-ct{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column}
        .topbar{height:60px;background:var(--w);border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:50}
        .topbar-left{display:flex;align-items:center;gap:12px}
        .topbar-title{font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .topbar-title i{color:var(--pk500)}
        .topbar-right{display:flex;align-items:center;gap:8px}
        .topbar-user{display:flex;align-items:center;gap:10px;padding-left:12px;border-left:1px solid var(--g200)}
        .topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--pk400),var(--pk600));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem}
        .topbar-uname{font-size:.85rem;font-weight:600;color:var(--g700)}
        .topbar-logout{color:var(--g400);font-size:.9rem;padding:6px 10px;border-radius:8px;transition:var(--tr)}
        .topbar-logout:hover{color:#ef4444;background:#fef2f2}

        .content{flex:1;padding:1.5rem 2rem 2rem}
        .content-in{max-width:1100px;margin:0 auto}

        /* ERROR */
        .error-box{padding:1.2rem 1.5rem;border-radius:18px;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:12px;font-size:.9rem;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;animation:fadeUp .4s ease}
        .error-box i{font-size:1.2rem;margin-top:2px;flex-shrink:0}
        .error-box .error-detail{font-size:.8rem;opacity:.8;margin-top:.3rem;font-family:monospace;word-break:break-all}

        /* SUCCESS BANNER */
        .success-banner{padding:1.5rem 1.8rem;border-radius:20px;margin-bottom:1.5rem;display:flex;align-items:center;gap:16px;background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);border:1px solid #6ee7b7;animation:fadeUp .5s ease;position:relative;overflow:hidden}
        .success-banner::before{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(34,197,94,.15)}
        .success-banner::after{content:'';position:absolute;bottom:-20px;right:60px;width:60px;height:60px;border-radius:50%;background:rgba(34,197,94,.1)}
        .success-banner .sb-icon{width:52px;height:52px;min-width:52px;border-radius:16px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;box-shadow:0 4px 15px rgba(34,197,94,.35);z-index:1}
        .success-banner .sb-text{z-index:1}
        .success-banner .sb-text h3{font-size:1.05rem;font-weight:800;color:#065f46;margin-bottom:3px}
        .success-banner .sb-text p{font-size:.85rem;color:#047857;line-height:1.5}
        .success-banner .sb-close{position:absolute;top:12px;right:12px;width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.6);color:#065f46;display:flex;align-items:center;justify-content:center;font-size:.8rem;cursor:pointer;z-index:2;border:none;transition:var(--tr);backdrop-filter:blur(4px)}
        .success-banner .sb-close:hover{background:rgba(255,255,255,.9)}

        /* FLASH */
        .flash-box{padding:1rem 1.2rem;border-radius:18px;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;animation:fadeUp .5s ease}
        .flash-ok{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .flash-warn{background:#fef3c7;color:#d97706;border:1px solid #fde68a}
        @keyframes fadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}

        /* HERO HEADER */
        .hero-header{background:linear-gradient(135deg,#4a1225 0%,#831843 50%,#be185d 100%);border-radius:24px;padding:2rem 2.2rem;margin-bottom:1.8rem;color:#fff;position:relative;overflow:hidden;display:flex;align-items:center;gap:1.5rem}
        .hero-header::before{content:'';position:absolute;top:-50px;right:-30px;width:200px;height:200px;border-radius:50%;background:rgba(244,114,182,.12)}
        .hero-header::after{content:'';position:absolute;bottom:-40px;left:20%;width:140px;height:140px;border-radius:50%;background:rgba(236,72,153,.08)}
        .hero-icon-box{width:72px;height:72px;min-width:72px;border-radius:20px;background:rgba(255,255,255,.1);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;z-index:1;animation:pulse-soft 2.5s ease-in-out infinite}
        .hero-icon-box i{font-size:1.8rem;color:var(--pk300)}
        @keyframes pulse-soft{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
        .hero-text{flex:1;z-index:1}
        .hero-text h1{font-size:1.4rem;font-weight:900;margin-bottom:4px}
        .hero-text h1 span{color:var(--pk300)}
        .hero-text p{font-size:.85rem;color:rgba(255,255,255,.55);line-height:1.5}
        .hero-stats{display:flex;gap:1.2rem;z-index:1}
        .hero-stat{text-align:center;padding:.6rem 1rem;background:rgba(255,255,255,.08);border-radius:14px;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.06);min-width:80px}
        .hero-stat-val{font-size:1.3rem;font-weight:900}
        .hero-stat-lbl{font-size:.62rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1px;margin-top:2px}

        /* FILTER TABS */
        .filter-section{display:flex;gap:6px;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center}
        .filter-label{font-size:.78rem;font-weight:700;color:var(--g400);text-transform:uppercase;letter-spacing:1px;margin-right:6px}
        .ftab{padding:8px 16px;border-radius:20px;font-size:.8rem;font-weight:600;color:var(--g500);background:var(--w);border:1.5px solid var(--g200);cursor:pointer;transition:var(--tr);display:flex;align-items:center;gap:6px;white-space:nowrap}
        .ftab i{font-size:.7rem}
        .ftab:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}
        .ftab.active{background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;border-color:transparent;box-shadow:0 4px 15px rgba(236,72,153,.25)}
        .ftab .ft-count{padding:1px 7px;border-radius:8px;font-size:.68rem;font-weight:700}
        .ftab.active .ft-count{background:rgba(255,255,255,.22);color:#fff}
        .ftab:not(.active) .ft-count{background:var(--g100);color:var(--g400)}

        /* INFO BAR */
        .info-bar{font-size:.85rem;color:var(--g500);margin-bottom:1.3rem;display:flex;align-items:center;gap:8px}
        .info-bar i{color:var(--pk400);font-size:.8rem}
        .info-bar strong{color:var(--g700)}

        /* ORDER CARD */
        .order-card{background:var(--w);border:1.5px solid var(--g100);border-radius:20px;transition:var(--tr);position:relative;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.02)}
        .order-card:hover{transform:translateY(-4px);box-shadow:var(--shl);border-color:rgba(236,72,153,.18)}
        .order-accent{height:4px;width:100%}
        .order-body{padding:1.4rem 1.5rem}

        .oc-top{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.8rem}
        .oc-left{flex:1;min-width:200px}
        .oc-code-row{display:flex;align-items:center;gap:8px;margin-bottom:.4rem}
        .oc-code{font-size:.95rem;font-weight:800;color:var(--g800)}
        .oc-copy{width:26px;height:26px;border-radius:6px;background:var(--g50);color:var(--g400);display:flex;align-items:center;justify-content:center;font-size:.65rem;cursor:pointer;transition:var(--tr);border:none}
        .oc-copy:hover{background:var(--pk50);color:var(--pk600)}
        .oc-meta{font-size:.8rem;color:var(--g500);display:flex;flex-wrap:wrap;gap:.8rem;align-items:center}
        .oc-meta-item{display:flex;align-items:center;gap:5px}
        .oc-meta-item i{font-size:.7rem;color:var(--g400);width:14px;text-align:center}
        .oc-right{display:flex;flex-direction:column;align-items:flex-end;gap:.5rem}
        .oc-price{font-size:1.25rem;font-weight:900;color:var(--g900)}

        /* STATUS BADGE */
        .status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:12px;font-size:.73rem;font-weight:700;letter-spacing:.2px}
        .status-badge i{font-size:.65rem}
        .sb-pending{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#b45309}
        .sb-dikonfirmasi{background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#1d4ed8}
        .sb-diproses{background:linear-gradient(135deg,#fce7f3,#fbcfe8);color:#be185d}
        .sb-dicuci{background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#6d28d9}
        .sb-selesai{background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#047857}
        .sb-diantar{background:linear-gradient(135deg,#cffafe,#a5f3fc);color:#0e7490}
        .sb-dibatalkan{background:linear-gradient(135deg,#fee2e2,#fecaca);color:#b91c1c}

        /* PAYMENT BADGE */
        .pay-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:10px;font-size:.7rem;font-weight:700}
        .pay-badge i{font-size:.6rem}
        .pb-pending{background:#fef3c7;color:#b45309}
        .pb-dibayar{background:#d1fae5;color:#047857}
        .pb-gagal{background:#fee2e2;color:#b91c1c}

        /* PROGRESS BAR */
        .progress-section{margin:1rem 0 .2rem}
        .progress-track{display:flex;align-items:center;gap:0;position:relative;padding:0 4px}
        .progress-step{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;z-index:1}
        .progress-dot{width:28px;height:28px;border-radius:50%;background:var(--g100);display:flex;align-items:center;justify-content:center;font-size:.6rem;color:var(--g400);transition:var(--tr);border:2.5px solid var(--g200);position:relative;z-index:2}
        .progress-dot.done{border-color:var(--pk500);background:var(--pk500);color:#fff;box-shadow:0 2px 10px rgba(236,72,153,.3)}
        .progress-dot.current{border-color:var(--pk400);background:var(--w);color:var(--pk600);animation:dot-pulse 1.5s ease-in-out infinite;box-shadow:0 0 0 4px rgba(236,72,153,.15)}
        @keyframes dot-pulse{0%,100%{box-shadow:0 0 0 4px rgba(236,72,153,.15)}50%{box-shadow:0 0 0 8px rgba(236,72,153,.05)}}
        .progress-label{font-size:.58rem;font-weight:600;color:var(--g400);margin-top:5px;text-align:center;white-space:nowrap;max-width:60px;overflow:hidden;text-overflow:ellipsis}
        .progress-step.done .progress-label{color:var(--pk600)}
        .progress-step.current .progress-label{color:var(--pk700);font-weight:700}
        .progress-line{position:absolute;top:14px;left:calc(12.5% + 7px);right:calc(12.5% + 7px);height:3px;background:var(--g200);z-index:0;border-radius:2px}
        .progress-line-fill{height:100%;background:linear-gradient(90deg,var(--pk500),var(--pk400));border-radius:2px;transition:width .6s cubic-bezier(.4,0,.2,1)}
        .progress-cancelled{padding:.8rem 1rem;background:linear-gradient(135deg,#fef2f2,#fee2e2);border-radius:12px;border:1px solid #fecaca;display:flex;align-items:center;gap:10px}
        .progress-cancelled i{font-size:1.1rem;color:#ef4444}
        .progress-cancelled span{font-size:.82rem;font-weight:600;color:#991b1b}

        /* DIVIDER */
        .oc-divider{height:1px;background:var(--g100);margin:1rem 0}

        /* BOTTOM ROW */
        .oc-bottom{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
        .pay-info{display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--g500);flex-wrap:wrap}
        .pay-info i{color:var(--g400);font-size:.8rem}

        /* BUTTONS */
        .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:12px;font-size:.82rem;font-weight:700;cursor:pointer;border:none;transition:var(--tr)}
        .btn-primary{background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;box-shadow:0 4px 12px rgba(236,72,153,.2)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.3)}
        .btn-ghost{background:var(--w);color:var(--g600);border:1.5px solid var(--g200)}
        .btn-ghost:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}
        .btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,.2)}
        .btn-success:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,.3)}
        .btn-sm{padding:7px 14px;font-size:.78rem;border-radius:10px}
        .btn i{font-size:.8rem}

        /* EMPTY STATE */
        .empty-state{text-align:center;padding:4rem 2rem;background:var(--w);border:1.5px solid var(--g100);border-radius:24px;box-shadow:0 2px 10px rgba(0,0,0,.03)}
        .empty-icon-box{width:100px;height:100px;border-radius:28px;background:linear-gradient(135deg,var(--pk50),var(--pk100));display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;animation:float 3s ease-in-out infinite}
        .empty-icon-box i{font-size:2.5rem;color:var(--pk400)}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        .empty-state h2{font-size:1.3rem;font-weight:800;color:var(--g800);margin-bottom:.4rem}
        .empty-state p{color:var(--g500);font-size:.9rem;margin-bottom:1.5rem;max-width:400px;margin-left:auto;margin-right:auto;line-height:1.6}

        /* PAGINATION */
        .pagination{display:flex;gap:6px;justify-content:center;margin-top:2rem;align-items:center}
        .page-link{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:12px;font-size:.88rem;font-weight:600;color:var(--g500);background:var(--w);border:1.5px solid var(--g200);transition:var(--tr)}
        .page-link:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}
        .page-link.active{background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;border-color:transparent;box-shadow:0 4px 15px rgba(236,72,153,.25)}
        .page-dots{color:var(--g400);padding:0 4px;font-weight:600}

        /* ANIM */
        .fi{opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease}
        .fi.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}.d5{transition-delay:.25s}.d6{transition-delay:.3s}.d7{transition-delay:.35s}.d8{transition-delay:.4s}

        /* TOAST */
        .toast-ct{position:fixed;top:80px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px}
        .toast{padding:12px 20px;border-radius:14px;font-size:.85rem;font-weight:600;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,.15);animation:tIn .4s ease,tOut .4s ease 2.6s forwards;display:flex;align-items:center;gap:10px}
        .toast.ok{background:linear-gradient(135deg,#10b981,#059669)}
        .toast.err{background:linear-gradient(135deg,#ef4444,#dc2626)}
        @keyframes tIn{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}
        @keyframes tOut{to{opacity:0;transform:translateX(100px)}}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}.sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}.topbar{padding:0 1rem}.content{padding:1rem}
            .hero-header{flex-direction:column;padding:1.5rem;text-align:center}
            .hero-stats{justify-content:center}
            .oc-top{flex-direction:column}.oc-right{flex-direction:row;align-items:center;width:100%;justify-content:space-between}
            .filter-section{gap:4px}.ftab{padding:6px 11px;font-size:.72rem}
            .progress-label{font-size:.5rem;max-width:40px}
            .topbar-user .topbar-uname{display:none}
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
        <a href="dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="layanan.php" class="sidebar-link"><i class="fa-solid fa-shirt"></i> Layanan</a>
        <a href="cart.php" class="sidebar-link"><i class="fa-solid fa-cart-shopping"></i> Keranjang</a>
        <a href="orders.php" class="sidebar-link active"><i class="fa-solid fa-list-check"></i> Pesanan Saya</a>
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
        <div class="topbar-left">
            <div class="topbar-title"><i class="fa-solid fa-list-check"></i> Pesanan Saya</div>
        </div>
        <div class="topbar-right">
            <a href="dashboard.php" style="color:var(--g500);font-size:.82rem;font-weight:600;padding:7px 14px;border-radius:10px;transition:var(--tr);display:flex;align-items:center;gap:6px"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <div class="topbar-user">
                <div class="topbar-avatar"><?= strtoupper(substr($userNama, 0, 1)) ?></div>
                <span class="topbar-uname"><?= htmlspecialchars($userNama) ?></span>
                <a href="logout.php" class="topbar-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </div>

    <div class="content">
    <div class="content-in">

        <?php if (!empty($dbError)): ?>
        <div class="error-box">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Terjadi Kesalahan Database</strong>
                <div class="error-detail"><?= htmlspecialchars($dbError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($fromCheckout): ?>
        <div class="success-banner fi" id="successBanner">
            <div class="sb-icon"><i class="fa-solid fa-check"></i></div>
            <div class="sb-text">
                <h3>Pesanan Berhasil Dibuat!</h3>
                <p>Pesanan kamu sudah masuk. Silakan lakukan pembayaran agar cucian segera diproses.</p>
            </div>
            <button class="sb-close" onclick="document.getElementById('successBanner').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <?php endif; ?>

        <?php if ($flash && !$fromCheckout): ?>
        <div class="flash-box flash-ok fi">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars(is_array($flash) ? ($flash['message'] ?? $flash['text'] ?? json_encode($flash)) : $flash) ?></span>
        </div>
        <?php endif; ?>

        <!-- HERO -->
        <div class="hero-header fi d1">
            <div class="hero-icon-box">
                <i class="fa-solid fa-box-archive"></i>
            </div>
            <div class="hero-text">
                <h1>Pesanan <span>Saya</span></h1>
                <p>Pantau status pesanan laundry kamu secara real-time dari pending hingga selesai.</p>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $total ?></div>
                    <div class="hero-stat-lbl">Total</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val" style="color:var(--pk300)"><?= count(array_filter($orders, function($o) use ($statusFlow) { return in_array($o['status'] ?? '', $statusFlow) && getStatusStep($o['status']) >= 0 && getStatusStep($o['status']) < count($statusFlow) - 1; })) ?></div>
                    <div class="hero-stat-lbl">Aktif</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val" style="color:#86efac"><?= count(array_filter($orders, function($o) { return in_array($o['status'] ?? '', ['selesai', 'diantar']); })) ?></div>
                    <div class="hero-stat-lbl">Selesai</div>
                </div>
            </div>
        </div>

        <!-- FILTER -->
        <div class="filter-section fi d2">
            <span class="filter-label"><i class="fa-solid fa-filter" style="margin-right:4px"></i> Filter</span>
            <?php
            $tabs = [
                'all' => ['label' => 'Semua', 'icon' => 'fa-border-all'],
                'pending' => ['label' => 'Pending', 'icon' => 'fa-clock'],
                'dikonfirmasi' => ['label' => 'Dikonfirmasi', 'icon' => 'fa-circle-check'],
                'diproses' => ['label' => 'Diproses', 'icon' => 'fa-gears'],
                'dicuci' => ['label' => 'Dicuci', 'icon' => 'fa-droplet'],
                'diantar' => ['label' => 'Diantar', 'icon' => 'fa-truck-fast'],
                'selesai' => ['label' => 'Selesai', 'icon' => 'fa-flag-checkered'],
                'dibatalkan' => ['label' => 'Dibatalkan', 'icon' => 'fa-circle-xmark']
            ];

            // Hitung per status
            $statusCounts = [];
            foreach ($orders as $o) {
                $s = $o['status'] ?? 'pending';
                $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
            }

            foreach($tabs as $k => $t):
                $count = $k === 'all' ? $total : ($statusCounts[$k] ?? 0);
            ?>
            <a href="?status=<?= $k ?>&page=1" class="ftab <?= $filter === $k ? 'active' : '' ?>">
                <i class="fa-solid <?= $t['icon'] ?>"></i>
                <?= htmlspecialchars($t['label']) ?>
                <span class="ft-count"><?= $count ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- INFO BAR -->
        <div class="info-bar fi d3">
            <i class="fa-solid fa-circle-info"></i>
            <?php if ($filter !== 'all'): ?>
                Menampilkan <strong><?= count($orders) ?></strong> pesanan dengan status <strong><?= htmlspecialchars($statusLabel[$filter] ?? $filter) ?></strong>
            <?php else: ?>
                Menampilkan <strong><?= count($orders) ?></strong> dari <strong><?= $total ?></strong> pesanan
            <?php endif; ?>
        </div>

        <!-- ORDERS -->
        <?php if(empty($orders) && empty($dbError)): ?>
        <div class="empty-state fi d4">
            <div class="empty-icon-box">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h2>Tidak Ada Pesanan</h2>
            <p>Belum ada pesanan dengan status ini. Yuk mulai laundry sekarang!</p>
            <a href="layanan.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Pesan Sekarang</a>
        </div>
        <?php elseif(!empty($orders)): ?>
        <div style="display:flex;flex-direction:column;gap:1.2rem;">
            <?php foreach($orders as $i => $order):
                $status = $order['status'] ?? 'pending';
                $statusColor = getStatusColor($status);
                $payStatus = $order['status_pembayaran'] ?? 'pending';
                $payClass = $payStatus === 'dibayar' ? 'dibayar' : ($payStatus === 'gagal' ? 'gagal' : 'pending');
                $payLabel = ucfirst($payStatus);
                $payIcon = $payStatus === 'dibayar' ? 'fa-circle-check' : ($payStatus === 'gagal' ? 'fa-circle-xmark' : 'fa-hourglass-half');
                $stepIdx = getStatusStep($status);
                $isCancelled = $status === 'dibatalkan';
                $progressPercent = $isCancelled ? 0 : max(0, ($stepIdx / (count($statusFlow) - 1)) * 100);
            ?>
            <div class="order-card fi d<?= min(($i % 8) + 1, 8) ?>">
                <!-- Accent top bar -->
                <div class="order-accent" style="background:linear-gradient(90deg,<?= $statusColor ?>,<?= $statusColor ?>88,transparent)"></div>

                <div class="order-body">
                    <div class="oc-top">
                        <div class="oc-left">
                            <div class="oc-code-row">
                                <span class="oc-code"><?= htmlspecialchars($order[$colCode] ?? '-') ?></span>
                                <button class="oc-copy" onclick="copyCode('<?= htmlspecialchars(addslashes($order[$colCode] ?? '')) ?>')" title="Salin kode"><i class="fa-regular fa-copy"></i></button>
                            </div>
                            <div class="oc-meta">
                                <div class="oc-meta-item"><i class="fa-regular fa-calendar"></i> <?= date('d M Y, H:i', strtotime($order[$colDate] ?? 'now')) ?></div>
                                <div class="oc-meta-item"><i class="fa-solid fa-<?= ($order['metode'] ?? '') === 'antar-jemput' ? 'truck-fast' : 'store' ?>"></i> <?= ($order['metode'] ?? '') === 'antar-jemput' ? 'Antar-Jemput' : 'Datang Sendiri' ?></div>
                                <?php if(!empty($order['berat'])): ?>
                                <div class="oc-meta-item"><i class="fa-solid fa-weight-hanging"></i> <?= number_format($order['berat'], 1) ?> kg</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="oc-right">
                            <div class="oc-price"><?= formatRupiah($order['total_harga'] ?? 0) ?></div>
                            <span class="status-badge sb-<?= $status ?>">
                                <i class="fa-solid <?= $statusIcon[$status] ?? 'fa-circle' ?>"></i>
                                <?= $statusLabel[$status] ?? ucfirst($status) ?>
                            </span>
                        </div>
                    </div>

                    <!-- PROGRESS BAR -->
                    <?php if ($isCancelled): ?>
                    <div class="progress-section">
                        <div class="progress-cancelled">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>Pesanan ini dibatalkan</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="progress-section">
                        <div class="progress-track">
                            <div class="progress-line">
                                <div class="progress-line-fill" style="width:<?= $progressPercent ?>%"></div>
                            </div>
                            <?php foreach($statusFlow as $si => $sLabel):
                                $isDone = $si < $stepIdx;
                                $isCurrent = $si === $stepIdx;
                                $cls = $isDone ? 'done' : ($isCurrent ? 'current' : '');
                                $dotIcon = $isDone ? 'fa-check' : ($isCurrent ? $statusIcon[$sLabel] : '');
                            ?>
                            <div class="progress-step <?= $cls ?>">
                                <div class="progress-dot <?= $cls ?>">
                                    <?php if ($dotIcon): ?>
                                    <i class="fa-solid <?= $dotIcon ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="progress-label"><?= $sLabel ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="oc-divider"></div>

                    <div class="oc-bottom">
                        <div class="pay-info">
                            <i class="fa-solid fa-wallet"></i>
                            <span>Pembayaran:</span>
                            <span class="pay-badge pb-<?= $payClass ?>">
                                <i class="fa-solid <?= $payIcon ?>"></i>
                                <?= $payLabel ?>
                            </span>
                            <?php if(!empty($order['metode_pembayaran'])): ?>
                            <span style="font-size:.75rem;color:var(--g400)">(<?= ucfirst($order['metode_pembayaran']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:.5rem;">
                            <?php if($payStatus === 'pending' && !$isCancelled): ?>
                            <a href="payment.php?order=<?= $order['id_order'] ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-credit-card"></i> Bayar</a>
                            <?php endif; ?>
                            <a href="order-detail.php?id=<?= $order['id_order'] ?>" class="btn btn-ghost btn-sm">Detail <i class="fa-solid fa-arrow-right" style="font-size:.7rem"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if($pages > 1): ?>
        <div class="pagination fi">
            <?php if($page > 1): ?>
            <a href="?status=<?= $filter ?>&page=<?= $page - 1 ?>" class="page-link"><i class="fa-solid fa-chevron-left" style="font-size:.7rem"></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end = min($pages, $page + 2);
            if ($start > 1) {
                echo '<a href="?status='.$filter.'&page=1" class="page-link">1</a>';
                if ($start > 2) echo '<span class="page-dots">...</span>';
            }
            for($p = $start; $p <= $end; $p++): ?>
            <a href="?status=<?= $filter ?>&page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor;
            if ($end < $pages) {
                if ($end < $pages - 1) echo '<span class="page-dots">...</span>';
                echo '<a href="?status='.$filter.'&page='.$pages.'" class="page-link">'.$pages.'</a>';
            }
            ?>
            <?php if($page < $pages): ?>
            <a href="?status=<?= $filter ?>&page=<?= $page + 1 ?>" class="page-link"><i class="fa-solid fa-chevron-right" style="font-size:.7rem"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
    </div>
</div>

<div class="toast-ct" id="toastCt"></div>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
var obs=new IntersectionObserver(function(e){e.forEach(function(en){if(en.isIntersecting)en.target.classList.add('v')})},{threshold:.05});
document.querySelectorAll('.fi').forEach(function(el){obs.observe(el)});

function copyCode(code){
    if(navigator.clipboard){
        navigator.clipboard.writeText(code).then(function(){
            showToast('Kode pesanan disalin!','ok');
        });
    } else {
        var ta=document.createElement('textarea');
        ta.value=code;document.body.appendChild(ta);ta.select();document.execCommand('copy');
        document.body.removeChild(ta);showToast('Kode pesanan disalin!','ok');
    }
}

function showToast(msg,type){
    type=type||'ok';
    var ct=document.getElementById('toastCt'),t=document.createElement('div');
    t.className='toast '+type;
    t.innerHTML='<i class="fa-solid '+(type==='ok'?'fa-circle-check':'fa-circle-exclamation')+'"></i> '+msg;
    ct.appendChild(t);setTimeout(function(){t.remove()},3200);
}
</script>
</body>
</html>