<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/login.php');
    exit;
}

 $pageTitle = 'Detail Pesanan — CUCI.IN';
 $userNama = $_SESSION['user_nama'] ?? 'User';
 $flash = getFlash();

 $orderId = (int)($_GET['id'] ?? 0);
 $userId = (int)($_SESSION['user_id'] ?? 0);

if (!$orderId) {
    header('Location: ' . APP_URL . '/user/orders.php');
    exit;
}

// FIX ERROR: TAMBAHKAN "o." DI DEPAN id_user DAN id_order PADA WHERE CLAUSE
 $order = $db->query("
    SELECT o.*, p.id_pembayaran, p.metode_pembayaran, p.status_pembayaran, p.jumlah, p.tanggal_bayar
    FROM orders o
    LEFT JOIN pembayaran p ON o.id_order = p.id_order
    WHERE o.id_user = $userId AND o.id_order = $orderId
")->fetch_assoc();

if (!$order) {
    setFlash('warning', 'Pesanan tidak ditemukan.');
    header('Location: ' . APP_URL . '/user/orders.php');
    exit;
}

// Ambil detail item
 $orderItems = [];
try {
    $orderItems = $db->query("
        SELECT od.*, l.nama_layanan, l.satuan 
        FROM order_detail od 
        JOIN layanan l ON od.id_layanan = l.id_layanan 
        WHERE od.id_order = $orderId
    ")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

// Ambil tracking
 $tracking = [];
try {
    $tracking = $db->query("
        SELECT * FROM tracking 
        WHERE id_order = $orderId 
        ORDER BY waktu_update ASC
    ")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

function getStatusColor($s) {
    $colors = [
        'pending' => '#f59e0b', 'dikonfirmasi' => '#3b82f6', 'diproses' => '#6366f1',
        'dicuci' => '#8b5cf6', 'selesai' => '#10b981', 'diantar' => '#06b6d4', 'dibatalkan' => '#ef4444'
    ];
    return $colors[$s] ?? '#6b7280';
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
            --sidebar-w:260px;--rl:20px;
            --sh:0 4px 20px rgba(236,72,153,.08);--shl:0 12px 40px rgba(236,72,153,.12);
            --tr:all .3s cubic-bezier(.4,0,.2,1);
        }
        body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--g50);color:var(--g800);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        a{text-decoration:none;color:inherit;transition:var(--tr)}button{cursor:pointer;border:none;font-family:inherit}

        /* SIDEBAR & MAIN (SAMA SEPERTI LAINNYA) */
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
        .topbar-left{display:flex;align-items:center;gap:10px}
        .topbar-back{padding:8px 14px;border-radius:10px;color:var(--g600);font-size:.88rem;font-weight:600;transition:var(--tr)}
        .topbar-back:hover{background:var(--pk50);color:var(--pk500)}
        .topbar-title{font-size:1rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .topbar-title i{color:var(--pk400)}
        .topbar-user{display:flex;align-items:center;gap:10px;padding-left:12px;border-left:1px solid var(--g200)}
        .topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--pk400),var(--pk600));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem}
        .topbar-uname{font-size:.85rem;font-weight:600;color:var(--g700)}
        .topbar-logout{color:var(--g400);font-size:.9rem;padding:6px 10px;border-radius:8px;transition:var(--tr)}
        .topbar-logout:hover{color:#ef4444;background:#fef2f2}
        .content{flex:1;padding:2rem}
        .content-in{max-width:900px;margin:0 auto}

        /* FLASH */
        .flash-box{padding:1rem 1.2rem;border-radius:var(--rl);margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;animation:fadeUp .5s ease}
        .flash-ok{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .flash-warn{background:#fef3c7;color:#d97706;border:1px solid #fde68a}
        @keyframes fadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}

        /* HEADER */
        .pg-header{margin-bottom:2rem;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem}
        .pg-title{font-size:1.8rem;font-weight:900;color:var(--g900);display:flex;align-items:center;gap:12px}
        .pg-title i{color:var(--pk500)}
        .pg-sub{font-size:.95rem;color:var(--g500);margin-top:.25rem}
        .badge{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:.82rem;font-weight:700}
        
        /* CARDS */
        .card{background:var(--w);border:1px solid var(--g100);border-radius:var(--rl);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.02);margin-bottom:1.5rem}
        .card-hd{padding:1rem 1.3rem;border-bottom:1px solid var(--g100);font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .card-hd i{color:var(--pk400)}
        .card-bd{padding:1.3rem}
        
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .detail-item{display:flex;flex-direction:column;gap:2px}
        .detail-label{font-size:.8rem;color:var(--g500);font-weight:500}
        .detail-value{font-size:.95rem;font-weight:700;color:var(--g800)}
        .detail-value.price{color:var(--pk600);font-size:1.2rem}

        /* TRACKING TIMELINE */
        .timeline{position:relative;padding-left:30px}
        .timeline::before{content:'';position:absolute;left:11px;top:5px;bottom:5px;width:2px;background:var(--g200)}
        .tl-item{position:relative;padding-bottom:1.5rem}
        .tl-item:last-child{padding-bottom:0}
        .tl-dot{position:absolute;left:-30px;top:2px;width:24px;height:24px;border-radius:50%;background:var(--w);border:2px solid var(--g300);display:flex;align-items:center;justify-content:center;z-index:1}
        .tl-dot i{font-size:.6rem;color:var(--g300)}
        .tl-item.active .tl-dot{border-color:var(--pk500);background:var(--pk50)}
        .tl-item.active .tl-dot i{color:var(--pk500)}
        .tl-title{font-size:.9rem;font-weight:700;color:var(--g800);margin-bottom:2px}
        .tl-desc{font-size:.82rem;color:var(--g500);margin-bottom:2px}
        .tl-time{font-size:.75rem;color:var(--g400)}

        /* TABLE LIST */
        .item-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem 0;border-bottom:1px solid var(--g50)}
        .item-row:last-child{border-bottom:none}
        .item-name{font-weight:600;color:var(--g800);font-size:.9rem}
        .item-detail{font-size:.8rem;color:var(--g500);margin-top:2px}
        .item-price{font-weight:700;color:var(--g800);font-size:.9rem}

        .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;font-size:.88rem;font-weight:600;cursor:pointer;border:none;transition:var(--tr)}
        .btn-primary{background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;box-shadow:0 4px 12px rgba(236,72,153,.2)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.3)}
        .btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,.2)}
        .btn-success:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,.3)}
        .btn-ghost{background:var(--w);color:var(--g600);border:1.5px solid var(--g200)}
        .btn-ghost:hover{border-color:var(--pk300);color:var(--pk600);background:var(--pk50)}

        .fi{opacity:0;transform:translateY(25px);transition:opacity .6s ease,transform .6s ease}
        .fi.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}.sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}.topbar{padding:0 1rem}.content{padding:1rem}
            .detail-grid{grid-template-columns:1fr}
            .topbar-back span{display:none}.topbar-user .topbar-uname{display:none}
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
        <div class="topbar-left">
            <a href="orders.php" class="topbar-back"><i class="fa-solid fa-arrow-left"></i> <span>Pesanan</span></a>
            <div class="topbar-title"><i class="fa-solid fa-file-lines"></i> Detail Pesanan</div>
        </div>
        <div class="topbar-user">
            <div class="topbar-avatar"><?= strtoupper(substr($userNama, 0, 1)) ?></div>
            <span class="topbar-uname"><?= htmlspecialchars($userNama) ?></span>
            <a href="login.php?logout=1" class="topbar-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>

    <div class="content">
    <div class="content-in">

        <?php if ($flash): ?>
        <div class="flash-box flash-<?= ($flash['type'] ?? '') === 'success' ? 'ok' : 'warn' ?>">
            <i class="fa-solid <?= ($flash['type'] ?? '') === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <span><?= htmlspecialchars(is_array($flash) ? ($flash['message'] ?? '') : $flash) ?></span>
        </div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="pg-header fi">
            <div>
                <div class="pg-title"><i class="fa-solid fa-receipt"></i> <?= htmlspecialchars($order['kode_order']) ?></div>
                <div class="pg-sub"><?= date('d M Y, H:i', strtotime($order['tanggal_order'])) ?></div>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <span class="badge" style="background:<?= getStatusColor($order['status']) ?>20; color:<?= getStatusColor($order['status']) ?>">
                    <?= ucfirst($order['status']) ?>
                </span>
                <?php if(($order['status_pembayaran'] ?? '') === 'pending'): ?>
                <a href="payment.php?order=<?= $order['id_order'] ?>" class="btn btn-success"><i class="fa-solid fa-credit-card"></i> Cek Status Pembayaran </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="fi d1">
            <!-- INFO PESANAN -->
            <div class="card">
                <div class="card-hd"><i class="fa-solid fa-circle-info"></i> Info Pesanan</div>
                <div class="card-bd">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Metode Pengiriman</span>
                            <span class="detail-value"><?= $order['metode'] === 'antar-jemput' ? '🚗 Antar-Jemput' : '🏪 Datang Sendiri' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status Pembayaran</span>
                            <span class="detail-value" style="color:<?= ($order['status_pembayaran']??'') === 'dibayar' ? '#10b981' : '#f59e0b' ?>">
                                <?= ucfirst($order['status_pembayaran'] ?? 'pending') ?>
                            </span>
                        </div>
                        <?php if($order['metode'] === 'antar-jemput' && $order['alamat']): ?>
                        <div class="detail-item" style="grid-column:span 2">
                            <span class="detail-label">Alamat Jemput</span>
                            <span class="detail-value"><?= htmlspecialchars($order['alamat']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($order['catatan']): ?>
                        <div class="detail-item" style="grid-column:span 2">
                            <span class="detail-label">Catatan</span>
                            <span class="detail-value"><?= htmlspecialchars($order['catatan']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TOTAL BAYAR -->
            <div class="card" style="border-color:rgba(236,72,153,.15)">
                <div class="card-hd"><i class="fa-solid fa-wallet"></i> Pembayaran</div>
                <div class="card-bd" style="display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:2rem">
                    <div style="font-size:.85rem;color:var(--g500);margin-bottom:.5rem;">Total Tagihan</div>
                    <div style="font-size:2rem;font-weight:900;background:linear-gradient(135deg,var(--pk500),var(--pk700));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                        <?= formatRupiah($order['total_harga']) ?>
                    </div>
                    <?php if($order['metode_pembayaran']): ?>
                    <div style="margin-top:1rem;font-size:.85rem;color:var(--g500);">
                        Metode: <strong style="color:var(--g700)"><?= ucfirst($order['metode_pembayaran']) ?></strong>
                        <?php if($order['tanggal_bayar']): ?>
                         • Dibayar: <strong style="color:var(--g700)"><?= date('d M Y, H:i', strtotime($order['tanggal_bayar'])) ?></strong>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TRACKING -->
        <div class="card fi d2">
            <div class="card-hd"><i class="fa-solid fa-timeline"></i> Tracking Pesanan</div>
            <div class="card-bd">
                <?php if(empty($tracking)): ?>
                <div style="text-align:center;padding:1rem;color:var(--g400)">Belum ada update tracking.</div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach($tracking as $i => $t): ?>
                    <div class="tl-item <?= $i === array_key_last($tracking) ? 'active' : '' ?>">
                        <div class="tl-dot"><i class="fa-solid fa-check"></i></div>
                        <div class="tl-title"><?= htmlspecialchars($t['status']) ?></div>
                        <?php if(!empty($t['keterangan'])): ?>
                        <div class="tl-desc"><?= htmlspecialchars($t['keterangan']) ?></div>
                        <?php endif; ?>
                        <div class="tl-time"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i', strtotime($t['waktu_update'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ITEM PESANAN -->
        <div class="card fi d3">
            <div class="card-hd"><i class="fa-solid fa-box-open"></i> Item Pesanan (<?= count($orderItems) ?>)</div>
            <div class="card-bd">
                <?php foreach($orderItems as $item): ?>
                <div class="item-row">
                    <div>
                        <div class="item-name"><?= htmlspecialchars($item['nama_layanan']) ?></div>
                        <div class="item-detail"><?= $item['qty'] ?> <?= htmlspecialchars($item['satuan']) ?> × <?= formatRupiah($item['harga_satuan']) ?></div>
                    </div>
                    <div class="item-price"><?= formatRupiah($item['subtotal']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FOOTER ACTION -->
        <div style="display:flex;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:1rem;" class="fi d4">
            <a href="orders.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Kembali ke Pesanan</a>
            <a href="layanan.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Pesan Lagi</a>
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

// Fix grid mobile
if(window.innerWidth <= 768) {
    document.querySelector('.detail-grid')?.closest('.card')?.parentElement?.style.setProperty('grid-template-columns', '1fr');
}
window.addEventListener('resize', () => {
    const grid = document.querySelector('.fi.d1');
    if(grid) grid.style.gridTemplateColumns = window.innerWidth <= 768 ? '1fr' : '1fr 1fr';
});
</script>
</body>
</html>