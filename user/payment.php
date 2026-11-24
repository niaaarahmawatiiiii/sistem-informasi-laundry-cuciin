<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/login.php');
    exit;
}

$pageTitle = 'Pembayaran — CUCI.IN';
$userNama = $_SESSION['userNama'] ?? 'User';

$orderId = (int)($_GET['order'] ?? 0);
if (!$orderId) {
    header('Location: ' . APP_URL . '/user/orders.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$order = $db->query("
    SELECT o.*, p.id_pembayaran, p.metode_pembayaran, p.status_pembayaran, p.jumlah 
    FROM orders o 
    LEFT JOIN pembayaran p ON o.id_order=p.id_order 
    WHERE o.id_order=$orderId AND o.id_user=$userId
")->fetch_assoc();

if (!$order) {
    header('Location: ' . APP_URL . '/user/orders.php');
    exit;
}

if (($order['status_pembayaran'] ?? '') === 'dibayar') {
    setFlash('warning', 'Pesanan ini sudah dibayar.');
    header('Location: ' . APP_URL . '/user/order-detail.php?id=' . $orderId);
    exit;
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
            --pk400:#f472b6;--pk500:#ec4899;--pk600:#db2777;--pk700:#be185d;
            --w:#fff;--g50:#f9fafb;--g100:#f3f4f6;--g200:#e5e7eb;--g300:#d1d5db;
            --g400:#9ca3af;--g500:#6b7280;--g600:#4b5563;--g700:#374151;--g800:#1f2937;--g900:#111827;
            --sidebar-w:260px;--rl:20px;
            --sh:0 4px 20px rgba(236,72,153,.08);--tr:all .3s cubic-bezier(.4,0,.2,1);
        }
        body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--g50);color:var(--g800);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        a{text-decoration:none;color:inherit;transition:var(--tr)}button{cursor:pointer;border:none;font-family:inherit}

        .sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--w);border-right:1px solid var(--g100);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:var(--tr)}
        .sidebar-brand{padding:1.5rem 1.2rem;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--g100)}
        .sidebar-brand img{height:36px;width:36px;object-fit:contain;filter:drop-shadow(0 2px 8px rgba(236,72,153,.2))}
        .sidebar-brand span{font-size:1.25rem;font-weight:900;background:linear-gradient(135deg,var(--pk500),var(--pk700));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .sidebar-nav{flex:1;padding:1rem .8rem;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
        .sidebar-label{font-size:.7rem;font-weight:700;color:var(--g400);text-transform:uppercase;letter-spacing:1.5px;padding:.8rem .8rem .4rem}
        .sidebar-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:.88rem;font-weight:500;color:var(--g600);transition:var(--tr)}
        .sidebar-link:hover{background:var(--pk50);color:var(--pk600)}
        .sidebar-link.active{background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;font-weight:600;box-shadow:0 4px 15px rgba(236,72,153,.25)}
        .sidebar-link i{width:20px;text-align:center;font-size:.9rem}
        .sidebar-link.danger{color:#ef4444}.sidebar-link.danger:hover{background:#fef2f2}
        .sidebar-user{padding:1rem 1.2rem;border-top:1px solid var(--g100);display:flex;align-items:center;gap:10px}
        .sidebar-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--pk400),var(--pk600));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0}
        .sidebar-uname{font-size:.85rem;font-weight:600;color:var(--g800)}
        .sidebar-urole{font-size:.72rem;color:var(--g400);text-transform:capitalize}
        .sidebar-toggle{display:none;position:fixed;top:1rem;left:1rem;z-index:200;width:44px;height:44px;border-radius:12px;background:var(--w);border:1px solid var(--g200);box-shadow:var(--sh);font-size:1.1rem;color:var(--g600);align-items:center;justify-content:center}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:90}

        .main-ct{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column}
        .topbar{height:64px;background:var(--w);border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:50}
        .topbar-title{font-size:1rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .topbar-title i{color:var(--pk400)}
        .topbar-user{display:flex;align-items:center;gap:10px;padding-left:12px;border-left:1px solid var(--g200)}
        .topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--pk400),var(--pk600));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem}
        .topbar-uname{font-size:.85rem;font-weight:600;color:var(--g700)}
        .topbar-logout{color:var(--g400);font-size:.9rem;padding:6px 10px;border-radius:8px;transition:var(--tr)}
        .topbar-logout:hover{color:#ef4444;background:#fef2f2}

        .content{flex:1;padding:2rem}
        .content-in{max-width:650px;margin:0 auto}
        .back-link{display:inline-flex;align-items:center;gap:6px;font-size:.88rem;font-weight:600;color:var(--g500);margin-bottom:1.5rem;transition:var(--tr)}
        .back-link:hover{color:var(--pk600);gap:8px}
        .pg-header{margin-bottom:2rem}
        .pg-title{font-size:1.8rem;font-weight:900;color:var(--g900);display:flex;align-items:center;gap:12px}
        .pg-title i{color:var(--pk500)}
        .pg-sub{font-size:.95rem;color:var(--g500);margin-top:.25rem}

        .card{background:var(--w);border:1px solid var(--g100);border-radius:var(--rl);padding:1.8rem;box-shadow:0 2px 10px rgba(0,0,0,.02);transition:var(--tr)}
        .card-highlight{border:2px solid var(--pk400);box-shadow:0 8px 30px rgba(236,72,153,.1)}
        .total-display{text-align:center;padding:1.5rem 0 1rem}
        .total-icon{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--pk50),var(--pk100));display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--pk500);margin-bottom:1rem}
        .total-amount{font-size:2.2rem;font-weight:900;background:linear-gradient(135deg,var(--pk500),var(--pk700));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:.25rem}
        .total-label{font-size:.88rem;color:var(--g500);font-weight:500}
        .detail-row{display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--g100)}
        .detail-row:last-child{border-bottom:none}
        .detail-label{font-size:.88rem;color:var(--g500)}
        .detail-value{font-size:.88rem;font-weight:700;color:var(--g800)}
        .method-card{border-radius:var(--rl);padding:1.5rem;margin-bottom:1.5rem;display:flex;flex-direction:column;gap:1rem}
        .method-card.bank{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
        .method-card.ewallet{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
        .method-card.cod{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
        .method-title{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:.5rem}
        .method-row{display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.25rem}
        .method-row-label{opacity:.7}
        .method-row-value{font-weight:700}
        .method-big{font-size:1.3rem;font-weight:800;letter-spacing:1px;margin:.5rem 0}

        .fi{opacity:0;transform:translateY(25px);transition:opacity .6s ease,transform .6s ease}
        .fi.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}
            .sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}
            .sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}
            .topbar{padding:0 1rem}
            .content{padding:1rem}
            .topbar-uname{display:none}
            .total-amount{font-size:1.8rem}
        }
    </style>
</head>
<body>

<button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>" style="display:flex;align-items:center;gap:10px">
            <img src="<?= APP_URL ?>/img/logo.png" alt="CUCI.IN">
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
        <a href="<?= APP_URL ?>" class="sidebar-link"><i class="fa-solid fa-house"></i> Ke Beranda</a>
        <a href="login.php?logout=1" class="sidebar-link danger"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </nav>
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($userNama, 0, 1)) ?></div>
        <div>
            <div class="sidebar-uname"><?= htmlspecialchars($userNama) ?></div>
            <div class="sidebar-urole"><?= $_SESSION['role'] ?? 'user' ?></div>
        </div>
    </div>
</aside>

<div class="main-ct">
    <div class="topbar">
        <div class="topbar-title"><i class="fa-solid fa-credit-card"></i> Pembayaran</div>
        <div class="topbar-user">
            <div class="topbar-avatar"><?= strtoupper(substr($userNama, 0, 1)) ?></div>
            <span class="topbar-uname"><?= htmlspecialchars($userNama) ?></span>
            <a href="login.php?logout=1" class="topbar-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>

    <div class="content">
    <div class="content-in">

        <a href="order-detail.php?id=<?= $orderId ?>" class="back-link fi">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Pesanan
        </a>

        <div class="pg-header fi d1">
            <div class="pg-title"><i class="fa-solid fa-wallet"></i> Pembayaran Pesanan</div>
            <div class="pg-sub">Pesanan #<?= htmlspecialchars($order['kode_order']) ?></div>
        </div>

        <div class="card card-highlight fi d2" style="margin-bottom:1.5rem;">
            <div class="total-display">
                <div class="total-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div class="total-amount"><?= formatRupiah($order['total_harga']) ?></div>
                <div class="total-label">Total Tagihan</div>
            </div>
            <div style="margin-top:1rem;">
                <div class="detail-row">
                    <span class="detail-label">Kode Pesanan</span>
                    <span class="detail-value"><?= htmlspecialchars($order['kode_order']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Metode Pembayaran</span>
                    <span class="detail-value"><?= ucfirst($order['metode_pembayaran'] ?? 'Transfer') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value" style="color:#f59e0b;">Menunggu Verifikasi Admin</span>
                </div>
            </div>
        </div>

        <?php if(($order['metode_pembayaran'] ?? '') === 'transfer' || empty($order['metode_pembayaran'])): ?>
        <div class="method-card bank fi d3">
            <div class="method-title"><i class="fa-solid fa-building-columns"></i> Info Transfer Bank</div>
            <div class="method-row">
                <span class="method-row-label">Bank Tujuan</span>
                <span class="method-row-value">BCA / Mandiri / BNI</span>
            </div>
            <div class="method-row">
                <span class="method-row-label">No. Rekening Virtual</span>
                <span class="method-row-value">8801 <?= str_pad($orderId, 8, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div style="text-align:center;padding:.5rem;background:rgba(255,255,255,.5);border-radius:10px;">
                <div class="method-big">8801 <?= str_pad($orderId, 8, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div class="method-row">
                <span class="method-row-label">Nominal Transfer</span>
                <span class="method-row-value"><?= formatRupiah($order['total_harga']) ?></span>
            </div>
            <div style="font-size:.8rem;color:inherit;opacity:.8">
                Setelah melakukan pembayaran, admin akan memverifikasi dan status pesanan akan diperbarui otomatis.
            </div>
        </div>

        <?php elseif(($order['metode_pembayaran'] ?? '') === 'ewallet'): ?>
        <div class="method-card ewallet fi d3">
            <div class="method-title"><i class="fa-solid fa-mobile-screen-button"></i> Info E-Wallet</div>
            <div style="text-align:center;padding:.5rem;background:rgba(255,255,255,.5);border-radius:10px;">
                <div class="method-big">0812-3456-7890</div>
                <div style="font-size:.78rem;opacity:.7">GoPay / OVO / Dana</div>
            </div>
            <div class="method-row">
                <span class="method-row-label">Nominal Transfer</span>
                <span class="method-row-value"><?= formatRupiah($order['total_harga']) ?></span>
            </div>
            <div style="font-size:.8rem;color:inherit;opacity:.8">
                Setelah pembayaran diterima dan dicek admin, status pesanan akan diperbarui otomatis.
            </div>
        </div>

        <?php elseif(($order['metode_pembayaran'] ?? '') === 'cod'): ?>
        <div class="method-card cod fi d3">
            <div class="method-title"><i class="fa-solid fa-hand-holding-dollar"></i> Bayar di Tempat (COD)</div>
            <p style="font-size:.9rem;">
                Siapkan uang tunai pas sejumlah <strong><?= formatRupiah($order['total_harga']) ?></strong> saat kurir tiba di lokasi Anda.
            </p>
            <p style="font-size:.8rem;opacity:.8">
                Status pesanan akan disesuaikan oleh admin setelah proses pengantaran atau verifikasi.
            </p>
        </div>
        <?php endif; ?>

        <div class="card fi d4">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                <div style="width:40px;height:40px;border-radius:10px;background:#d1fae5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fa-solid fa-shield-check"></i>
                </div>
                <div>
                    <div style="font-weight:700;color:var(--g800);">Status Pembayaran</div>
                    <div style="font-size:.8rem;color:var(--g500);">Pembayaran akan diverifikasi admin</div>
                </div>
            </div>

            <div class="detail-row">
                <span class="detail-label">Pembayaran</span>
                <span class="detail-value">Menunggu pengecekan admin</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Aksi</span>
                <span class="detail-value">Tidak perlu input manual</span>
            </div>
        </div>

    </div>
    </div>
</div>

<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . APP_URL . '/user/login.php');
    exit;
}
?>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
const obs=new IntersectionObserver(e=>{e.forEach(en=>{if(en.isIntersecting)en.target.classList.add('v')})},{threshold:.05});
document.querySelectorAll('.fi').forEach(el=>obs.observe(el));
</script>
</body>
</html>