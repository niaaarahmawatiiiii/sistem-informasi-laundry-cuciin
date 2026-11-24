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
 $search = trim($_GET['q'] ?? '');
 $filter = $_GET['jenis'] ?? '';
 $cartCount = getCartCount($userId);

 $layananCols = [];
if (isset($db) && $db) {
    try {
        $colResult = $db->query("SHOW COLUMNS FROM layanan")->fetch_all(MYSQLI_ASSOC);
        $layananCols = array_column($colResult, 'Field');
    } catch (Exception $e) {}
}
 $colHarga = in_array('harga_per_kg', $layananCols) ? 'harga_per_kg' : (in_array('harga', $layananCols) ? 'harga' : 'harga');
 $colSatuan = in_array('satuan', $layananCols) ? 'satuan' : 'kg';
 $colJenis = in_array('jenis', $layananCols) ? 'jenis' : '';
 $colDesc = in_array('deskripsi', $layananCols) ? 'deskripsi' : '';
 $colIcon = in_array('icon', $layananCols) ? 'icon' : '';
 $colActive = in_array('is_active', $layananCols) ? 'is_active' : '1=1';
 $colEstimasi = in_array('estimasi_waktu', $layananCols) ? 'estimasi_waktu' : (in_array('estimasi', $layananCols) ? 'estimasi' : '');

 $where = "WHERE $colActive";
 $params = [];
 $bindTypes = '';

if ($search) {
    $where .= " AND (nama_layanan LIKE ? OR $colDesc LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $bindTypes .= "ss";
}
if ($filter && $colJenis) {
    $where .= " AND jenis = ?";
    $params[] = $filter;
    $bindTypes .= 's';
} else {
    $where .= " AND jenis != 'hidden'";
}

 $layanan = [];
if (isset($db) && $db) {
    try {
        $query = "SELECT * FROM layanan $where ORDER BY jenis, id_layanan";
        if (!empty($params)) {
            $stmt = $db->prepare($query);
            $stmt->bind_param($bindTypes, ...$params);
            $stmt->execute();
            $layanan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $layanan = $db->query($query)->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {}
}

 $jenisList = [];
 $jenisCount = [];
if (isset($db) && $db) {
    try {
        $jResult = $db->query("SELECT jenis, COUNT(*) as cnt FROM layanan WHERE $colActive AND jenis != 'hidden' GROUP BY jenis ORDER BY jenis");
        while ($jRow = $jResult->fetch_assoc()) {
            if (!empty($jRow['jenis'])) {
                $jenisList[] = $jRow['jenis'];
                $jenisCount[$jRow['jenis']] = (int)$jRow['cnt'];
            }
        }
    } catch (Exception $e) {}
}
 $totalLayanan = array_sum($jenisCount);
if (empty($jenisList)) $jenisList = ['Layanan'];

 $pageTitle = 'Layanan — CUCI.IN';

function getServiceStyle($nama, $jenis) {
    $n = strtolower($nama . ' ' . $jenis);
    $styles = [
        ['keys'=>['cuci kering','cuci setrika','cuci lipat'],'icon'=>'fa-shirt','bg1'=>'#dbeafe','bg2'=>'#93c5fd','clr'=>'#2563eb','emoji'=>'fa-droplet'],
        ['keys'=>['setrika'],'icon'=>'fa-temperature-arrow-up','bg1'=>'#fef3c7','bg2'=>'#fde68a','clr'=>'#d97706','emoji'=>'fa-fire-flame-curved'],
        ['keys'=>['dry clean','dry cleaning','dry'],'icon'=>'fa-wind','bg1'=>'#e0e7ff','bg2'=>'#c7d2fe','clr'=>'#4f46e5','emoji'=>'fa-snowflake'],
        ['keys'=>['express','kilat','fast'],'icon'=>'fa-bolt-lightning','bg1'=>'#fee2e2','bg2'=>'#fecaca','clr'=>'#dc2626','emoji'=>'fa-bolt'],
        ['keys'=>['sepatu','sneakers'],'icon'=>'fa-shoe-prints','bg1'=>'#ede9fe','bg2'=>'#ddd6fe','clr'=>'#7c3aed','emoji'=>'fa-star'],
        ['keys'=>['karpet','carpet','rug'],'icon'=>'fa-rug','bg1'=>'#fce7f3','bg2'=>'#fbcfe8','clr'=>'#db2777','emoji'=>'fa-broom'],
        ['keys'=>['bed cover','bedcover','selimut','sprei','bed'],'icon'=>'fa-bed','bg1'=>'#d1fae5','bg2'=>'#a7f3d0','clr'=>'#059669','emoji'=>'fa-moon'],
        ['keys'=>['gorden','curtain','vitrase'],'icon'=>'fa-blinds','bg1'=>'#f3e8ff','bg2'=>'#e9d5ff','clr'=>'#9333ea','emoji'=>'fa-window-maximize'],
        ['keys'=>['tas','bag','handbag'],'icon'=>'fa-bag-shopping','bg1'=>'#fef9c3','bg2'=>'#fef08a','clr'=>'#ca8a04','emoji'=>'fa-gem'],
        ['keys'=>['boneka','stuffed','plush'],'icon'=>'fa-paw','bg1'=>'#fce7f3','bg2'=>'#f9a8d4','clr'=>'#ec4899','emoji'=>'fa-heart'],
        ['keys'=>['self service','self-service','mandiri'],'icon'=>'fa-hands-bubbles','bg1'=>'#dcfce7','bg2'=>'#bbf7d0','clr'=>'#16a34a','emoji'=>'fa-droplet'],
        ['keys'=>['premium','vip','luxury'],'icon'=>'fa-crown','bg1'=>'#fef3c7','bg2'=>'#fcd34d','clr'=>'#b45309','emoji'=>'fa-star'],
        ['keys'=>['antar jemput','pickup','delivery'],'icon'=>'fa-truck-fast','bg1'=>'#cffafe','bg2'=>'#a5f3fc','clr'=>'#0891b2','emoji'=>'fa-route'],
        ['keys'=>['potong','tailor','jahit'],'icon'=>'fa-scissors','bg1'=>'#ffe4e6','bg2'=>'#fecdd3','clr'=>'#e11d48','emoji'=>'fa-ruler'],
    ];
    foreach ($styles as $s) {
        foreach ($s['keys'] as $k) {
            if (stripos($n, $k) !== false) return $s;
        }
    }
    return ['icon'=>'fa-tag','bg1'=>'#fce7f3','bg2'=>'#fbcfe8','clr'=>'#ec4899','emoji'=>'fa-percent'];
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

        .main-ct{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column}
        .topbar{height:60px;background:var(--w);border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:50}
        .topbar-title{font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .topbar-title i{color:var(--pk500)}
        .topbar-right{display:flex;align-items:center;gap:8px}
        .topbar-btn{color:var(--g500);font-size:.82rem;font-weight:600;padding:7px 14px;border-radius:10px;transition:var(--tr);display:flex;align-items:center;gap:6px}
        .topbar-btn:hover{background:var(--g50);color:var(--g700)}
        .topbar-btn.logout:hover{background:#fef2f2;color:#ef4444}

        .content{flex:1;padding:1.5rem 2rem 2rem}
        .content-in{max-width:1200px;margin:0 auto}

        .hero-layanan{background:linear-gradient(135deg,#4a1225 0%,#831843 40%,#be185d 100%);border-radius:24px;padding:2.5rem;margin-bottom:2rem;color:#fff;position:relative;overflow:hidden;display:flex;align-items:center;gap:2rem}
        .hero-layanan::before{content:'';position:absolute;top:-60px;right:-40px;width:250px;height:250px;border-radius:50%;background:rgba(244,114,182,.12)}
        .hero-layanan::after{content:'';position:absolute;bottom:-80px;left:30%;width:180px;height:180px;border-radius:50%;background:rgba(236,72,153,.08)}
        .hero-icon-box{width:80px;height:80px;min-width:80px;border-radius:22px;background:rgba(255,255,255,.1);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;z-index:1;animation:float 3s ease-in-out infinite;border:1px solid rgba(255,255,255,.15)}
        .hero-icon-box i{font-size:2rem;color:var(--pk300)}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
        .hero-text{flex:1;z-index:1}
        .hero-text h1{font-size:1.6rem;font-weight:900;margin-bottom:6px}
        .hero-text h1 span{color:var(--pk300)}
        .hero-text p{font-size:.88rem;color:rgba(255,255,255,.6);line-height:1.5}
        .hero-stats{display:flex;gap:1.5rem;z-index:1}
        .hero-stat{text-align:center}
        .hero-stat-val{font-size:1.4rem;font-weight:900}
        .hero-stat-lbl{font-size:.68rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;margin-top:2px}

        .flash-box{padding:1rem 1.2rem;border-radius:16px;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;animation:fadeUp .5s ease}
        .flash-warn{background:#fef3c7;color:#d97706;border:1px solid #fde68a}
        @keyframes fadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}

        .search-section{display:flex;gap:1rem;margin-bottom:1.5rem;align-items:stretch}
        .search-wrap{flex:1;position:relative}
        .search-wrap i.search-ic{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--g400);font-size:.95rem;pointer-events:none;z-index:2}
        .search-input{width:100%;padding:14px 48px 14px 48px;border:2px solid var(--g200);border-radius:16px;font-size:.92rem;font-family:inherit;color:var(--g800);background:var(--w);outline:none;transition:var(--tr)}
        .search-input::placeholder{color:var(--g300)}
        .search-input:focus{border-color:var(--pk400);box-shadow:0 0 0 4px rgba(236,72,153,.08)}
        .search-clear{position:absolute;right:14px;top:50%;transform:translateY(-50%);width:30px;height:30px;border:none;background:var(--g100);color:var(--g500);cursor:pointer;font-size:.8rem;display:none;align-items:center;justify-content:center;border-radius:8px;transition:var(--tr)}
        .search-clear:hover{background:var(--pk100);color:var(--pk600)}
        .cart-mini-btn{display:flex;align-items:center;gap:8px;padding:14px 22px;border-radius:16px;background:var(--w);border:2px solid var(--g200);font-size:.88rem;font-weight:700;color:var(--g600);transition:var(--tr);white-space:nowrap;cursor:pointer}
        .cart-mini-btn:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}
        .cart-mini-btn .cart-ct-badge{background:var(--pk500);color:#fff;padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:700}

        .filter-bar{display:flex;gap:8px;margin-bottom:1.8rem;flex-wrap:wrap;align-items:center}
        .ftab{padding:9px 18px;border-radius:12px;font-size:.82rem;font-weight:600;color:var(--g500);background:var(--w);border:1.5px solid var(--g200);cursor:pointer;transition:var(--tr);display:flex;align-items:center;gap:7px;white-space:nowrap}
        .ftab:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}
        .ftab.active{background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;border-color:transparent;box-shadow:0 4px 15px rgba(236,72,153,.25)}
        .ft-count{padding:2px 7px;border-radius:8px;font-size:.7rem;font-weight:700}
        .ftab.active .ft-count{background:rgba(255,255,255,.25);color:#fff}
        .ftab:not(.active) .ft-count{background:var(--g100);color:var(--g500)}

        .info-bar{font-size:.85rem;color:var(--g500);margin-bottom:1.5rem;display:flex;align-items:center;gap:8px}
        .info-bar i{color:var(--pk400)}
        .info-bar strong{color:var(--g700)}

        .srv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem}

        .srv-card{background:var(--w);border:1.5px solid var(--g100);border-radius:20px;overflow:hidden;transition:var(--tr);position:relative;display:flex;flex-direction:column}
        .srv-card:hover{transform:translateY(-6px);box-shadow:var(--shl);border-color:rgba(236,72,153,.2)}
        .srv-card-top{padding:1.5rem 1.5rem .8rem;display:flex;align-items:flex-start;gap:1rem}
        .srv-icon-box{width:60px;height:60px;min-width:60px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;position:relative;transition:var(--tr)}
        .srv-card:hover .srv-icon-box{transform:scale(1.08) rotate(-3deg)}
        .srv-icon-badge{position:absolute;top:-6px;right:-6px;width:22px;height:22px;border-radius:7px;background:var(--w);display:flex;align-items:center;justify-content:center;font-size:.6rem;box-shadow:0 2px 8px rgba(0,0,0,.12)}
        .srv-name-box{flex:1;min-width:0}
        .srv-name{font-size:1.05rem;font-weight:800;color:var(--g800);margin-bottom:3px;line-height:1.3}
        .srv-jenis-badge{display:inline-flex;padding:3px 10px;border-radius:8px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .srv-jenis-badge.jasa{background:#ede9fe;color:#7c3aed}
        .srv-jenis-badge.self{background:#dcfce7;color:#16a34a}
        .srv-jenis-badge.reguler{background:#dbeafe;color:#2563eb}
        .srv-jenis-badge.express{background:#fee2e2;color:#dc2626}
        .srv-jenis-badge.premium{background:#fef3c7;color:#b45309}

        .srv-desc{padding:0 1.5rem;font-size:.84rem;color:var(--g500);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1}

        .srv-meta{padding:.8rem 1.5rem;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .srv-meta-item{display:flex;align-items:center;gap:4px;font-size:.75rem;color:var(--g400)}
        .srv-meta-item i{font-size:.7rem}

        .srv-bottom{padding:1rem 1.5rem 1.5rem;display:flex;align-items:center;justify-content:space-between;margin-top:auto;border-top:1px solid var(--g50)}
        .srv-price-box{}
        .srv-price{font-size:1.25rem;font-weight:900;color:var(--pk600);line-height:1}
        .srv-satuan{font-size:.75rem;color:var(--g400);font-weight:500;margin-top:2px}
        .srv-actions{display:flex;gap:8px}
        .btn-detail{width:40px;height:40px;border-radius:12px;background:var(--g50);color:var(--g500);display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:var(--tr);border:none}
        .btn-detail:hover{background:var(--pk50);color:var(--pk600)}
        .btn-add-cart{padding:10px 18px;border-radius:12px;background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:6px;transition:var(--tr);border:none;box-shadow:0 4px 12px rgba(236,72,153,.2)}
        .btn-add-cart:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(236,72,153,.3)}

        .empty-state{text-align:center;padding:5rem 2rem;background:var(--w);border:1.5px solid var(--g100);border-radius:24px}
        .empty-icon-box{width:100px;height:100px;border-radius:28px;background:var(--pk50);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;animation:float 3s ease-in-out infinite}
        .empty-icon-box i{font-size:2.5rem;color:var(--pk400)}
        .empty-state h2{font-size:1.4rem;font-weight:800;color:var(--g800);margin-bottom:.4rem}
        .empty-state p{color:var(--g500);font-size:.92rem;margin-bottom:1.5rem;max-width:420px;margin-left:auto;margin-right:auto;line-height:1.6}

        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px)}
        .modal-overlay.show{display:flex}
        .modal{background:var(--w);border-radius:24px;width:100%;max-width:520px;max-height:85vh;overflow-y:auto;animation:modalIn .3s ease}
        @keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:none}}
        .modal-hero{padding:2rem;display:flex;align-items:center;gap:1.2rem;position:relative;overflow:hidden}
        .modal-hero::after{content:'';position:absolute;inset:0;opacity:.08;border-radius:24px 24px 0 0}
        .modal-hero-icon{width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2rem;z-index:1;position:relative}
        .modal-hero-badge{position:absolute;bottom:-4px;right:-4px;width:26px;height:26px;border-radius:8px;background:var(--w);display:flex;align-items:center;justify-content:center;font-size:.65rem;box-shadow:0 2px 8px rgba(0,0,0,.12);z-index:2}
        .modal-hero-text{flex:1;z-index:1}
        .modal-hero-text h2{font-size:1.2rem;font-weight:900;color:var(--g900)}
        .modal-hero-text .mh-jenis{font-size:.75rem;font-weight:600;margin-top:3px}
        .modal-close{position:absolute;top:1rem;right:1rem;width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.9);color:var(--g500);cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center;transition:var(--tr);z-index:2;border:none;backdrop-filter:blur(8px)}
        .modal-close:hover{background:#fee2e2;color:#ef4444}
        .modal-body{padding:1.5rem 2rem 2rem}
        .modal-price-row{display:flex;align-items:center;justify-content:space-between;padding:1.2rem;background:var(--g50);border-radius:14px;margin-bottom:1.2rem}
        .modal-price-val{font-size:1.5rem;font-weight:900;color:var(--pk600)}
        .modal-price-sat{font-size:.82rem;color:var(--g400)}
        .modal-desc{font-size:.9rem;color:var(--g600);line-height:1.7;margin-bottom:1.2rem}
        .modal-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1.5rem}
        .modal-info-item{display:flex;align-items:center;gap:10px;padding:.8rem 1rem;background:var(--g50);border-radius:10px}
        .modal-info-item i{color:var(--pk500);font-size:.85rem;width:18px;text-align:center}
        .modal-info-item .mi-label{font-size:.72rem;color:var(--g400);font-weight:500}
        .modal-info-item .mi-val{font-size:.85rem;font-weight:700;color:var(--g800)}
        .modal-add-btn{width:100%;padding:14px;border-radius:14px;background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;font-size:.95rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:var(--tr);border:none;box-shadow:0 6px 20px rgba(236,72,153,.25)}
        .modal-add-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(236,72,153,.35)}

        .toast-ct{position:fixed;top:80px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px}
        .toast{padding:14px 24px;border-radius:14px;font-size:.88rem;font-weight:600;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,.15);animation:tIn .4s ease,tOut .4s ease 2.6s forwards;display:flex;align-items:center;gap:10px}
        .toast.ok{background:linear-gradient(135deg,#10b981,#059669)}
        .toast.err{background:linear-gradient(135deg,#ef4444,#dc2626)}
        @keyframes tIn{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}
        @keyframes tOut{to{opacity:0;transform:translateX(100px)}}

        .fi{opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease}
        .fi.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}.d5{transition-delay:.25s}.d6{transition-delay:.3s}.d7{transition-delay:.35s}.d8{transition-delay:.4s}.d9{transition-delay:.45s}.d10{transition-delay:.5s}

        @media(max-width:1024px){.srv-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}.sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}.topbar{padding:0 1rem}.content{padding:1rem}
            .srv-grid{grid-template-columns:1fr}
            .hero-layanan{flex-direction:column;padding:1.5rem;text-align:center}
            .hero-stats{justify-content:center}
            .filter-bar{gap:4px}.ftab{padding:7px 12px;font-size:.76rem}
            .search-section{flex-direction:column}.cart-mini-btn{justify-content:center}
            .modal-info-grid{grid-template-columns:1fr}
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
        <a href="layanan.php" class="sidebar-link active"><i class="fa-solid fa-shirt"></i> Layanan</a>
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
        <div class="topbar-title"><i class="fa-solid fa-shirt"></i> Layanan</div>
        <div class="topbar-right">
            <a href="cart.php" class="topbar-btn"><i class="fa-solid fa-cart-shopping"></i> Keranjang<?= $cartCount > 0 ? ' ('.$cartCount.')' : '' ?></a>
            <a href="logout.php" class="topbar-btn logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>

    <div class="content">
    <div class="content-in">

        <?php if ($flash): ?>
        <div class="flash-box flash-warn fi">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars(is_array($flash) ? ($flash['message'] ?? json_encode($flash)) : $flash) ?></span>
        </div>
        <?php endif; ?>

        <div class="hero-layanan fi d1">
            <div class="hero-icon-box">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <div class="hero-text">
                <h1>Layanan <span>Laundry</span> Kami</h1>
                <p>Pilih layanan terbaik sesuai kebutuhanmu. Semua dikerjakan oleh tenaga profesional dengan peralatan modern.</p>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $totalLayanan ?></div>
                    <div class="hero-stat-lbl">Layanan</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= count($jenisList) ?></div>
                    <div class="hero-stat-lbl">Kategori</div>
                </div>
            </div>
        </div>

        <div class="search-section fi d2">
            <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass search-ic"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Cari layanan, misal: cuci kering, setrika..." value="<?= htmlspecialchars($search) ?>" oninput="handleSearch();toggleClear();">
                <button class="search-clear" id="searchClear" onclick="clearSearch()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <a href="cart.php" class="cart-mini-btn">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Keranjang</span>
                <?php if($cartCount > 0): ?><span class="cart-ct-badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
        </div>

        <div class="filter-bar fi d3">
            <button class="ftab <?= empty($filter) ? 'active' : '' ?>" onclick="setFilter('')">
                <i class="fa-solid fa-border-all" style="font-size:.75rem"></i> Semua
                <span class="ft-count"><?= $totalLayanan ?></span>
            </button>
            <?php foreach ($jenisList as $j):
                $jStyle = getServiceStyle('', $j);
                $jCount = $jenisCount[$j] ?? 0;
            ?>
            <button class="ftab <?= $filter === $j ? 'active' : '' ?>" onclick="setFilter('<?= htmlspecialchars(addslashes($j)) ?>')">
                <i class="fa-solid <?= $jStyle['icon'] ?>" style="font-size:.75rem"></i>
                <?= htmlspecialchars(ucwords(str_replace(['_','-'], ' ', $j))) ?>
                <span class="ft-count"><?= $jCount ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="info-bar fi d4">
            <i class="fa-solid fa-filter"></i>
            <?php if ($search): ?>
                Menampilkan <strong><?= count($layanan) ?></strong> hasil untuk "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php if($filter): ?> dalam kategori <strong><?= htmlspecialchars(ucwords(str_replace(['_','-'], ' ', $filter))) ?></strong><?php endif; ?>
            <?php elseif ($filter): ?>
                Menampilkan <strong><?= count($layanan) ?></strong> layanan kategori <strong><?= htmlspecialchars(ucwords(str_replace(['_','-'], ' ', $filter))) ?></strong>
            <?php else: ?>
                Menampilkan <strong><?= count($layanan) ?></strong> layanan tersedia
            <?php endif; ?>
        </div>

        <?php if (empty($layanan)): ?>
        <div class="empty-state fi d5">
            <div class="empty-icon-box">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <h2>Layanan Tidak Ditemukan</h2>
            <p><?= $search ? 'Tidak ada layanan yang cocok dengan pencarian "<strong>'.htmlspecialchars($search).'</strong>".' : 'Tidak ada layanan dalam kategori ini.' ?></p>
            <a href="layanan.php" class="btn-add-cart" style="display:inline-flex"><i class="fa-solid fa-arrow-left"></i> Lihat Semua Layanan</a>
        </div>
        <?php else: ?>
        <div class="srv-grid">
            <?php foreach ($layanan as $i => $l):
                $style = getServiceStyle($l['nama_layanan'] ?? '', $l['jenis'] ?? '');
                $jenis = $l['jenis'] ?? '';
                $jenisLower = strtolower($jenis);
                $badgeClass = 'reguler';
                if (stripos($jenisLower, 'jasa') !== false) $badgeClass = 'jasa';
                elseif (stripos($jenisLower, 'self') !== false) $badgeClass = 'self';
                elseif (stripos($jenisLower, 'express') !== false || stripos($jenisLower, 'kilat') !== false) $badgeClass = 'express';
                elseif (stripos($jenisLower, 'premium') !== false || stripos($jenisLower, 'vip') !== false) $badgeClass = 'premium';
                $harga = $l[$colHarga] ?? 0;
                $satuan = $l[$colSatuan] ?? 'kg';
                $estimasi = $l[$colEstimasi] ?? '';
                $desc = $l[$colDesc] ?? '';
            ?>
            <div class="srv-card fi d<?= min(($i % 8) + 1, 8) ?>"
                 data-id="<?= $l['id_layanan'] ?>"
                 data-name="<?= htmlspecialchars(addslashes($l['nama_layanan'])) ?>"
                 data-harga="<?= $harga ?>"
                 data-satuan="<?= htmlspecialchars(addslashes($satuan)) ?>"
                 data-desc="<?= htmlspecialchars(addslashes($desc)) ?>"
                 data-jenis="<?= htmlspecialchars(addslashes(ucwords(str_replace(['_','-'],' ',$jenis)))) ?>"
                 data-estimasi="<?= htmlspecialchars(addslashes($estimasi)) ?>"
                 data-icon="<?= $style['icon'] ?>"
                 data-emoji="<?= $style['emoji'] ?>"
                 data-badge="<?= $badgeClass ?>"
                 data-bg1="<?= $style['bg1'] ?>"
                 data-bg2="<?= $style['bg2'] ?>"
                 data-clr="<?= $style['clr'] ?>">
                <div class="srv-card-top">
                    <div class="srv-icon-box" style="background:linear-gradient(135deg,<?= $style['bg1'] ?>,<?= $style['bg2'] ?>);color:<?= $style['clr'] ?>">
                        <i class="fa-solid <?= $style['icon'] ?>"></i>
                        <span class="srv-icon-badge" style="color:<?= $style['clr'] ?>"><i class="fa-solid <?= $style['emoji'] ?>"></i></span>
                    </div>
                    <div class="srv-name-box">
                        <div class="srv-name"><?= htmlspecialchars($l['nama_layanan']) ?></div>
                        <?php if($jenis): ?>
                        <span class="srv-jenis-badge <?= $badgeClass ?>"><?= htmlspecialchars(ucwords(str_replace(['_','-'], ' ', $jenis))) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($desc): ?>
                <div class="srv-desc"><?= htmlspecialchars($desc) ?></div>
                <?php endif; ?>
                <?php if($estimasi): ?>
                <div class="srv-meta">
                    <div class="srv-meta-item"><i class="fa-regular fa-clock"></i> Est. <?= htmlspecialchars($estimasi) ?></div>
                    <div class="srv-meta-item"><i class="fa-solid fa-weight-hanging"></i> Per <?= htmlspecialchars($satuan) ?></div>
                </div>
                <?php endif; ?>
                <div class="srv-bottom">
                    <div class="srv-price-box">
                        <div class="srv-price"><?= formatRupiah($harga) ?></div>
                        <div class="srv-satuan">per <?= htmlspecialchars($satuan) ?></div>
                    </div>
                    <div class="srv-actions">
                        <button class="btn-detail" onclick="openDetail(this.closest('.srv-card'))" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                        <button class="btn-add-cart" onclick="addToCart(<?= $l['id_layanan'] ?>,'<?= htmlspecialchars(addslashes($l['nama_layanan'])) ?>')">
                            <i class="fa-solid fa-cart-plus"></i> Tambah
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    </div>
</div>

<div class="modal-overlay" id="detailModal">
    <div class="modal">
        <div class="modal-hero" id="modalHero">
            <button class="modal-close" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-hero-icon" id="modalIcon"></div>
            <span class="modal-hero-badge" id="modalEmoji"></span>
            <div class="modal-hero-text">
                <h2 id="modalName"></h2>
                <span class="mh-jenis" id="modalJenis"></span>
            </div>
        </div>
        <div class="modal-body">
            <div class="modal-price-row">
                <div>
                    <div class="modal-price-val" id="modalPrice"></div>
                    <div class="modal-price-sat" id="modalSat"></div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:.72rem;color:var(--g400);font-weight:600">HARGA</div>
                    <div style="font-size:.78rem;color:var(--g500)">Sudah termasuk biaya handling</div>
                </div>
            </div>
            <div id="modalDesc" class="modal-desc" style="display:none"></div>
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <i class="fa-solid fa-tag"></i>
                    <div>
                        <div class="mi-label">Kategori</div>
                        <div class="mi-val" id="modalInfoJenis"></div>
                    </div>
                </div>
                <div class="modal-info-item" id="modalEstRow" style="display:none">
                    <i class="fa-regular fa-clock"></i>
                    <div>
                        <div class="mi-label">Estimasi Waktu</div>
                        <div class="mi-val" id="modalEstimasi"></div>
                    </div>
                </div>
                <div class="modal-info-item">
                    <i class="fa-solid fa-weight-hanging"></i>
                    <div>
                        <div class="mi-label">Satuan</div>
                        <div class="mi-val" id="modalInfoSat"></div>
                    </div>
                </div>
                <div class="modal-info-item">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <div class="mi-label">Garansi</div>
                        <div class="mi-val">Hasil Maksimal</div>
                    </div>
                </div>
            </div>
            <button class="modal-add-btn" id="modalAddBtn" onclick="addToCartFromModal()">
                <i class="fa-solid fa-cart-plus"></i> Tambahkan ke Keranjang
            </button>
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

var searchTimer;
function handleSearch(){
    clearTimeout(searchTimer);
    searchTimer=setTimeout(function(){
        var val=document.getElementById('searchInput').value;
        var params=new URLSearchParams(window.location.search);
        if(val) params.set('q',val); else params.delete('q');
        window.location.href=window.location.pathname+'?'+params.toString();
    },600);
}
function clearSearch(){
    document.getElementById('searchInput').value='';
    var params=new URLSearchParams(window.location.search);
    params.delete('q');
    window.location.href=window.location.pathname+'?'+params.toString();
}
function toggleClear(){
    document.getElementById('searchClear').style.display=document.getElementById('searchInput').value?'flex':'none';
}
function setFilter(jenis){
    var params=new URLSearchParams(window.location.search);
    if(jenis) params.set('jenis',jenis); else params.delete('jenis');
    params.delete('page');
    window.location.href=window.location.pathname+'?'+params.toString();
}

function addToCart(id,name){
    var btn=event.target.closest('.btn-add-cart');
    if(btn){btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i>...';}
    fetch('<?= APP_URL ?>/api/cart.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'add',id_layanan:id,qty:1})
    }).then(function(r){return r.json()}).then(function(d){
        if(d.success) showToast(name+' ditambahkan ke keranjang','ok');
        else showToast(d.message||'Gagal menambahkan','err');
        if(btn){btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-cart-plus"></i> Tambah';}
    }).catch(function(){showToast('Error koneksi','err');if(btn){btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-cart-plus"></i> Tambah';}});
}

var currentModalId=0;
function openDetail(card){
    currentModalId=parseInt(card.dataset.id);
    var name=card.dataset.name;
    var harga=card.dataset.harga;
    var satuan=card.dataset.satuan;
    var desc=card.dataset.desc;
    var jenis=card.dataset.jenis;
    var estimasi=card.dataset.estimasi;
    var icon=card.dataset.icon;
    var emoji=card.dataset.emoji;
    var badge=card.dataset.badge;
    var bg1=card.dataset.bg1;
    var bg2=card.dataset.bg2;
    var clr=card.dataset.clr;

    document.getElementById('modalHero').style.background='linear-gradient(135deg,'+bg1+','+bg2+')';
    document.getElementById('modalHero').querySelector('.modal-close').style.background='rgba(255,255,255,.8)';
    document.getElementById('modalIcon').style.background='rgba(255,255,255,.5)';
    document.getElementById('modalIcon').style.color=clr;
    document.getElementById('modalIcon').innerHTML='<i class="fa-solid '+icon+'"></i>';
    document.getElementById('modalEmoji').style.color=clr;
    document.getElementById('modalEmoji').innerHTML='<i class="fa-solid '+emoji+'"></i>';
    document.getElementById('modalName').textContent=name;

    var jBadge=document.getElementById('modalJenis');
    jBadge.textContent=jenis||'Layanan';
    jBadge.className='mh-jenis srv-jenis-badge '+badge;

    document.getElementById('modalPrice').textContent='Rp '+Number(harga).toLocaleString('id-ID');
    document.getElementById('modalSat').textContent='per '+satuan;

    var descEl=document.getElementById('modalDesc');
    if(desc){descEl.textContent=desc;descEl.style.display='block';}
    else{descEl.style.display='none';}

    document.getElementById('modalInfoJenis').textContent=jenis||'Reguler';
    document.getElementById('modalInfoSat').textContent=satuan;

    var estRow=document.getElementById('modalEstRow');
    if(estimasi){document.getElementById('modalEstimasi').textContent=estimasi;estRow.style.display='flex';}
    else{estRow.style.display='none';}

    document.getElementById('detailModal').classList.add('show');
    document.body.style.overflow='hidden';
}

function closeDetail(){
    document.getElementById('detailModal').classList.remove('show');
    document.body.style.overflow='';
}
document.getElementById('detailModal').addEventListener('click',function(e){if(e.target===this)closeDetail();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDetail();});

function addToCartFromModal(){
    addToCart(currentModalId,document.getElementById('modalName').textContent);
}

function showToast(msg,type){
    type=type||'ok';
    var ct=document.getElementById('toastCt'),t=document.createElement('div');
    t.className='toast '+type;
    t.innerHTML='<i class="fa-solid '+(type==='ok'?'fa-circle-check':'fa-circle-exclamation')+'"></i> '+msg;
    ct.appendChild(t);setTimeout(function(){t.remove()},3200);
}
document.addEventListener('DOMContentLoaded',toggleClear);
</script>
</body>
</html>