<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/login.php');
    exit;
}

 $userId = $_SESSION['user_id'];
 $userNama = $_SESSION['userNama'] ?? 'User';
 $cartCount = getCartCount($userId);

// Fetch cart items
 $cartItems = [];
 $total = 0;
try {
    $cartItems = $db->query("
        SELECT c.id_cart, c.qty, c.catatan,
               l.id_layanan, l.nama_layanan, l.jenis, l.harga, l.satuan,
               (c.qty * l.harga) as subtotal
        FROM cart c
        JOIN layanan l ON c.id_layanan = l.id_layanan
        WHERE c.id_user = $userId
        ORDER BY c.added_at
    ")->fetch_all(MYSQLI_ASSOC);
    $total = array_sum(array_column($cartItems, 'subtotal'));
} catch (Exception $e) {}

 $pageTitle = 'Keranjang — CUCI.IN';
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
            --pk400:#f472b6;--pk500:#ec4899;--pk600:#db2777;--pk700:#be185d;--pk800:#9d174d;--pk900:#831843;
            --w:#fff;--g50:#f9fafb;--g100:#f3f4f6;--g200:#e5e7eb;--g300:#d1d5db;
            --g400:#9ca3af;--g500:#6b7280;--g600:#4b5563;--g700:#374151;--g800:#1f2937;--g900:#111827;
            --sidebar-w:250px;
            --sh:0 4px 20px rgba(236,72,153,.08);--shl:0 12px 40px rgba(236,72,153,.12);
            --shxl:0 20px 60px rgba(236,72,153,.16);--shpk:0 8px 30px rgba(236,72,153,.25);
            --tr:all .3s cubic-bezier(.4,0,.2,1);
        }
        body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--w);color:var(--g800);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden;line-height:1.6}
        a{text-decoration:none;color:inherit;transition:var(--tr)}
        button{cursor:pointer;border:none;font-family:inherit}
        img{max-width:100%;display:block}

        ::-webkit-scrollbar{width:7px}
        ::-webkit-scrollbar-track{background:var(--pk50)}
        ::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--pk400),var(--pk600));border-radius:10px}

        /* ===== BG ORBS ===== */
        .bg-orbs{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
        .bg-orbs .orb{position:absolute;border-radius:50%;filter:blur(120px);animation:orbF 20s ease-in-out infinite}
        .bg-orbs .orb:nth-child(1){width:700px;height:700px;background:radial-gradient(circle,rgba(236,72,153,.12),transparent 70%);top:-15%;left:-10%}
        .bg-orbs .orb:nth-child(2){width:600px;height:600px;background:radial-gradient(circle,rgba(244,114,182,.1),transparent 70%);bottom:-15%;right:-10%;animation-delay:-7s}
        .bg-orbs .orb:nth-child(3){width:500px;height:500px;background:radial-gradient(circle,rgba(251,207,232,.18),transparent 70%);top:40%;left:40%;animation-delay:-14s}
        .bg-orbs .orb:nth-child(4){width:400px;height:400px;background:radial-gradient(circle,rgba(236,72,153,.08),transparent 70%);top:10%;right:20%;animation-delay:-3s}
        @keyframes orbF{0%,100%{transform:translate(0,0) scale(1)}25%{transform:translate(50px,-30px) scale(1.1)}50%{transform:translate(-30px,40px) scale(.95)}75%{transform:translate(30px,20px) scale(1.05)}}

        .bg-grid{position:fixed;inset:0;z-index:0;pointer-events:none;
            background-image:linear-gradient(rgba(236,72,153,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(236,72,153,.025) 1px,transparent 1px);
            background-size:60px 60px}

        /* ===== PARTICLES ===== */
        .particle{position:fixed;border-radius:50%;pointer-events:none;z-index:0}
        .p-dot{width:4px;height:4px;background:var(--pk300);box-shadow:0 0 8px rgba(236,72,153,.4);animation:pF linear infinite}
        .p-ring{width:18px;height:18px;border:1.5px solid rgba(236,72,153,.12);animation:pF linear infinite}
        .p-heart{width:0;height:0;background:none;box-shadow:none;font-size:12px;color:rgba(236,72,153,.18);animation:pF linear infinite}
        @keyframes pF{0%{transform:translateY(100vh) rotate(0);opacity:0}10%{opacity:1}90%{opacity:1}100%{transform:translateY(-10vh) rotate(720deg);opacity:0}}

        /* ===== SIDEBAR DARK PINK ===== */
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

        /* ===== MAIN ===== */
        .main-ct{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;position:relative;z-index:1}
        .topbar{height:60px;background:rgba(255,255,255,.85);backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);border-bottom:1px solid rgba(236,72,153,.06);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:50;transition:var(--tr)}
        .topbar.scrolled{background:rgba(255,255,255,.97);box-shadow:0 4px 30px rgba(236,72,153,.06)}
        .topbar-title{font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .topbar-title i{color:var(--pk500)}
        .topbar-right{display:flex;align-items:center;gap:8px}
        .topbar-btn{color:var(--g500);font-size:.82rem;font-weight:600;padding:7px 14px;border-radius:10px;transition:var(--tr);display:flex;align-items:center;gap:6px}
        .topbar-btn:hover{background:var(--pk50);color:var(--pk700)}
        .topbar-btn.logout:hover{background:#fef2f2;color:#ef4444}

        /* ===== CONTENT ===== */
        .content{flex:1;padding:1.5rem 2rem 2rem}
        .content-in{max-width:1100px;margin:0 auto;position:relative;z-index:1}

        /* ===== EMPTY STATE ===== */
        .empty-state{text-align:center;padding:5rem 2rem;background:rgba(255,255,255,.9);backdrop-filter:blur(10px);border:1px solid rgba(236,72,153,.06);border-radius:20px;box-shadow:var(--shl)}
        .empty-icon{font-size:4rem;margin-bottom:1rem;opacity:.4}
        .empty-state h2{font-size:1.4rem;font-weight:800;color:var(--g900);margin-bottom:.5rem}
        .empty-state p{color:var(--g500);font-size:.95rem;margin-bottom:1.5rem}
        .empty-state .btn-w{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;border-radius:12px;font-weight:700;font-size:.95rem;background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;box-shadow:var(--shpk);transition:var(--tr)}
        .empty-state .btn-w:hover{transform:translateY(-3px);box-shadow:0 12px 35px rgba(236,72,153,.35)}

        /* ===== CART HEADER ===== */
        .cart-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
        .cart-hdr h1{font-size:1.6rem;font-weight:800;color:var(--g900)}
        .cart-hdr h1 i{color:var(--pk400);margin-right:8px}
        .cart-count{font-size:.9rem;color:var(--g500);font-weight:500;margin-top:.2rem}
        .cart-hdr-actions{display:flex;gap:.8rem}

        .btn-sm{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:.85rem;font-weight:600;transition:var(--tr);cursor:pointer}
        .btn-sm-danger{background:#fef2f2;color:#ef4444;border:1px solid #fecaca}
        .btn-sm-danger:hover{background:#ef4444;color:#fff;transform:translateY(-2px)}
        .btn-sm-ghost{background:var(--w);color:var(--g600);border:1px solid rgba(236,72,153,.08);box-shadow:var(--sh)}
        .btn-sm-ghost:hover{border-color:rgba(236,72,153,.2);background:var(--pk50);color:var(--pk600);transform:translateY(-2px);box-shadow:var(--shl)}

        /* ===== CART GRID ===== */
        .cart-grid{display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start}

        /* ===== CART ITEM ===== */
        .cart-items-wrap{display:flex;flex-direction:column;gap:1rem}
        .cart-item{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid rgba(236,72,153,.06);border-radius:16px;padding:1.5rem;display:grid;grid-template-columns:1fr auto;gap:1.2rem;align-items:center;transition:var(--tr);box-shadow:var(--sh)}
        .cart-item:hover{border-color:rgba(236,72,153,.12);box-shadow:var(--shxl);transform:translateY(-2px)}
        .ci-left{display:flex;align-items:center;gap:1rem}
        .ci-icon{width:52px;height:52px;min-width:52px;border-radius:14px;background:linear-gradient(135deg,var(--pk50),var(--pk100));display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--pk500);border:1px solid rgba(236,72,153,.08);transition:var(--tr)}
        .cart-item:hover .ci-icon{background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;border-color:transparent;box-shadow:0 8px 25px rgba(236,72,153,.3)}
        .ci-info h3{font-size:1rem;font-weight:700;color:var(--g800);margin-bottom:.2rem}
        .ci-meta{font-size:.8rem;color:var(--g500);display:flex;align-items:center;gap:8px}
        .ci-meta .badge{padding:3px 10px;border-radius:8px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
        .badge-jasa{background:rgba(124,58,237,.08);color:#7c3aed}
        .badge-self{background:rgba(22,163,74,.08);color:#16a34a}
        .ci-price{font-size:.85rem;color:var(--g500);margin-top:.2rem}
        .ci-right{text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:.8rem}
        .ci-qty{display:flex;align-items:center;gap:0;border:1px solid var(--g200);border-radius:10px;overflow:hidden}
        .qty-btn{width:36px;height:36px;background:var(--w);border:none;color:var(--g600);font-size:1.1rem;font-weight:700;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:var(--tr)}
        .qty-btn:hover{background:var(--pk50);color:var(--pk500)}
        .qty-val{width:44px;text-align:center;font-weight:700;font-size:.95rem;color:var(--g800);border-left:1px solid var(--g200);border-right:1px solid var(--g200);line-height:36px}
        .ci-subtotal{font-size:1.15rem;font-weight:800;background:linear-gradient(135deg,var(--pk500),var(--pk700));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .ci-remove{font-size:.78rem;color:var(--g400);cursor:pointer;padding:4px 8px;border-radius:6px;transition:var(--tr);background:none;border:none}
        .ci-remove:hover{color:#ef4444;background:#fef2f2}

        /* ===== CATATAN ===== */
        .catatan-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid rgba(236,72,153,.06);border-radius:16px;padding:1.3rem;margin-top:1rem;box-shadow:var(--sh);transition:var(--tr)}
        .catatan-card:hover{border-color:rgba(236,72,153,.12);box-shadow:var(--shl)}
        .catatan-card label{font-size:.85rem;font-weight:700;color:var(--g700);margin-bottom:.6rem;display:flex;align-items:center;gap:6px}
        .catatan-card textarea{width:100%;padding:10px 14px;border:1.5px solid var(--g200);border-radius:10px;font-size:.9rem;font-family:inherit;color:var(--g800);resize:vertical;min-height:80px;outline:none;transition:var(--tr);background:rgba(255,255,255,.6)}
        .catatan-card textarea:focus{border-color:var(--pk400);box-shadow:0 0 0 4px rgba(236,72,153,.08)}
        .catatan-card textarea::placeholder{color:var(--g300)}

        /* ===== SUMMARY ===== */
        .summary{position:sticky;top:80px;display:flex;flex-direction:column;gap:1rem}
        .summary-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid rgba(236,72,153,.06);border-radius:16px;overflow:hidden;box-shadow:var(--sh);transition:var(--tr)}
        .summary-card:hover{border-color:rgba(236,72,153,.12);box-shadow:var(--shl)}
        .summary-hd{padding:1.1rem 1.3rem;border-bottom:1px solid rgba(236,72,153,.06);font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .summary-hd i{color:var(--pk500)}
        .summary-body{padding:1.3rem}
        .summary-row{display:flex;justify-content:space-between;padding:.5rem 0;font-size:.88rem;color:var(--g500)}
        .summary-row.total{border-top:2px solid rgba(236,72,153,.1);margin-top:.5rem;padding-top:.8rem;font-size:1.1rem;font-weight:800;color:var(--g800)}
        .summary-row.total span:last-child{background:linear-gradient(135deg,var(--pk500),var(--pk700));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .summary-row.free span:last-child{color:#16a34a;font-weight:700}
        .summary-footer{padding:1.3rem;border-top:1px solid rgba(236,72,153,.06);display:flex;flex-direction:column;gap:.8rem}
        .btn-checkout{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border:none;border-radius:12px;font-size:1rem;font-weight:700;background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;box-shadow:0 4px 15px rgba(236,72,153,.3);transition:var(--tr);text-decoration:none;position:relative;overflow:hidden}
        .btn-checkout::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,var(--pk400),var(--pk600));opacity:0;transition:opacity .4s}
        .btn-checkout:hover::before{opacity:1}
        .btn-checkout span,.btn-checkout i{position:relative;z-index:1}
        .btn-checkout:hover{transform:translateY(-2px);box-shadow:var(--shpk)}
        .btn-checkout:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
        .btn-add-more{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:12px;border-radius:12px;font-size:.9rem;font-weight:600;background:var(--w);color:var(--pk600);border:1.5px solid rgba(236,72,153,.1);box-shadow:var(--sh);transition:var(--tr);text-decoration:none}
        .btn-add-more:hover{border-color:rgba(236,72,153,.2);background:var(--pk50);color:var(--pk600);transform:translateY(-2px);box-shadow:var(--shl)}

        /* ===== PROMO ===== */
        .promo-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid rgba(236,72,153,.06);border-radius:16px;padding:1.3rem;box-shadow:var(--sh);transition:var(--tr)}
        .promo-card:hover{border-color:rgba(236,72,153,.12);box-shadow:var(--shl)}
        .promo-card label{font-size:.85rem;font-weight:700;color:var(--g700);margin-bottom:.6rem;display:flex;align-items:center;gap:6px}
        .promo-row{display:flex;gap:.6rem}
        .promo-input{flex:1;padding:10px 14px;border:1.5px solid var(--g200);border-radius:10px;font-size:.9rem;font-family:inherit;color:var(--g800);outline:none;transition:var(--tr);background:rgba(255,255,255,.6)}
        .promo-input:focus{border-color:var(--pk400);box-shadow:0 0 0 4px rgba(236,72,153,.08)}
        .promo-btn{padding:10px 20px;border:none;border-radius:10px;font-size:.88rem;font-weight:700;background:linear-gradient(135deg,var(--pk500),var(--pk600));color:#fff;transition:var(--tr);white-space:nowrap}
        .promo-btn:hover{transform:translateY(-1px);box-shadow:var(--shpk)}

        /* ===== TOAST ===== */
        .toast-ct{position:fixed;top:80px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px}
        .toast{padding:14px 24px;border-radius:12px;font-size:.88rem;font-weight:600;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,.12);animation:tI .5s ease,tO .4s ease 2.6s forwards;display:flex;align-items:center;gap:10px}
        .toast.ok{background:linear-gradient(135deg,#10b981,#059669)}
        .toast.err{background:linear-gradient(135deg,#ef4444,#dc2626)}
        @keyframes tI{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}
        @keyframes tO{to{opacity:0;transform:translateX(100px)}}

        /* ===== ANIM ===== */
        .fi-anim{opacity:0;transform:translateY(25px);transition:opacity .6s cubic-bezier(.16,1,.3,1),transform .6s cubic-bezier(.16,1,.3,1)}
        .fi-anim.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}

        @media(max-width:1024px){.cart-grid{grid-template-columns:1fr}.summary{position:static}}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}
            .sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}
            .sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}
            .topbar{padding:0 1rem}
            .content{padding:1rem}
            .cart-hdr h1{font-size:1.3rem}
            .cart-item{padding:1rem;grid-template-columns:1fr;gap:.8rem}
            .ci-right{align-items:flex-start}
        }
    </style>
</head>
<body>

<!-- BG ORBS -->
<div class="bg-orbs"><div class="orb"></div><div class="orb"></div><div class="orb"></div><div class="orb"></div></div>
<div class="bg-grid"></div>

<!-- PARTICLES -->
<?php for($i=0;$i<14;$i++):
    $tp=['p-dot','p-ring','p-heart'];$t=$tp[array_rand($tp)];
    $sym=$t==='p-heart'?'♥':'';
?>
<div class="particle <?= $t ?>" style="left:<?= rand(0,100) ?>%;animation-duration:<?= rand(10,22) ?>s;animation-delay:<?= rand(0,15) ?>s;"><?= $sym ?></div>
<?php endfor; ?>

<!-- Mobile Toggle -->
<button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
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
        <a href="cart.php" class="sidebar-link active">
            <i class="fa-solid fa-cart-shopping"></i> Keranjang
            <?php if($cartCount > 0): ?><span class="badge-ct"><?= $cartCount ?></span><?php endif; ?>
        </a>
        <a href="dashboard.php#pesanan" class="sidebar-link"><i class="fa-solid fa-list-check"></i> Pesanan Saya</a>
        <div class="sidebar-label">Akun</div>
        <a href="<?= APP_URL ?>" class="sidebar-link"><i class="fa-solid fa-globe"></i> Beranda</a>
        <a href="logout.php" class="sidebar-link danger"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </nav>
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($userNama, 0, 1)) ?></div>
        <div>
            <div class="sidebar-uname"><?= htmlspecialchars($userNama) ?></div>
            <div class="sidebar-uemail"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div class="main-ct">
    <div class="topbar" id="topbar">
        <div class="topbar-title"><i class="fa-solid fa-cart-shopping"></i> Keranjang</div>
        <div class="topbar-right">
            <a href="layanan.php" class="topbar-btn"><i class="fa-solid fa-plus"></i> <span>Tambah</span></a>
            <a href="logout.php" class="topbar-btn logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div class="content">
    <div class="content-in">

        <?php if(empty($cartItems)): ?>
        <!-- EMPTY STATE -->
        <div class="empty-state fi-anim">
            <div class="empty-icon">🛒</div>
            <h2>Keranjangmu Masih Kosong</h2>
            <p>Yuk pilih layanan laundry yang kamu butuhkan!</p>
            <a href="layanan.php" class="btn-w"><i class="fa-solid fa-shirt"></i> Lihat Layanan</a>
        </div>

        <?php else: ?>
        <!-- CART HEADER -->
        <div class="cart-hdr fi-anim">
            <div>
                <h1><i class="fa-solid fa-cart-shopping"></i> Keranjang Belanja</h1>
                <p class="cart-count"><?= count($cartItems) ?> item dipilih</p>
            </div>
            <div class="cart-hdr-actions">
                <button class="btn-sm btn-sm-ghost" onclick="location.href='layanan.php'">
                    <i class="fa-solid fa-plus"></i> Tambah
                </button>
                <button class="btn-sm btn-sm-danger" onclick="clearCart()">
                    <i class="fa-solid fa-trash-can"></i> Hapus Semua
                </button>
            </div>
        </div>

        <!-- CART GRID -->
        <div class="cart-grid">

            <!-- ITEMS -->
            <div>
                <div class="cart-items-wrap">
                    <?php foreach($cartItems as $key => $item): ?>
                    <div class="cart-item fi-anim d<?= min(($key % 4) + 1, 4) ?>" id="item-<?= $item['id_cart'] ?>">
                        <div class="ci-left">
                            <div class="ci-icon"><i class="fa-solid fa-shirt"></i></div>
                            <div class="ci-info">
                                <h3><?= htmlspecialchars($item['nama_layanan']) ?></h3>
                                <div class="ci-meta">
                                    <?= formatRupiah($item['harga']) ?> / <?= htmlspecialchars($item['satuan']) ?>
                                    <span class="badge <?= $item['jenis']==='jasa' ? 'badge-jasa' : 'badge-self' ?>">
                                        <?= $item['jenis']=='jasa' ? 'Jasa' : 'Self Service' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="ci-right">
                            <div class="ci-subtotal" id="sub-<?= $item['id_cart'] ?>"><?= formatRupiah($item['subtotal']) ?></div>
                            <div class="ci-qty">
                                <button class="qty-btn" onclick="updateQty(<?= $item['id_cart'] ?>,'minus')">−</button>
                                <span class="qty-val" id="qty-<?= $item['id_cart'] ?>"><?= $item['qty'] ?></span>
                                <button class="qty-btn" onclick="updateQty(<?= $item['id_cart'] ?>,'plus')">+</button>
                            </div>
                            <button class="ci-remove" onclick="removeItem(<?= $item['id_cart'] ?>)">
                                <i class="fa-solid fa-trash-can"></i> Hapus
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- CATATAN -->
                <div class="catatan-card fi-anim d4">
                    <label><i class="fa-solid fa-pen-to-square" style="color:var(--pk400)"></i> Catatan untuk Laundry</label>
                    <textarea placeholder="Contoh: Pakaian putih harap pisahkan, ada noda di kemeja biru..."></textarea>
                </div>
            </div>

            <!-- SUMMARY -->
            <div class="summary fi-anim d2">
                <div class="summary-card">
                    <div class="summary-hd"><i class="fa-solid fa-receipt"></i> Ringkasan Pesanan</div>
                    <div class="summary-body">
                        <?php foreach($cartItems as $item): ?>
                        <div class="summary-row">
                            <span><?= htmlspecialchars($item['nama_layanan']) ?> ×<?= $item['qty'] ?></span>
                            <span><?= formatRupiah($item['subtotal']) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="summary-subtotal"><?= formatRupiah($total) ?></span>
                        </div>
                        <div class="summary-row free">
                            <span><i class="fa-solid fa-truck-fast"></i> Antar-Jemput</span>
                            <span>Gratis 🎉</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="summary-total"><?= formatRupiah($total) ?></span>
                        </div>
                    </div>
                    <div class="summary-footer">
                        <a href="checkout.php" class="btn-checkout" id="btnCheckout">
                            <i class="fa-solid fa-lock"></i> Lanjut ke Checkout
                        </a>
                        <a href="layanan.php" class="btn-add-more">
                            <i class="fa-solid fa-arrow-left"></i> Tambah Layanan Lagi
                        </a>
                    </div>
                </div>

                <!-- PROMO -->
                <div class="promo-card fi-anim d3">
                    <label><i class="fa-solid fa-ticket" style="color:var(--pk400)"></i> Kode Promo</label>
                    <div class="promo-row">
                        <input type="text" class="promo-input" placeholder="Masukkan kode">
                        <button class="promo-btn">Pakai</button>
                    </div>
                </div>
            </div>

        </div>
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
const obs=new IntersectionObserver(e=>{e.forEach(en=>{if(en.isIntersecting)en.target.classList.add('v')})},{threshold:.05});
document.querySelectorAll('.fi-anim').forEach(el=>obs.observe(el));

/* Topbar scroll effect */
const topbar=document.getElementById('topbar');
if(topbar)window.addEventListener('scroll',()=>{topbar.classList.toggle('scrolled',window.scrollY>20)});

function updateQty(id,action){
    const qtyEl=document.getElementById('qty-'+id);
    let qty=parseInt(qtyEl.textContent);
    if(action==='plus')qty++;else if(action==='minus'&&qty>1)qty--;

    fetch('<?= APP_URL ?>/api/cart.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'update',id_cart:id,qty:qty})
    }).then(r=>r.json()).then(d=>{
        if(d.success){
            qtyEl.textContent=qty;
            location.reload();
        }else{
            showToast(d.message||'Gagal update','err');
        }
    }).catch(()=>showToast('Error','err'));
}

function removeItem(id){
    fetch('<?= APP_URL ?>/api/cart.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'remove',id_cart:id})
    }).then(r=>r.json()).then(d=>{
        if(d.success){
            showToast('Item dihapus');setTimeout(()=>location.reload(),600);
        }else{showToast(d.message||'Gagal','err')}
    }).catch(()=>showToast('Error','err'));
}

function clearCart(){
    if(!confirm('Hapus semua item dari keranjang?'))return;
    fetch('<?= APP_URL ?>/api/cart.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'clear'})
    }).then(r=>r.json()).then(d=>{
        if(d.success){showToast('Keranjang dikosongkan');setTimeout(()=>location.reload(),600)}
        else{showToast(d.message||'Gagal','err')}
    }).catch(()=>showToast('Error','err'));
}

function showToast(msg,type='ok'){
    const ct=document.getElementById('toastCt'),t=document.createElement('div');
    t.className='toast '+type;
    const ic=type==='ok'?'fa-check-circle':'fa-exclamation-circle';
    t.innerHTML=`<i class="fa-solid ${ic}"></i> ${msg}`;
    ct.appendChild(t);setTimeout(()=>t.remove(),3200);
}
</script>
</body>
</html>