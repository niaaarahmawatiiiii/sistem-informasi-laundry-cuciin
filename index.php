<?php
 $pageTitle = 'CUCI.IN — Platform Laundry Digital #1';
require_once __DIR__ . '/includes/header.php';
// --- TAMBAHKAN KODE INI ---
 $dashboardLink = APP_URL . '/user/dashboard.php'; // Default ke User Dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboardLink = APP_URL . '/admin/dashboard.php'; // Redirect ke Admin Dashboard
}
 $layananCols = [];
if (isset($db) && $db) {
    try {
        $c = $db->query("SHOW COLUMNS FROM layanan")->fetch_all(MYSQLI_ASSOC);
        $layananCols = array_column($c, 'Field');
    } catch (Exception $e) {}
}
 $colHarga = in_array('harga_per_kg', $layananCols) ? 'harga_per_kg' : (in_array('harga', $layananCols) ? 'harga' : 'harga');
 $colDesc = in_array('deskripsi', $layananCols) ? 'deskripsi' : '';
 $colEstimasi = in_array('estimasi_waktu', $layananCols) ? 'estimasi_waktu' : (in_array('estimasi', $layananCols) ? 'estimasi' : '');
 $colSatuan = in_array('satuan', $layananCols) ? 'satuan' : 'kg';

 $layananResult = $db->query("SELECT * FROM layanan WHERE is_active=1 ORDER BY jenis, id_layanan LIMIT 8");
 $layanan = $layananResult ? $layananResult->fetch_all(MYSQLI_ASSOC) : [];

 $totalLayanan = 0;
if (isset($db) && $db) {
    try {
        $tr = $db->query("SELECT COUNT(*) as c FROM layanan WHERE is_active=1");
        if ($tr) $totalLayanan = (int)($tr->fetch_assoc()['c']);
    } catch (Exception $e) {}
}

function getIcon($n) {
    $n = strtolower($n);
    $m = ['cuci kering'=>'fa-shirt','cuci setrika'=>'fa-shirt','cuci lipat'=>'fa-shirt','setrika'=>'fa-temperature-arrow-up','dry clean'=>'fa-wind','dry cleaning'=>'fa-wind','express'=>'fa-bolt-lightning','kilat'=>'fa-bolt-lightning','sepatu'=>'fa-shoe-prints','karpet'=>'fa-rug','bed cover'=>'fa-bed','bedcover'=>'fa-bed','selimut'=>'fa-bed','sprei'=>'fa-bed','gorden'=>'fa-window-maximize','curtain'=>'fa-window-maximize','tas'=>'fa-bag-shopping','boneka'=>'fa-paw','self service'=>'fa-hands-bubbles','premium'=>'fa-crown','vip'=>'fa-crown','antar jemput'=>'fa-truck-fast','potong'=>'fa-scissors','jahit'=>'fa-scissors'];
    foreach ($m as $k => $v) { if (stripos($n, $k) !== false) return $v; }
    return 'fa-tag';
}
function getBadge($n) {
    $n = strtolower($n);
    if (stripos($n, 'express') !== false || stripos($n, 'kilat') !== false) return ['bg'=>'rgba(251,191,36,.12)','c'=>'#fbbf24','b'=>'rgba(251,191,36,.18)','l'=>'Express'];
    if (stripos($n, 'premium') !== false || stripos($n, 'vip') !== false) return ['bg'=>'rgba(167,139,250,.12)','c'=>'#a78bfa','b'=>'rgba(167,139,250,.18)','l'=>'Premium'];
    if (stripos($n, 'dry') !== false) return ['bg'=>'rgba(129,140,248,.12)','c'=>'#818cf8','b'=>'rgba(129,140,248,.18)','l'=>'Dry Clean'];
    if (stripos($n, 'sepatu') !== false) return ['bg'=>'rgba(244,114,182,.12)','c'=>'#f472b6','b'=>'rgba(244,114,182,.18)','l'=>'Special'];
    return ['bg'=>'rgba(249,168,212,.1)','c'=>'#f9a8d4','b'=>'rgba(249,168,212,.15)','l'=>'Regular'];
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --mr0:#fff5f7;--mr1:#ffedf1;--mr2:#ffe4ec;--mr3:#ffd9e4;--mr4:#ffcdd8;--mr5:#ffb8c9;
  --mr6:#f472b6;--mr7:#ec4899;--mr8:#db2777;--mr9:#be185d;
  --pk1:#be185d;--pk2:#db2777;--pk3:#ec4899;--pk4:#f472b6;--pk5:#c92060;
  --t1:#4a1028;--t2:#6b2040;--t3:#9b4068;--t4:#c48098;--t5:#e8c4d4;
  --bd1:rgba(190,24,93,.08);--bd2:rgba(190,24,93,.13);--bd3:rgba(190,24,93,.22);
  --card:linear-gradient(135deg,rgba(255,255,255,.75),rgba(255,240,243,.5));
  --r:16px;--rl:24px;--rx:32px;
  --sh:0 4px 20px rgba(190,24,93,.06);--shl:0 12px 40px rgba(190,24,93,.08);--shxl:0 20px 60px rgba(190,24,93,.1);
  --shpk:0 8px 30px rgba(236,72,153,.18);--glow:0 0 50px rgba(236,72,153,.06);
  --tr:all .4s cubic-bezier(.4,0,.2,1);
}
body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--mr0);color:var(--t1);overflow-x:hidden;line-height:1.6;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit;transition:var(--tr)}img{max-width:100%;display:block}button{cursor:pointer;border:none;font-family:inherit}
::selection{background:var(--mr8);color:#fff}
::-webkit-scrollbar{width:6px}::-webkit-scrollbar-track{background:var(--mr1)}::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--mr7),var(--mr9));border-radius:10px}

.bg-orbs{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.bg-orbs .orb{position:absolute;border-radius:50%;filter:blur(130px);animation:orbF 24s ease-in-out infinite}
.bg-orbs .orb:nth-child(1){width:750px;height:750px;background:radial-gradient(circle,rgba(249,168,212,.22),transparent 70%);top:-18%;left:-12%}
.bg-orbs .orb:nth-child(2){width:650px;height:650px;background:radial-gradient(circle,rgba(236,72,153,.12),transparent 70%);bottom:-18%;right:-12%;animation-delay:-8s}
.bg-orbs .orb:nth-child(3){width:500px;height:500px;background:radial-gradient(circle,rgba(251,191,36,.08),transparent 70%);top:45%;left:45%;animation-delay:-16s}
@keyframes orbF{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(45px,-28px) scale(1.08)}66%{transform:translate(-28px,35px) scale(.95)}}
.bg-grid{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(190,24,93,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(190,24,93,.03) 1px,transparent 1px);background-size:65px 65px}
.bg-noise{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.018;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
.bg-vig{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse at center,transparent 55%,rgba(255,215,228,.4) 100%)}

.ptc{position:fixed;border-radius:50%;pointer-events:none;z-index:0}
.ptc-dot{width:3px;height:3px;background:var(--pk1);box-shadow:0 0 8px rgba(190,24,93,.2);animation:ptcU linear infinite}
.ptc-ring{width:15px;height:15px;border:1px solid rgba(190,24,93,.15);background:none;box-shadow:none;animation:ptcU linear infinite}
.ptc-fa{width:0;height:0;background:none;box-shadow:none;font-size:10px;color:rgba(190,24,93,.18);animation:ptcU linear infinite}
@keyframes ptcU{0%{transform:translateY(105vh) rotate(0);opacity:0}8%{opacity:1}92%{opacity:1}100%{transform:translateY(-8vh) rotate(540deg);opacity:0}}

/* ===== LOADER — BEDAIN DARI BODY ===== */
.pg-loader{position:fixed;inset:0;z-index:99999;background:linear-gradient(160deg,#fff0f3,#fce4ec,#fff5f7);display:flex;flex-direction:column;align-items:center;justify-content:center;transition:opacity .7s ease,visibility .7s ease}
.pg-loader.gone{opacity:0;visibility:hidden;pointer-events:none}
.pg-loader.hide{display:none!important}
.ld-logo{width:76px;height:76px;animation:ldP 1s ease-in-out infinite;cursor:pointer}
.ld-logo img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 20px rgba(190,24,93,.15)) brightness(1)}
@keyframes ldP{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
.ld-bar{width:180px;height:3px;background:rgba(190,24,93,.1);border-radius:10px;margin-top:1.4rem;overflow:hidden}
.ld-bar-in{width:0;height:100%;background:linear-gradient(90deg,var(--pk2),var(--pk5));border-radius:10px;animation:ldFill 1.4s ease forwards}
@keyframes ldFill{0%{width:0}50%{width:65%}100%{width:100%}}
.ld-txt{margin-top:.7rem;font-size:.7rem;font-weight:700;color:var(--pk1);letter-spacing:4px;text-transform:uppercase}

.mw{position:relative;z-index:1}

/* ===== NAVBAR ===== */
header,.navbar{background:rgba(255,245,247,.85)!important;backdrop-filter:blur(24px) saturate(180%)!important;-webkit-backdrop-filter:blur(24px) saturate(180%)!important;border-bottom:1px solid var(--bd1)!important;transition:var(--tr)!important}
header.scrolled,.navbar.scrolled{background:rgba(255,245,247,.95)!important;box-shadow:0 4px 30px rgba(190,24,93,.08)!important}
header a,header span,header .nav-link{color:var(--t2)!important}header a:hover{color:var(--pk1)!important}
header .navbar-brand{color:var(--pk1)!important;font-weight:800!important}

/* ===== BTN ===== */
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;border-radius:12px;font-weight:600;font-size:.95rem;transition:var(--tr);position:relative;overflow:hidden}
.btn i{font-size:.9rem}
.btn-pk{background:linear-gradient(135deg,var(--pk3),var(--pk5));color:#fff;border:none;box-shadow:0 4px 15px rgba(236,72,153,.25)}
.btn-pk:hover{transform:translateY(-3px);box-shadow:var(--shpk)}
.btn-pk::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,var(--pk2),var(--pk4));opacity:0;transition:opacity .4s}.btn-pk:hover::before{opacity:1}
.btn-pk span,.btn-pk i{position:relative;z-index:1}
.btn-ol{background:transparent;color:var(--pk1);border:2px solid rgba(190,24,93,.2)}
.btn-ol:hover{background:var(--pk3);color:#fff;border-color:var(--pk3);transform:translateY(-3px);box-shadow:var(--shpk)}
.btn-w{background:rgba(255,255,255,.6);backdrop-filter:blur(8px);color:var(--t1);border:1.5px solid var(--bd2);box-shadow:var(--sh)}
.btn-w:hover{transform:translateY(-2px);box-shadow:var(--shl);border-color:rgba(190,24,93,.2);background:rgba(255,255,255,.8)}
.btn-ghost{background:rgba(190,24,93,.12);color:var(--pk1);border:1px solid rgba(190,24,93,.18)}
.btn-ghost:hover{background:rgba(190,24,93,.22);transform:translateY(-2px)}
.btn-glass{background:rgba(255,255,255,.65);backdrop-filter:blur(10px);color:var(--t1);border:1.5px solid rgba(190,24,93,.1)}
.btn-glass:hover{background:rgba(255,255,255,.85);transform:translateY(-2px)}
.btn-block{width:100%;justify-content:center}.btn-sm{padding:10px 20px;font-size:.85rem;border-radius:10px}.btn-lg{padding:16px 36px;font-size:1.05rem;border-radius:14px}

/* Override buttons inside dark contexts */
#heroSlider .btn-glass{background:rgba(255,255,255,.07);backdrop-filter:blur(10px);color:#fff;border:1.5px solid rgba(255,255,255,.1)}
#heroSlider .btn-glass:hover{background:rgba(255,255,255,.14)}
.cta-in .btn-w{background:rgba(255,255,255,.08);backdrop-filter:blur(8px);color:#fff;border:1.5px solid rgba(255,255,255,.1);box-shadow:0 4px 20px rgba(0,0,0,.25)}
.cta-in .btn-w:hover{background:rgba(255,255,255,.12);border-color:rgba(249,168,212,.25)}
.cta-in .btn-glass{background:rgba(255,255,255,.07);color:#fff;border:1.5px solid rgba(255,255,255,.1)}
.cta-in .btn-glass:hover{background:rgba(255,255,255,.14)}

/* ===== CARD ===== */
.gc{background:var(--card);backdrop-filter:blur(10px);border:1px solid var(--bd1);border-radius:var(--rl);transition:var(--tr);box-shadow:0 2px 12px rgba(190,24,93,.06);position:relative;overflow:hidden}
.gc::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(190,24,93,.04),transparent 50%);pointer-events:none;border-radius:inherit}
.gc:hover{transform:translateY(-6px);box-shadow:var(--shxl),var(--glow);border-color:var(--bd3)}

/* ===== SLIDER ===== */
.slider-wrap{padding-top:76px;position:relative}
.slider{width:100%;max-width:1360px;margin:0 auto;position:relative;overflow:hidden;border-radius:var(--rx);height:620px;border:none;box-shadow:var(--shxl)}
.slider::after{content:"";position:absolute;bottom:0;left:0;right:0;height:55%;background:linear-gradient(0deg,rgba(60,15,35,.6) 0%,rgba(60,15,35,.3) 40%,transparent 100%);z-index:2;pointer-events:none}
.slider img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transform:scale(1.05);transition:opacity 1.4s ease,transform 10s ease;z-index:1}
.slider img.on{opacity:1;transform:scale(1);z-index:2}
.sl-accent{position:absolute;bottom:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--mr6),var(--mr8),var(--mr9),var(--mr8),var(--mr6));z-index:6;border-radius:0 0 var(--rx) var(--rx)}
.sl-arrow{position:absolute;top:50%;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,.07);backdrop-filter:blur(10px);color:#fff;border:1.5px solid rgba(255,255,255,.1);border-radius:50%;font-size:17px;display:flex;align-items:center;justify-content:center;z-index:10;transition:var(--tr)}
.sl-arrow:hover{background:var(--mr9);border-color:var(--mr9);transform:translateY(-50%) scale(1.1);color:#fff;box-shadow:0 0 25px rgba(190,24,93,.5)}
.sl-arrow.prev{left:20px}.sl-arrow.next{right:20px}
.sl-content{position:absolute;bottom:55px;left:50px;z-index:5;max-width:600px}
.sl-content h2{font-size:2.8rem;font-weight:900;line-height:1.1;margin-bottom:.6rem;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.4)}
.sl-content h2 em{font-style:normal;color:#f9a8d4}
.sl-content p{font-size:1.05rem;color:rgba(255,255,255,.75);margin-bottom:1.2rem;text-shadow:0 1px 6px rgba(0,0,0,.3)}
.sl-dots{display:flex;justify-content:center;gap:8px;margin-top:16px;position:relative;z-index:5}
.dot{width:10px;height:10px;background:rgba(190,24,93,.15);border:1px solid rgba(190,24,93,.2);border-radius:20px;cursor:pointer;transition:var(--tr)}
.dot.on{width:36px;background:var(--pk2);border-color:var(--pk2);box-shadow:0 0 12px rgba(219,39,119,.4)}
.sl-deco{position:absolute;z-index:3;opacity:.05;color:var(--pk2);pointer-events:none}
.sl-deco:nth-child(1){top:15%;right:12%;font-size:3rem;animation:slD 8s ease-in-out infinite}
.sl-deco:nth-child(2){top:52%;right:8%;font-size:2rem;animation:slD 10s ease-in-out infinite reverse}
.sl-deco:nth-child(3){top:28%;right:22%;font-size:1.5rem;animation:slD 7s ease-in-out infinite 2s}
@keyframes slD{0%,100%{transform:translate(0,0) rotate(0)}33%{transform:translate(12px,-18px) rotate(8deg)}66%{transform:translate(-8px,12px) rotate(-4deg)}}

/* ===== SECTION ===== */
.sec{padding:5.5rem 2rem;position:relative}
.sec-in{max-width:1300px;margin:0 auto}
.sec-hd{text-align:center;margin-bottom:3.5rem}
.sec-tag{display:inline-flex;align-items:center;gap:8px;color:var(--pk1);font-weight:700;font-size:.8rem;text-transform:uppercase;letter-spacing:2.5px;margin-bottom:.8rem;padding:7px 18px;background:rgba(190,24,93,.1);border:1px solid rgba(190,24,93,.15);border-radius:30px}
.sec-tag i{font-size:.7rem}
.sec-t{font-size:2.7rem;font-weight:900;line-height:1.2;margin-bottom:.7rem;color:var(--t1)}
.sec-t em{font-style:normal;background:linear-gradient(135deg,var(--pk1),var(--pk3));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.sec-d{color:var(--t3);max-width:620px;margin:0 auto;font-size:1.02rem;line-height:1.75}

/* ===== WELCOME ===== */
.wel{padding:5rem 2rem}
.wel-in{max-width:1300px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:4.5rem;align-items:center}
.wel-vis{display:flex;justify-content:center;position:relative}
.wel-glow{position:absolute;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(249,168,212,.15),transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);animation:wG 4s ease-in-out infinite;pointer-events:none}
@keyframes wGlow{0%,100%{opacity:.5;transform:translate(-50%,-50%) scale(1)}50%{opacity:1;transform:translate(-50%,-50%) scale(1.1)}}
.wel-logo{position:relative;width:380px;height:380px;display:flex;align-items:center;justify-content:center}
.wel-logo::before{content:"";position:absolute;inset:-22px;border-radius:50%;background:conic-gradient(from 0deg,var(--mr8),var(--mr6),var(--pk2),var(--mr4),var(--mr8));animation:spin 12s linear infinite;opacity:.45}
.wel-logo::after{content:"";position:absolute;inset:0;border-radius:50%;background:linear-gradient(135deg,var(--mr2),var(--mr4))}
@keyframes spin{to{transform:rotate(360deg)}}
.wel-logo img{position:relative;z-index:2;height:300px;width:300px;object-fit:contain;filter:drop-shadow(0 8px 25px rgba(190,24,93,.12)) brightness(1);animation:lFloat 4s ease-in-out infinite}
@keyframes lFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
.wel-txt h1{font-size:3.2rem;font-weight:900;line-height:1.1;margin-bottom:.3rem;color:var(--t1)}
.wel-txt h1 .gt{background:linear-gradient(135deg,var(--t1) 30%,var(--pk1));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.wel-txt .bn{font-size:2.6rem;font-weight:900;background:linear-gradient(135deg,var(--pk1),var(--pk3));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:1rem}
.wel-txt p{color:var(--t3);font-size:1.05rem;line-height:1.8;margin-bottom:1.5rem;max-width:500px}
.wel-tags{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:2rem}
.wel-tag{display:flex;align-items:center;gap:6px;padding:5px 14px;background:rgba(190,24,93,.08);border:1px solid rgba(190,24,93,.13);border-radius:20px;font-size:.78rem;font-weight:600;color:var(--pk1)}
.wel-tag i{color:var(--pk2);font-size:.7rem}
.wel-acts{display:flex;gap:1rem;flex-wrap:wrap}

/* ===== STATS ===== */
.stats{padding:0 2rem;margin:-2rem auto 0;max-width:1260px;position:relative;z-index:2}
.stats-g{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
.st-c{background:var(--card);backdrop-filter:blur(14px);border:1px solid var(--bd1);border-radius:var(--r);padding:1.8rem 1rem;text-align:center;transition:var(--tr);position:relative;overflow:hidden;box-shadow:0 8px 30px rgba(190,24,93,.08)}
.st-c::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,var(--pk2),transparent);opacity:0;transition:opacity .4s}.st-c:hover::before{opacity:1}
.st-c:hover{transform:translateY(-5px);box-shadow:0 16px 50px rgba(190,24,93,.1),0 0 35px rgba(236,72,153,.08);border-color:var(--bd3)}
.st-ic{width:50px;height:50px;margin:0 auto .8rem;display:flex;align-items:center;justify-content:center;border-radius:14px;background:rgba(190,24,93,.1);color:var(--pk1);font-size:1.15rem;border:1px solid rgba(190,24,93,.1);transition:var(--tr)}
.st-c:hover .st-ic{background:linear-gradient(135deg,var(--pk3),var(--pk5));color:#fff;border-color:transparent;box-shadow:0 6px 18px rgba(236,72,153,.3)}
.st-num{font-size:2.1rem;font-weight:900;display:block;margin-bottom:.1rem;background:linear-gradient(135deg,var(--pk1),var(--pk3));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.st-lb{color:var(--t3);font-size:.85rem;font-weight:500}
.st-lb i{color:var(--pk2);margin-right:3px;font-size:.7rem}

/* ===== DIVIDER ===== */
.divider{display:flex;align-items:center;justify-content:center;gap:14px;padding:0 2rem;margin:0 auto;max-width:380px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,transparent,rgba(190,24,93,.18),transparent)}
.divider i{color:var(--pk1);font-size:.65rem;opacity:.4}

/* ===== WHY US ===== */
.why-g{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem}
.why-c{padding:2rem}
.why-c::after{content:"";position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--pk2),var(--pk5));transform:scaleX(0);transform-origin:left;transition:transform .5s}.why-c:hover::after{transform:scaleX(1)}
.why-ic{width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:16px;background:rgba(190,24,93,.1);color:var(--pk1);font-size:1.3rem;margin-bottom:1.1rem;border:1px solid rgba(190,24,93,.1);transition:var(--tr)}
.why-c:hover .why-ic{background:linear-gradient(135deg,var(--pk3),var(--pk5));color:#fff;border-color:transparent;box-shadow:0 8px 22px rgba(236,72,153,.3);transform:scale(1.05) rotate(-3deg)}
.why-c h3{font-size:1.08rem;font-weight:700;margin-bottom:.45rem;color:var(--t1)}
.why-c p{color:var(--t3);font-size:.88rem;line-height:1.65}

/* ===== ABOUT ===== */
.abt-in{display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center}
.abt-img{position:relative;border-radius:var(--rl);overflow:hidden;box-shadow:var(--shxl);border:1px solid var(--bd1)}
.abt-img img{width:100%;height:420px;object-fit:cover;transition:transform .6s}.abt-img:hover img{transform:scale(1.03)}
.abt-badge{position:absolute;top:20px;left:20px;background:linear-gradient(135deg,var(--mr7),var(--mr9));color:#fff;padding:10px 20px;border-radius:12px;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:8px;box-shadow:var(--shpk)}
.abt-txt h2{font-size:2.2rem;font-weight:900;color:var(--t1);margin-bottom:1rem;line-height:1.2}
.abt-txt h2 em{font-style:normal;background:linear-gradient(135deg,var(--pk1),var(--pk3));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.abt-txt>p{color:var(--t3);font-size:1rem;line-height:1.8;margin-bottom:1.5rem}
.abt-feats{display:flex;flex-direction:column;gap:.8rem;margin-bottom:2rem}
.abt-feat{display:flex;align-items:flex-start;gap:14px;padding:.9rem 0 .9rem 1rem;border-left:3px solid transparent;transition:var(--tr);border-radius:0 12px 12px 0}
.abt-feat:hover{border-left-color:var(--pk2);background:rgba(190,24,93,.04)}
.af-ic{width:42px;height:42px;min-width:42px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:rgba(190,24,93,.08);color:var(--pk1);font-size:1rem;border:1px solid rgba(190,24,93,.08);transition:var(--tr)}
.abt-feat:hover .af-ic{background:linear-gradient(135deg,var(--pk3),var(--pk5));color:#fff;border-color:transparent}
.abt-feat h4{font-size:.92rem;font-weight:700;color:var(--t1);margin-bottom:2px}
.abt-feat p{font-size:.82rem;color:var(--t3);line-height:1.5}

/* ===== STEPS ===== */
.steps-g{display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;position:relative}
.steps-g::before{content:"";position:absolute;top:52px;left:14%;right:14%;height:2px;background:linear-gradient(90deg,transparent,rgba(190,24,93,.18),rgba(190,24,93,.18),transparent);z-index:0}
.step-c{text-align:center;padding:2rem 1.2rem;position:relative;z-index:1}
.step-n{position:absolute;top:10px;right:14px;font-size:.7rem;font-weight:800;color:var(--t4)}
.step-ic{width:60px;height:60px;margin:0 auto 1.1rem;display:flex;align-items:center;justify-content:center;border-radius:18px;background:linear-gradient(135deg,var(--mr7),var(--mr9));color:#fff;font-size:1.3rem;box-shadow:0 8px 22px rgba(236,72,153,.25);position:relative;transition:var(--tr)}
.step-c:hover .step-ic{transform:scale(1.08);box-shadow:0 12px 32px rgba(190,24,93,.4)}
.step-ic::after{content:"";position:absolute;inset:-4px;border-radius:22px;border:2px solid rgba(190,24,93,.1)}
.step-c h3{font-size:1.02rem;font-weight:700;margin-bottom:.35rem;color:var(--t1)}
.step-c p{color:var(--t3);font-size:.85rem;line-height:1.6}

/* ===== SERVICES ===== */
.srv-g{display:grid;grid-template-columns:repeat(4,1fr);gap:1.2rem}
.srv-c{padding:0;position:relative;overflow:hidden}
.srv-c::after{content:"";position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--pk2),var(--pk5));transform:scaleX(0);transform-origin:left;transition:transform .5s}.srv-c:hover::after{transform:scaleX(1)}
.srv-top{padding:1.4rem 1.4rem 0;display:flex;align-items:flex-start;gap:.9rem}
.srv-ic{width:48px;height:48px;min-width:48px;display:flex;align-items:center;justify-content:center;border-radius:14px;background:rgba(190,24,93,.1);color:var(--pk1);font-size:1.15rem;border:1px solid rgba(190,24,93,.08);transition:var(--tr)}
.srv-c:hover .srv-ic{background:linear-gradient(135deg,var(--pk3),var(--pk5));color:#fff;border-color:transparent;box-shadow:0 6px 18px rgba(236,72,153,.25);transform:rotate(-3deg) scale(1.05)}
.srv-badge{display:inline-flex;padding:3px 9px;border-radius:8px;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem}
.srv-nm{font-weight:800;font-size:1rem;margin-bottom:.3rem;color:var(--t1);line-height:1.3}
.srv-ds{color:var(--t3);font-size:.8rem;line-height:1.55;margin-bottom:.8rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;padding:0 .2rem}
.srv-foot{padding:.8rem 1.4rem 1.4rem;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--bd1)}
.srv-price{font-weight:900;font-size:1.1rem;color:var(--pk1)}
.srv-price i{font-size:.75rem;margin-right:3px}
.srv-meta{display:flex;align-items:center;gap:8px;font-size:.7rem;color:var(--t4)}
.srv-meta i{font-size:.65rem}
.srv-btn{padding:.7rem 1.4rem 1.4rem}
.srv-btn .btn{width:100%}

/* ===== SHOWCASE ===== */
.show-g{display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:200px 200px;gap:1rem}
.sh-item{border-radius:var(--rl);overflow:hidden;position:relative;box-shadow:var(--sh);border:1px solid var(--bd1)}
.sh-item img{width:100%;height:100%;object-fit:cover;transition:transform .7s}.sh-item:hover img{transform:scale(1.1)}
.sh-item:nth-child(1){grid-column:span 2;grid-row:span 2}
.sh-ov{position:absolute;inset:0;background:linear-gradient(0deg,rgba(60,15,35,.75) 0%,transparent 60%);display:flex;flex-direction:column;align-items:flex-start;padding:1.2rem;opacity:0;transition:opacity .4s}
.sh-item:hover .sh-ov{opacity:1}
.sh-dot{width:7px;height:7px;border-radius:50%;background:var(--mr9);box-shadow:0 0 8px rgba(190,24,93,.5);margin-bottom:5px}
.sh-ov span{color:#fff;font-weight:700;font-size:.92rem}

/* ===== TESTIMONIALS ===== */
.tst-g{display:grid;grid-template-columns:repeat(2,1fr);gap:1.2rem}
.tst-c{padding:1.8rem;position:relative}
.tst-c::before{content:'\201C';position:absolute;top:10px;right:18px;font-size:3.5rem;font-weight:900;color:rgba(190,24,93,.1);line-height:1;font-family:Georgia,serif}
.tst-str{color:#fbbf24;font-size:.78rem;margin-bottom:.7rem;display:flex;gap:2px}
.tst-tx{color:var(--t2);font-size:.9rem;line-height:1.7;margin-bottom:1rem;font-style:italic}
.tst-au{display:flex;align-items:center;gap:10px}
.tst-av{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--mr7),var(--mr9));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0;box-shadow:0 4px 12px rgba(236,72,153,.2)}
.tst-nm{font-weight:700;font-size:.85rem;color:var(--t1)}
.tst-rl{color:var(--t4);font-size:.75rem;display:flex;align-items:center;gap:4px}
.tst-rl i{font-size:.6rem}

/* ===== FEATURES GRID ===== */
.feat-g{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem}
.feat-c{padding:1.6rem;display:flex;align-items:flex-start;gap:1rem;background:var(--card);backdrop-filter:blur(10px);border:1px solid var(--bd1);border-radius:var(--r);transition:var(--tr);box-shadow:0 2px 10px rgba(190,24,93,.05)}
.feat-c:hover{border-color:var(--bd3);transform:translateY(-4px);box-shadow:0 8px 25px rgba(190,24,93,.08)}
.feat-ic{width:46px;height:46px;min-width:46px;display:flex;align-items:center;justify-content:center;border-radius:13px;background:rgba(190,24,93,.1);color:var(--pk1);font-size:1.05rem;border:1px solid rgba(190,24,93,.08);transition:var(--tr)}
.feat-c:hover .feat-ic{background:linear-gradient(135deg,var(--pk3),var(--pk5));color:#fff;border-color:transparent}
.feat-c h4{font-size:.92rem;font-weight:700;color:var(--t1);margin-bottom:2px}
.feat-c p{font-size:.8rem;color:var(--t3);line-height:1.55}

/* ===== FAQ ===== */
.faq-list{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:10px}
.faq-item{background:var(--card);border:1px solid var(--bd1);border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(190,24,93,.05);transition:var(--tr)}
.faq-item:hover{border-color:var(--bd3)}
.faq-item.open{border-color:rgba(190,24,93,.22);box-shadow:0 4px 20px rgba(190,24,93,.08),0 0 25px rgba(236,72,153,.06)}
.faq-q{padding:1.2rem 1.5rem;display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-weight:700;font-size:.92rem;color:var(--t1);gap:12px;transition:var(--tr)}
.faq-q:hover{color:var(--pk1)}
.faq-q i{color:var(--pk2);font-size:.75rem;transition:transform .3s;min-width:20px;text-align:center}
.faq-item.open .faq-q i{transform:rotate(180deg);color:var(--pk1)}
.faq-a{max-height:0;overflow:hidden;transition:max-height .4s ease}
.faq-item.open .faq-a{max-height:220px}
.faq-a-inner{padding:0 1.5rem 1.2rem;color:var(--t3);font-size:.88rem;line-height:1.75}

/* ===== TRUST BAR ===== */
.trust-bar{padding:1.5rem 2rem;border-top:1px solid var(--bd1);border-bottom:1px solid var(--bd1);background:linear-gradient(135deg,var(--mr1),var(--mr3))}
.trust-in{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:center;gap:2.5rem;flex-wrap:wrap}
.trust-item{display:flex;align-items:center;gap:8px;color:var(--t3);font-size:.82rem;font-weight:600}
.trust-item i{font-size:1rem;color:var(--pk2)}

/* ===== CTA ===== */
.cta-in{max-width:960px;margin:0 auto;text-align:center;padding:4.5rem 3rem;border-radius:var(--rx);background:linear-gradient(135deg,#4a1225 0%,#831843 35%,#be185d 100%);color:#fff;position:relative;overflow:hidden;box-shadow:0 24px 80px rgba(131,24,67,.3)}
.cta-in::before{content:'';position:absolute;top:-80px;right:-80px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.06)}
.cta-in::after{content:'';position:absolute;bottom:-60px;left:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.04)}
.cta-in h2{font-size:2.3rem;font-weight:900;margin-bottom:.8rem;position:relative;z-index:1}
.cta-in h2 em{font-style:normal;color:#f9a8d4!important}
.cta-in>p{color:rgba(255,255,255,.75);font-size:1.02rem;margin-bottom:2rem;position:relative;z-index:1;line-height:1.7}
.cta-acts{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;position:relative;z-index:1}

/* ===== ANIM ===== */
.fi{opacity:0;transform:translateY(45px);transition:opacity .8s cubic-bezier(.16,1,.3,1),transform .8s cubic-bezier(.16,1,.3,1)}
.fi.v{opacity:1;transform:translateY(0)}
.d1{transition-delay:.08s}.d2{transition-delay:.16s}.d3{transition-delay:.24s}.d4{transition-delay:.32s}.d5{transition-delay:.4s}.d6{transition-delay:.48s}.d7{transition-delay:.56s}.d8{transition-delay:.64s}

.toast-ct{position:fixed;top:100px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px}
.toast{padding:14px 24px;border-radius:14px;font-size:.88rem;font-weight:600;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,.12);animation:tIn .5s cubic-bezier(.16,1,.3,1),tOut .4s ease 2.6s forwards;display:flex;align-items:center;gap:10px}
.toast.ok{background:linear-gradient(135deg,#10b981,#059669)}.toast.err{background:linear-gradient(135deg,#ef4444,#dc2626)}
@keyframes tIn{from{opacity:0;transform:translateX(100px) scale(.9)}to{opacity:1;transform:translateX(0) scale(1)}}
@keyframes tOut{to{opacity:0;transform:translateX(100px) scale(.9)}}

footer{background:var(--mr1)!important;color:var(--t3)!important;border-top:1px solid var(--bd1)!important}
footer a{color:var(--t3)!important}footer a:hover{color:var(--pk1)!important}
footer h5{color:var(--t2)!important}
footer .text-muted{color:var(--t4)!important}footer hr{border-color:var(--bd1)!important}

@media(max-width:1024px){
  .slider{height:460px}.sl-content h2{font-size:2.2rem}
  .wel-in{gap:3rem}.wel-txt h1{font-size:2.6rem}.wel-txt .bn{font-size:2rem}
  .srv-g{grid-template-columns:repeat(2,1fr)}.tst-g{grid-template-columns:1fr}.why-g,.feat-g{grid-template-columns:repeat(2,1fr)}
  .steps-g{grid-template-columns:repeat(2,1fr)}.steps-g::before{display:none}
  .stats-g{grid-template-columns:repeat(2,1fr)}.abt-in{gap:3rem}
  .show-g{grid-template-columns:repeat(2,1fr);grid-template-rows:180px 180px 180px}
  .show-g .sh-item:nth-child(1){grid-column:span 2;grid-row:span 1}
}
@media(max-width:768px){
  .brand-logo{top:76px;left:12px;width:42px;height:42px}
  .slider{height:360px;border-radius:0}.sl-content{bottom:25px;left:20px;right:20px}.sl-content h2{font-size:1.6rem}
  .sl-arrow{width:38px;height:38px;font-size:13px}
  .wel-in{grid-template-columns:1fr;text-align:center;padding:4rem 1.5rem}
  .wel-logo{width:260px;height:260px}.wel-logo img{height:200px;width:200px}
  .wel-txt p{margin-left:auto;margin-right:auto}.wel-acts{justify-content:center}.wel-tags{justify-content:center}
  .sec{padding:4rem 1.2rem}.sec-t{font-size:2rem}
  .srv-g,.why-g,.feat-g{grid-template-columns:1fr}.steps-g{grid-template-columns:1fr}
  .stats-g{grid-template-columns:1fr 1fr}.abt-in{grid-template-columns:1fr}.abt-img img{height:280px}
  .show-g{grid-template-columns:1fr 1fr;grid-template-rows:150px 150px 150px}
  .show-g .sh-item:nth-child(1){grid-column:span 2}
  .cta-in{padding:3rem 1.5rem}.cta-in h2{font-size:1.7rem}
  .trust-in{gap:1.5rem}.trust-item{font-size:.75rem}
}
@media(max-width:480px){
  .wel-txt h1{font-size:2rem}.wel-txt .bn{font-size:1.6rem}
  .stats-g{grid-template-columns:1fr}.slider{height:280px}.sl-content h2{font-size:1.35rem}
  .show-g{grid-template-columns:1fr;grid-template-rows:200px 200px 200px 200px}
  .show-g .sh-item:nth-child(1){grid-column:span 1}
}

/* ===== PACKAGES ===== */
.pkg-g{display:grid;grid-template-columns:repeat(4,1fr);gap:1.2rem;}
.pkg-c{padding:2rem 1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;}
.pkg-ic{width:64px;height:64px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,var(--mr7),var(--mr9));color:#fff;font-size:1.5rem;margin-bottom:1rem;box-shadow:0 8px 20px rgba(236,72,153,.3);}
.pkg-nm{font-size:1.2rem;font-weight:800;color:var(--t1);margin-bottom:.5rem;}
.pkg-pr{font-size:1.8rem;font-weight:900;color:var(--pk1);margin-bottom:.5rem;line-height:1.2;}
.pkg-pr span{font-size:.8rem;font-weight:600;color:var(--t4);display:block;margin-top:4px;}
.pkg-ds{font-size:.85rem;color:var(--t3);margin-bottom:1.5rem;min-height:40px;}
.pkg-c .btn{margin-top:auto;width:100%;}

.pkg-modal{position:fixed;inset:0;background:rgba(74,16,40,.6);backdrop-filter:blur(8px);z-index:99999;display:none;align-items:center;justify-content:center;padding:1rem;opacity:0;transition:opacity .3s ease;}
.pkg-modal.show{display:flex;opacity:1;}
.pkg-modal-c{background:var(--mr0);border-radius:var(--rl);max-width:480px;width:100%;padding:2.5rem;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.9);transition:transform .3s ease;border:1px solid var(--bd2);}
.pkg-modal.show .pkg-modal-c{transform:scale(1);}
.pkg-close{position:absolute;top:1rem;right:1rem;width:36px;height:36px;border-radius:50%;background:rgba(190,24,93,.1);color:var(--pk1);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:var(--tr);}
.pkg-close:hover{background:var(--pk3);color:#fff;}
.pkg-m-hd{text-align:center;margin-bottom:1.5rem;}
.pkg-m-ic{width:56px;height:56px;background:linear-gradient(135deg,var(--mr7),var(--mr9));color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin:0 auto 1rem;box-shadow:0 8px 20px rgba(236,72,153,.3);}
.pkg-m-nm{font-size:1.5rem;font-weight:900;color:var(--t1);}
.pkg-m-pr{font-size:1.1rem;font-weight:700;color:var(--pk1);}
.pkg-m-list{list-style:none;padding:0;margin-bottom:1.5rem;}
.pkg-m-list li{display:flex;align-items:center;gap:10px;padding:.8rem 0;border-bottom:1px solid var(--bd1);color:var(--t2);font-size:.92rem;}
.pkg-m-list li:last-child{border-bottom:none;}
.pkg-m-list li i{color:var(--pk2);width:20px;text-align:center;}

@media(max-width:1024px){.pkg-g{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){.pkg-g{grid-template-columns:1fr;}.pkg-modal-c{padding:1.5rem;}}
</style>

<!-- LOADER -->
<div class="pg-loader" id="pgLoader">
  <a href="<?= $_SERVER['PHP_SELF'] ?>" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none">
    <div class="ld-logo"><img src="img/logo.png" alt="CUCI.IN"></div>
    <div class="ld-bar"><div class="ld-bar-in"></div></div>
    <div class="ld-txt">Memuat</div>
  </a>
</div>

<!-- BG -->
<div class="bg-orbs"><div class="orb"></div><div class="orb"></div><div class="orb"></div></div>
<div class="bg-grid"></div>
<div class="bg-noise"></div>
<div class="bg-vig"></div>

<!-- PARTICLES -->
<?php
 $ptcFa=['fa-droplet','fa-shirt','fa-star','fa-heart','fa-wind','fa-leaf','fa-sparkles','fa-bolt','fa-gem'];
for($i=0;$i<16;$i++):
  $tp=['ptc-dot','ptc-ring','ptc-fa'];$t=$tp[array_rand($tp)];
  $sym=$t==='ptc-fa'?'<i class="fa-solid '.$ptcFa[array_rand($ptcFa)].'"></i>':'';
?>
<div class="ptc <?= $t ?>" style="left:<?= rand(2,98) ?>%;animation-duration:<?= rand(12,24) ?>s;animation-delay:-<?= rand(0,18) ?>s;"><?= $sym ?></div>
<?php endfor; ?>

<div class="toast-ct" id="toastCt"></div>

<div class="mw">

<!-- SLIDER -->
<div class="slider-wrap fi" id="heroWrap">
  <div class="slider" id="heroSlider">
    <img src="<?= $base ?>img/foto1.jpeg" class="on" alt="Laundry Service">
    <img src="<?= $base ?>img/foto2.jpeg" alt="Clean Clothes">
    <img src="<?= $base ?>img/foto3.jpeg" alt="Tracking">
    <button class="sl-arrow prev" onclick="chSlide(-1)"><i class="fa-solid fa-chevron-left"></i></button>
    <button class="sl-arrow next" onclick="chSlide(1)"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="sl-content">
      <h2>Laundry <em>Clean & Fresh</em><br>Tanpa Ribet</h2>
      <p><i class="fa-solid fa-sparkles"></i> Antar jemput gratis, tracking real-time, hasil bersih maksimal.</p>
      <div class="sl-acts">
        <?php if(isLoggedIn()): ?>
          <a href="<?= APP_URL ?>/user/layanan.php" class="btn btn-pk btn-lg"><span><i class="fa-solid fa-basket-shopping"></i> Pesan Sekarang</span></a>
         <a href="<?= $dashboardLink ?>" class="btn btn-glass btn-lg"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <?php else: ?>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-pk btn-lg"><span><i class="fa-solid fa-user-plus"></i> Daftar Gratis</span></a>
          <a href="<?= APP_URL ?>/user/login.php" class="btn btn-glass btn-lg"><i class="fa-solid fa-right-to-bracket"></i> Masuk</a>
        <?php endif; ?>
      </div>
    </div>
    <i class="fa-solid fa-shirt sl-deco"></i>
    <i class="fa-solid fa-droplet sl-deco"></i>
    <i class="fa-solid fa-wind sl-deco"></i>
    <div class="sl-accent"></div>
  </div>
  <div class="sl-dots">
    <div class="dot on" onclick="goSlide(0)"></div>
    <div class="dot" onclick="goSlide(1)"></div>
    <div class="dot" onclick="goSlide(2)"></div>
  </div>
</div>

<!-- WELCOME -->
<section class="wel">
  <div class="wel-in">
   <div class="wel-vis fi d1">
  <div class="wel-glow"></div>
  <div class="wel-logo">
    <img src="img/logo.png" alt="CUCI.IN">
  </div>
</div>
    <div class="wel-txt">
      <h1 class="fi d2"><span class="gt">SELAMAT DATANG</span></h1>
      <div class="bn fi d3">DI CUCI.IN</div>
      <p class="fi d4"><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--pk2)"></i> CUCI.IN hadir sebagai solusi laundry digital terdepan di Indonesia. Kami menggabungkan teknologi modern dengan pelayanan profesional untuk memberikan pengalaman laundry yang lebih mudah, cepat, dan terpercaya.</p>
      <div class="wel-tags fi d5">
        <span class="wel-tag"><i class="fa-solid fa-truck-fast"></i> Antar Jemput</span>
        <span class="wel-tag"><i class="fa-solid fa-bolt"></i> Express 2 Jam</span>
        <span class="wel-tag"><i class="fa-solid fa-map-pin"></i> Real-time Tracking</span>
        <span class="wel-tag"><i class="fa-solid fa-shield-halved"></i> Garansi Cuci Ulang</span>
        <span class="wel-tag"><i class="fa-solid fa-leaf"></i> Deterjen Premium</span>
        <span class="wel-tag"><i class="fa-solid fa-hand-holding-dollar"></i> Harga Transparan</span>
      </div>
      <div class="wel-acts fi d6">
        <?php if(isLoggedIn()): ?>
          <a href="<?= $dashboardLink ?>" class="btn btn-pk"><span><i class="fa-solid fa-gauge-high"></i> Dashboard Saya</span></a>
          <a href="<?= APP_URL ?>/user/layanan.php" class="btn btn-ol"><i class="fa-solid fa-shirt"></i> Lihat Layanan</a>
        <?php else: ?>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-pk"><span><i class="fa-solid fa-user-plus"></i> Daftar Gratis</span></a>
          <a href="<?= APP_URL ?>/user/login.php" class="btn btn-ol"><i class="fa-solid fa-right-to-bracket"></i> Masuk</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats"><div class="stats-g">
  <div class="st-c fi d1"><div class="st-ic"><i class="fa-solid fa-users"></i></div><span class="st-num" data-target="5000">0</span><div class="st-lb"><i class="fa-solid fa-chart-line"></i> Pelanggan Aktif</div></div>
  <div class="st-c fi d2"><div class="st-ic"><i class="fa-solid fa-star"></i></div><span class="st-num" data-target="99" data-suffix="%">0</span><div class="st-lb"><i class="fa-solid fa-face-smile"></i> Kepuasan</div></div>
  <div class="st-c fi d3"><div class="st-ic"><i class="fa-solid fa-bolt"></i></div><span class="st-num" data-target="2" data-suffix=" Jam">0</span><div class="st-lb"><i class="fa-solid fa-clock"></i> Express</div></div>
  <div class="st-c fi d4"><div class="st-ic"><i class="fa-solid fa-layer-group"></i></div><span class="st-num" data-target="<?= $totalLayanan ?>" data-suffix="+">0</span><div class="st-lb"><i class="fa-solid fa-list"></i> Layanan</div></div>
</div></div>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- WHY US -->
<section class="sec"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-crown"></i> Mengapa CUCI.IN</div>
    <h2 class="sec-t fi d1">Alasan <em>5.000+</em> Pelanggan<br>Mempercayai Kami</h2>
    <p class="sec-d fi d2">Kami tidak hanya mencuci pakaian — kami memberikan ketenangan pikiran dan waktu berharga kembali untuk Anda.</p>
  </div>
  <div class="why-g">
    <?php $why=[['fa-truck-fast','Antar Jemput Gratis','Kurir datang ke lokasi Anda tepat waktu sesuai jadwal yang ditentukan.'],['fa-clock','Selesai 2 Jam','Express menyelesaikan pesanan tanpa mengorbankan kualitas sedikitpun.'],['fa-map-location-dot','Tracking Real-time','Pantau cucian dari penjemputan hingga pengantaran via dashboard.'],['fa-shield-halved','Garansi Kepuasan','Tidak puas? Kami cuci ulang gratis tanpa syarat tambahan.'],['fa-leaf','Deterjen Premium','Ramah lingkungan, aman untuk semua jenis kain dan kulit sensitif.'],['fa-hand-holding-dollar','Harga Transparan','Harga yang dilihat di awal adalah harga final. Zero biaya tersembunyi.']];
    foreach($why as $i=>$w): ?>
    <div class="gc why-c fi d<?= ($i%3)+1 ?>"><div class="why-ic"><i class="fa-solid <?= $w[0] ?>"></i></div><h3><?= $w[1] ?></h3><p><?= $w[2] ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- ABOUT -->
<section class="sec" style="background:linear-gradient(180deg,var(--mr2),var(--mr0))"><div class="sec-in">
  <div class="abt-in">
    <div class="abt-img fi d1"><img src="img/foto2.jpeg" alt="Laundry Modern"><div class="abt-badge"><i class="fa-solid fa-award"></i> Trusted Since 2023</div></div>
    <div class="abt-txt">
      <div class="sec-tag fi d2"><i class="fa-solid fa-building"></i> Tentang Kami</div>
      <h2 class="fi d3">Lebih dari Sekedar <em>Laundry Biasa</em></h2>
      <p class="fi d3"><i class="fa-solid fa-quote-left" style="color:var(--pk2);margin-right:6px"></i>CUCI.IN didirikan dengan misi sederhana: menghilangkan beban cucian dari daftar harian Anda. Berawal dari satu outlet kecil, kini melayani ribuan pelanggan dengan standar kualitas konsisten.</p>
      <div class="abt-feats fi d4">
        <div class="abt-feat"><div class="af-ic"><i class="fa-solid fa-microscope"></i></div><div><h4>Quality Control 3 Tahap</h4><p>Setiap pakaian melewati pemeriksaan kualitas ketat sebelum dikembalikan.</p></div></div>
        <div class="abt-feat"><div class="af-ic"><i class="fa-solid fa-user-tie"></i></div><div><h4>Tim Profesional Terlatih</h4><p>Staf dilatih khusus menangani berbagai jenis kain.</p></div></div>
        <div class="abt-feat"><div class="af-ic"><i class="fa-solid fa-recycle"></i></div><div><h4>Ramah Lingkungan</h4><p>Mesin hemat energi dan deterjen biodegradable.</p></div></div>
        <div class="abt-feat"><div class="af-ic"><i class="fa-solid fa-headset"></i></div><div><h4>Support 24/7</h4><p>Siap membantu kapan saja via chat, telepon, atau email.</p></div></div>
      </div>
      <a href="<?= APP_URL ?>/user/layanan.php" class="btn btn-pk fi d5"><span><i class="fa-solid fa-arrow-right"></i> Coba Layanan Kami</span></a>
    </div>
  </div>
</div></section>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- STEPS -->
<section class="sec"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-route"></i> Cara Kerja</div>
    <h2 class="sec-t fi d1">Hanya <em>4 Langkah</em> Mudah</h2>
    <p class="sec-d fi d2">Proses laundry digital simpel, cepat, dan transparan dari awal hingga akhir.</p>
  </div>
  <div class="steps-g">
    <?php $steps=[['fa-shirt','Pilih Layanan','Jelajahi katalog dan pilih layanan yang sesuai kebutuhan.'],['fa-calendar-check','Jadwalkan','Tentukan waktu dan lokasi penjemputan yang nyaman.'],['fa-credit-card','Bayar Aman','Pilih metode — transfer, e-wallet, atau COD.'],['fa-box-open','Terima Bersih','Cucian diantar bersih, wangi, dan dilipat rapi.']];
    foreach($steps as $i=>$s): ?>
    <div class="gc step-c fi d<?= $i+1 ?>"><span class="step-n">0<?= $i+1 ?></span><div class="step-ic"><i class="fa-solid <?= $s[0] ?>"></i></div><h3><?= $s[1] ?></h3><p><?= $s[2] ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- SERVICES -->
<section class="sec" style="background:linear-gradient(180deg,var(--mr0),var(--mr2))"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-wand-magic-sparkles"></i> Layanan Kami</div>
    <h2 class="sec-t fi d1">Pilihan <em>Terbaik</em> dari CUCI.IN</h2>
    <p class="sec-d fi d2">Berbagai layanan laundry profesional untuk semua kebutuhan Anda, dari pakaian sehari-hari hingga item khusus.</p>
  </div>
  <div class="srv-g">
    <?php foreach($layanan as $i=>$l):
      $sIcon = getIcon($l['nama_layanan']);
      $sBadge = getBadge($l['nama_layanan']);
      $estimasi = $l[$colEstimasi] ?? '';
      $satuan = $l[$colSatuan] ?? 'kg';
    ?>
    <div class="gc srv-c fi d<?= ($i%4)+1 ?>">
      <div class="srv-top">
        <div class="srv-ic"><i class="fa-solid <?= $sIcon ?>"></i></div>
        <div>
          <div class="srv-badge" style="background:<?= $sBadge['bg'] ?>;color:<?= $sBadge['c'] ?>;border:1px solid <?= $sBadge['b'] ?>"><i class="fa-solid <?= $sIcon ?>" style="font-size:.5rem"></i> <?= $sBadge['l'] ?></div>
          <div class="srv-nm"><?= htmlspecialchars($l['nama_layanan']) ?></div>
        </div>
      </div>
      <?php if(!empty($l[$colDesc])): ?><div style="padding:0 1.4rem"><p class="srv-ds"><?= htmlspecialchars($l[$colDesc]) ?></p></div><?php endif; ?>
      <div class="srv-foot">
        <div>
          <div class="srv-price"><i class="fa-solid fa-tag"></i> <?= formatRupiah($l[$colHarga]) ?></div>
        </div>
        <div class="srv-meta">
          <?php if(!empty($estimasi)): ?><span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($estimasi) ?></span><?php endif; ?>
          <span><i class="fa-solid fa-weight-hanging"></i> per <?= htmlspecialchars($satuan) ?></span>
        </div>
      </div>
      <div class="srv-btn">
        <?php if(isLoggedIn()): ?>
          <button onclick="addCart(<?= $l['id_layanan'] ?>,'<?= htmlspecialchars(addslashes($l['nama_layanan'])) ?>')" class="btn btn-pk btn-sm btn-block"><span><i class="fa-solid fa-cart-plus"></i> Tambah</span></button>
        <?php else: ?>
          <a href="<?= APP_URL ?>/user/login.php" class="btn btn-ol btn-sm btn-block"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-top:2rem" class="fi"><a href="<?= APP_URL ?>/user/layanan.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-right"></i> Lihat Semua Layanan <i class="fa-solid fa-external-link" style="font-size:.7rem;margin-left:2px"></i></a></div>
</div></section>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- FEATURE HIGHLIGHTS -->
<section class="sec"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-bolt"></i> Keunggulan Kami</div>
    <h2 class="sec-t fi d1">Yang Membedakan Kami <em>Berbeda</em></h2>
    <p class="sec-d fi d2">Fitur-fitur unggulan yang membedakan CUCI.IN dari laundry biasa.</p>
  </div>
  <div class="feat-g">
    <?php $feats=[
        ['fa-rocket','Proses Kilat','Layanan express selesai hanya 2 jam dengan kualitas tetap terjaga tanpa kompromi.'],
        ['fa-mobile-screen','Dashboard Digital','Pantau pesanan, riwayat, dan pembayaran dari satu tempat melalui dashboard modern.'],
        ['fa-bell','Notifikasi Aktif','Dapatkan update status pesanan secara real-time via notifikasi push dan email.'],
        ['fa-chart-pie','Laporan Detail','Lihat breakdown biaya dan riwayat transaksi secara lengkap dan transparan.'],
        ['fa-clock-rotate-left','Fleksibel Jadwal','Pilih sendiri waktu pickup dan delivery sesuai ketersediaan Anda.'],
        ['fa-code-compare','API Terintegrasi','Dashboard user terhubung langsung dengan sistem operasional internal kami.'],
        ['fa-users-gear','Tim Verifikasi','Setiap kurir terverifikasi dan dilatih sebelum dikerjakan ke pelanggan.'],
        ['fa-leaf','Eco-Friendly','Deterjen biodegradable, mesin hemat energi, minimalkan jejak karbon.'],
        ['fa-file-invoice','Invoice Digital','Invoice digital otomatis langsung tersedia di dashboard setelah pembayaran.'],
    ];
    foreach($feats as $i=>$f): ?>
    <div class="feat-c fi d<?= ($i%3)+1 ?>"><div class="feat-ic"><i class="fa-solid <?= $f[0] ?>"></i></div><div><h4><?= $f[1] ?></h4><p><?= $f[2] ?></p></div></div>
    <?php endforeach; ?>
  </div>
</div></section>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- SHOWCASE -->
<section class="sec" style="background:linear-gradient(180deg,var(--mr2),var(--mr0))"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-images"></i> Galeri Kami</div>
    <h2 class="sec-t fi d1">Lihat <em>Hasil Kerja</em> Kami</h2>
    <p class="sec-d fi d2">Kualitas yang berbicara sendiri — setiap cucian ditangani dengan standar tertinggi.</p>
  </div>
  <div class="show-g fi d2">
    <div class="sh-item"><img src="img/Foto5.jpeg" alt="Clean"><div class="sh-ov"><div class="sh-dot"></div><span><i class="fa-solid fa-check-circle"></i> Hasil Cuci Bersih Maksimal</span></div></div>
    <div class="sh-item"><img src="img/Foto6.jpeg" alt="Machine"><div class="sh-ov"><div class="sh-dot"></div><span><i class="fa-solid fa-gear"></i> Mesin Cuci Modern</span></div></div>
    <div class="sh-item"><img src="img/Foto7.jpeg" alt="Fold"><div class="sh-ov"><div class="sh-dot"></div><span><i class="fa-solid fa-hand-sparkles"></i> Setrika & Lipat Rapi</span></div></div>
    <div class="sh-item"><img src="img/Foto8.jpeg" alt="Iron"><div class="sh-ov"><div class="sh-dot"></div><span><i class="fa-solid fa-temperature-high"></i> Setrika Profesional</span></div></div>
    <div class="sh-item"><img src="img/Foto10.jpeg" alt="Delivery"><div class="sh-ov"><div class="sh-dot"></div><span><i class="fa-solid fa-truck-fast"></i> Pengantaran Cepat</span></div></div>
  </div>
</div></section>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- PACKAGES -->
<section class="sec"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-boxes-stacked"></i> Paket Bundling</div>
    <h2 class="sec-t fi d1">Pilih <em>Paket Langganan</em> Hemat</h2>
    <p class="sec-d fi d2">Langganan lebih hemat! Pilih paket terbaikmu dan klik untuk melihat keuntungan sistem yang kamu dapatkan.</p>
  </div>
  <div class="pkg-g">
    
    <div class="gc pkg-c fi d1">
      <div class="pkg-ic"><i class="fa-solid fa-flask"></i></div>
      <h3 class="pkg-nm">Paket Uji Coba</h3>
      <div class="pkg-pr">Gratis<br><span>Berlaku 3 Bulan</span></div>
      <p class="pkg-ds">Rasakan semua keuntungan sistem tanpa biaya!</p>
      <button class="btn btn-pk" onclick="openPkgModal('Paket Uji Coba', 'Gratis - 3 Bulan', ['Cuci hingga 20kg/bulan', 'Antar Jemput Gratis', 'Tracking Real-time', 'Dashboard Digital', 'Semua fitur basic & premium dibuka'])"><span><i class="fa-solid fa-eye"></i> Lihat Keuntungan</span></button>
    </div>

    <div class="gc pkg-c fi d2">
      <div class="pkg-ic"><i class="fa-solid fa-box-open"></i></div>
      <h3 class="pkg-nm">Paket Hemat 3 Bulan</h3>
      <div class="pkg-pr">Rp 300.000<br><span>Per 3 Bulan</span></div>
      <p class="pkg-ds">Hemat 15% untuk langganan 3 bulan.</p>
      <button class="btn btn-pk" onclick="openPkgModal('Paket Hemat 3 Bulan', 'Rp 300.000 - Per 3 Bulan', ['Cuci hingga 40kg/bulan', 'Antar Jemput Prioritas', 'Gratis Express 2x/bulan', 'Garansi Cuci Ulang', 'Deterjen Premium Gratis'])"><span><i class="fa-solid fa-eye"></i> Lihat Keuntungan</span></button>
    </div>

    <div class="gc pkg-c fi d3">
      <div class="pkg-ic"><i class="fa-solid fa-people-roof"></i></div>
      <h3 class="pkg-nm">Paket Keluarga</h3>
      <div class="pkg-pr">Rp 550.000<br><span>Per 6 Bulan</span></div>
      <p class="pkg-ds">Solusi terbaik untuk kebutuhan keluarga.</p>
      <button class="btn btn-pk" onclick="openPkgModal('Paket Keluarga 6 Bulan', 'Rp 550.000 - Per 6 Bulan', ['Cuci hingga 60kg/bulan', 'Antar Jemput Kapan Saja', 'Gratis Express 4x/bulan', 'Free Dry Clean 2x/bulan', 'Support 24/7'])"><span><i class="fa-solid fa-eye"></i> Lihat Keuntungan</span></button>
    </div>

    <div class="gc pkg-c fi d4">
      <div class="pkg-ic"><i class="fa-solid fa-crown"></i></div>
      <h3 class="pkg-nm">Paket VIP 1 Tahun</h3>
      <div class="pkg-pr">Rp 1.000.000<br><span>Per Tahun</span></div>
      <p class="pkg-ds">Hemat maksimal dengan keuntungan eksklusif.</p>
      <button class="btn btn-pk" onclick="openPkgModal('Paket VIP 1 Tahun', 'Rp 1.000.000 - Per Tahun', ['Cuci Tanpa Batas (Unlimited)', 'Antar Jemput 24/7', 'Express Tanpa Batas', 'Free Dry Clean 4x/bulan', 'Akun VIP Support'])"><span><i class="fa-solid fa-eye"></i> Lihat Keuntungan</span></button>
    </div>

  </div>
</div></section>

<div id="pkgModal" class="pkg-modal">
  <div class="pkg-modal-c">
    <span class="pkg-close" onclick="closePkgModal()"><i class="fa-solid fa-xmark"></i></span>
    <div id="pkgModalContent"></div>
  </div>
</div>

<div class="divider"><i class="fa-solid fa-diamond"></i></div>

<!-- TESTIMONIALS -->
<section class="sec" style="background:linear-gradient(180deg,var(--mr0),var(--mr2))">
<div class="sec-in">

  <div class="sec-hd">
    <div class="sec-tag fi">
      <i class="fa-solid fa-quote-left"></i> Testimoni
    </div>

    <h2 class="sec-t fi d1">
      Apa Kata <em>Pelanggan</em> Kami
    </h2>

    <p class="sec-d fi d2">
      Kepercayaan dan kepuasan mereka adalah bukti nyata kualitas layanan CUCI.IN.
    </p>
  </div>

  <div class="tst-g">

<?php

 $tst = [
    ['B','Budi S.','Karyawan Swasta','Pelayanan cepat, hasil bersih dan wangi. Sudah 6 bulan langganan dan tidak pernah kecewa!',5],
    ['A','Ahmad R.','Mahasiswa','Aplikasi mudah digunakan dan tracking pesanan jelas. Cocok buat mahasiswa sibuk seperti saya.',5],
    ['R','Rina L.','Ibu Rumah Tangga','Hemat waktu, nggak perlu repot ke laundry. Cucian selalu rapi dan harum.',5],
    ['D','Dian K.','Freelancer','Express 2 jam tepat waktu, sangat membantu saat butuh baju bersih mendadak untuk meeting.',4.5],
    ['F','Faisal M.','Wiraswasta','Staff ramah, pakaian dilipat rapi. Harga sangat reasonable untuk kualitas ini.',5],
    ['S','Sari P.','Guru','Hasil bersih dan harum, anak-anak juga suka. Pasti akan langganan terus!',5],
    ['A','Andi T.','Dokter','Harga jelas sesuai kualitas, tidak ada biaya tersembunyi. Ini yang saya cari.',4.5],
    ['L','Lia N.','Accountant','Cocok buat karyawan sibuk yang nggak punya waktu cuci baju. Weekend jadi lebih free!',5]
];

foreach($tst as $i => $t):

    $sf = floor($t[4]);
    $hh = fmod($t[4], 1) !== 0.0;

    $sh = str_repeat(
        '<i class="fa-solid fa-star"></i>',
        $sf
    ) . ($hh
        ? '<i class="fa-solid fa-star-half-stroke"></i>'
        : '');

?>

    <div class="gc tst-c fi d<?= ($i % 4) + 1 ?>">

      <div class="tst-str">
        <?= $sh ?>
      </div>

      <p class="tst-tx">
        <?= $t[3] ?>
      </p>

      <div class="tst-au">

        <div class="tst-av">
          <?= $t[0] ?>
        </div>

        <div>
          <div class="tst-nm">
            <?= $t[1] ?>
          </div>

          <div class="tst-rl">
            <i class="fa-solid fa-briefcase"></i>
            <?= $t[2] ?>
          </div>
        </div>

      </div>

    </div>

<?php endforeach; ?>

  </div>
</div>
</section>

<div class="divider">
  <i class="fa-solid fa-diamond"></i>
</div>

<!-- FAQ -->
<section class="sec"><div class="sec-in">
  <div class="sec-hd">
    <div class="sec-tag fi"><i class="fa-solid fa-circle-question"></i> FAQ</div>
    <h2 class="sec-t fi d1">Pertanyaan <em>Umum</em></h2>
    <p class="sec-d fi d2">Jawaban atas pertanyaan yang sering diajukan pelanggan kami.</p>
  </div>
  <div class="faq-list fi d2">
    <?php $faq=[['Berapa lama proses pengerjaan laundry biasa?','Reguler 24-48 jam sejak diterima. Express hanya 2 jam tanpa mengorbankan kualitas.'],['Apakah ada minimum order?','Tidak ada minimum order. Mulai dari 1 potong pakaian. Antar jemput gratis minimum 3 kg.'],['Bagaimana jika pakaian hilang atau rusak?','Garansi penuh. Jika terjadi kerusakan atau kehilangan, kami ganti sesuai nilai pakaian.'],['Metode pembayaran apa saja yang diterima?','Transfer bank (BCA, Mandiri, BNI, BRI), e-wallet (GoPay, OVO, DANA, ShopeePay), dan COD.'],['Bisa request deterjen atau pewangi tertentu?','Tentu! Tambahkan catatan khusus saat pemesanan untuk request deterjen hypoallergenic atau pewangi favorit.'],['Area mana saja yang tercakup antar jemput?','Seluruh wilayah dalam kota. Untuk area luar kota, hubungi customer service kami.'],['Apakah bisa cancel pesanan?','Ya, bisa dibatalkan selama status masih pending. Cek di halaman detail pesanan.'],['Apakah tersedia paket langganan bulanan?','Ya! Tersedia paket hemat untuk pelanggan langganan bulanan dengan diskon hingga 20%.']];
    foreach($faq as $f): ?>
    <div class="faq-item"><div class="faq-q" onclick="toggleFaq(this)"><span><?= $f[0] ?></span><i class="fa-solid fa-chevron-down"></i></div><div class="faq-a"><div class="faq-a-inner"><?= $f[1] ?></div></div></div>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- TRUST -->
<div class="trust-bar fi"><div class="trust-in">
  <div class="trust-item"><i class="fa-solid fa-shield-halved"></i> Data Terenkripsi</div>
  <div class="trust-item"><i class="fa-solid fa-lock"></i> Pembayaran Aman</div>
  <div class="trust-item"><i class="fa-solid fa-headset"></i> Support 24/7</div>
  <div class="trust-item"><i class="fa-solid fa-rotate-left"></i> Garansi Uang Kembali</div>
  <div class="trust-item"><i class="fa-solid fa-certificate"></i> ISO Certified</div>
</div></div>

<!-- CTA -->
<section class="sec" style="padding-top:3rem"><div class="cta-in fi">
  <h2><i class="fa-solid fa-rocket" style="margin-right:8px"></i> Siap Merasakan <em>Kemudahan</em> Laundry?</h2>
  <p>Bergabung dengan 5.000+ pelanggan yang sudah mempercayakan cucian mereka kepada CUCI.IN. Daftar gratis, tanpa komitmen.</p>
  <div class="cta-acts">
    <?php if(isLoggedIn()): ?>
      <a href="<?= APP_URL ?>/user/layanan.php" class="btn btn-w btn-lg"><i class="fa-solid fa-basket-shopping"></i> Pesan Sekarang</a>
      <a href="<?= APP_URL ?>/user/orders.php" class="btn btn-glass btn-lg"><i class="fa-solid fa-list-check"></i> Pesanan Saya</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/user/register.php" class="btn btn-w btn-lg"><i class="fa-solid fa-user-plus"></i> Daftar & Mulai</a>
      <a href="<?= APP_URL ?>/user/login.php" class="btn btn-glass btn-lg"><i class="fa-solid fa-right-to-bracket"></i> Sudah Punya Akun?</a>
    <?php endif; ?>
  </div>
</div></section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
/* ===== LOADER: fallback timeout biar ga nge-stuck ===== */
(function(){
  var loader = document.getElementById('pgLoader');
  var done = false;
  function hideLoader(){
    if(done) return;
    done = true;
    loader.classList.add('gone');
    setTimeout(function(){
      loader.classList.add('hide');
      var w = document.getElementById('heroWrap');
      if(w) w.classList.add('v');
    }, 800);
  }
  // Fallback: pasti hilang max 3.5 detik
  var fallback = setTimeout(hideLoader, 3500);
  window.addEventListener('load', function(){
    clearTimeout(fallback);
    setTimeout(hideLoader, 1200);
  });
  // Safety: kalau DOMContentLoaded saja sudah lengkap
  if(document.readyState === 'complete'){
    clearTimeout(fallback);
    setTimeout(hideLoader, 1200);
  }
})();

/* ===== SLIDER ===== */
var cs=0,sl=document.querySelectorAll('#heroSlider img'),dt=document.querySelectorAll('.dot');
function shS(i){if(i>=sl.length)cs=0;else if(i<0)cs=sl.length-1;else cs=i;sl.forEach(function(s){s.classList.remove('on')});dt.forEach(function(d){d.classList.remove('on')});sl[cs].classList.add('on');dt[cs].classList.add('on')}
function chSlide(n){shS(cs+n)}function goSlide(n){shS(n)}setInterval(function(){chSlide(1)},5500);

/* ===== SCROLL REVEAL ===== */
var obs=new IntersectionObserver(function(e){e.forEach(function(en){if(en.isIntersecting)en.target.classList.add('v')})},{threshold:.06,rootMargin:'0px 0px -30px 0px'});
document.querySelectorAll('.fi').forEach(function(el){obs.observe(el)});

/* ===== COUNTER ===== */
var cObs=new IntersectionObserver(function(e){e.forEach(function(en){if(en.isIntersecting){var el=en.target,tg=parseInt(el.dataset.target),sf=el.dataset.suffix||'',st=performance.now();function up(n){var p=Math.min((n-st)/2200,1),e=1-Math.pow(1-p,3),c=Math.floor(e*tg);el.textContent=tg>=1000?(c/1000).toFixed(c>=tg?0:1)+'K+'+sf:c+sf;if(p<1)requestAnimationFrame(up)}requestAnimationFrame(up);cObs.unobserve(el)}})},{threshold:.5});
document.querySelectorAll('.st-num').forEach(function(el){cObs.observe(el)});

/* ===== NAVBAR SCROLL ===== */
var nb=document.querySelector('header,.navbar,nav');if(nb)window.addEventListener('scroll',function(){nb.classList.toggle('scrolled',window.scrollY>50)});

/* ===== FAQ ===== */
function toggleFaq(q){var item=q.parentElement,wasOpen=item.classList.contains('open');document.querySelectorAll('.faq-item.open').forEach(function(i){i.classList.remove('open')});if(!wasOpen)item.classList.add('open')}

/* ===== CART ===== */
function addCart(id,name){fetch('<?= APP_URL ?>/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',id_layanan:id,qty:1})}).then(function(r){return r.json()}).then(function(d){if(d.success)showToast(name+' ditambahkan ke keranjang','ok');else showToast(d.message||'Gagal menambahkan','err')}).catch(function(){showToast('Terjadi kesalahan','err')})}

/* ===== TOAST ===== */
function showToast(msg,type){type=type||'ok';var ct=document.getElementById('toastCt'),t=document.createElement('div');t.className='toast '+type;t.innerHTML='<i class="fa-solid '+(type==='ok'?'fa-circle-check':'fa-circle-exclamation')+'"></i> '+msg;ct.appendChild(t);setTimeout(function(){t.remove()},3200)}

/* ===== PACKAGES MODAL ===== */
function openPkgModal(name, price, benefits) {
    var listHtml = benefits.map(function(b) { return '<li><i class="fa-solid fa-check-circle"></i> ' + b + '</li>'; }).join('');
    var iconMap = {
        'Paket Uji Coba': 'fa-flask',
        'Paket Hemat 3 Bulan': 'fa-box-open',
        'Paket Keluarga 6 Bulan': 'fa-people-roof',
        'Paket VIP 1 Tahun': 'fa-crown'
    };
    var icon = iconMap[name] || 'fa-box';
    document.getElementById('pkgModalContent').innerHTML = 
        '<div class="pkg-m-hd"><div class="pkg-m-ic"><i class="fa-solid ' + icon + '"></i></div><div class="pkg-m-nm">' + name + '</div><div class="pkg-m-pr">' + price + '</div></div>' +
        '<ul class="pkg-m-list">' + listHtml + '</ul>' +
        '<button class="btn btn-pk btn-block" onclick="closePkgModal()"><span><i class="fa-solid fa-thumbs-up"></i> Saya Tertarik</span></button>';
    var modal = document.getElementById('pkgModal');
    modal.classList.add('show');
}
function closePkgModal() {
    document.getElementById('pkgModal').classList.remove('show');
}
document.getElementById('pkgModal').addEventListener('click', function(e) {
    if(e.target === this) closePkgModal();
});
</script>
</body>
</html>