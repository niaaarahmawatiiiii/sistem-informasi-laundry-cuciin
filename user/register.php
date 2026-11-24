<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/user/dashboard.php');
    }
    exit;
}

 $errors = [];
 $data = [];
 $success = '';
 $hasError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nama'     => trim($_POST['nama'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'no_hp'    => trim($_POST['no_hp'] ?? ''),
        'alamat'   => trim($_POST['alamat'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'konfirm'  => $_POST['konfirm'] ?? '',
    ];

    if (strlen($data['nama']) < 3) $errors['nama'] = 'Nama minimal 3 karakter.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Format email tidak valid.';
    if (!empty($data['no_hp']) && !preg_match('/^[0-9]{10,13}$/', $data['no_hp'])) $errors['no_hp'] = 'No. HP tidak valid (10-13 digit).';
    if (strlen($data['password']) < 6) $errors['password'] = 'Password minimal 6 karakter.';
    if ($data['password'] !== $data['konfirm']) $errors['konfirm'] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id_user FROM users WHERE email = ?");
        $stmt->bind_param('s', $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['email'] = 'Email sudah terdaftar. Silakan masuk.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (nama, email, password, no_hp, alamat, role, created_at) VALUES (?,?,?,?,?,'user',NOW())");
        $stmt->bind_param('sssss', $data['nama'], $data['email'], $hash, $data['no_hp'], $data['alamat']);

        if ($stmt->execute()) {
            $success = 'Akun berhasil dibuat! Silakan masuk dengan email Anda.';
            $data = [];
        } else {
            $errors['general'] = 'Gagal membuat akun. Silakan coba lagi.';
        }
    }
    
    $hasError = !empty($errors);
}

 $pageTitle = 'Daftar — CUCI.IN';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Nunito:wght@400;500;600;700;800&display=swap');
*{margin:0;padding:0;box-sizing:border-box;}

#splash{
  position:fixed;inset:0;z-index:9999;
  background:#fdf4fb;
  display:flex;align-items:center;justify-content:center;flex-direction:column;gap:24px;
  overflow:hidden;
  animation:splashExit 0.7s 3.4s cubic-bezier(0.76,0,0.24,1) forwards;
}
@keyframes splashExit{0%{transform:translateY(0);opacity:1}100%{transform:translateY(-100%);opacity:0;pointer-events:none}}

.splash-circles{position:absolute;inset:0;overflow:hidden;}
.sc{position:absolute;border-radius:50%;animation:scPop 3s ease-in-out infinite;}
.sc:nth-child(1){width:260px;height:260px;background:#f9d6f0;top:-60px;right:-60px;animation-delay:0s;}
.sc:nth-child(2){width:180px;height:180px;background:#ddd6fe;bottom:-40px;left:-40px;animation-delay:0.5s;}
.sc:nth-child(3){width:100px;height:100px;background:#bfdbfe;top:40%;right:5%;animation-delay:1s;}
.sc:nth-child(4){width:70px;height:70px;background:#fbcfe8;bottom:20%;left:8%;animation-delay:1.5s;}
@keyframes scPop{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}

.splash-logo-wrap{
  position:relative;z-index:1;
  display:flex;flex-direction:column;align-items:center;gap:16px;
  animation:splashIn 0.6s 0.2s both;
}
@keyframes splashIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

.splash-icon{
  width:90px;height:90px;position:relative;
  animation:spinDrum 2.5s linear infinite;
}
@keyframes spinDrum{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}

.splash-wordmark{
  font-family:'Playfair Display',serif;
  font-size:2.6rem;font-weight:800;
  color:#7c3d8f;letter-spacing:-1px;
  animation:splashIn 0.6s 0.4s both;
}
.splash-wordmark span{color:#e879a0;}

.marquee-track{
  overflow:hidden;width:360px;
  border-top:1.5px solid #e9c4f0;border-bottom:1.5px solid #e9c4f0;
  padding:9px 0;
  position:relative;z-index:1;
  animation:splashIn 0.6s 0.6s both;
}
.marquee-inner{
  display:flex;gap:48px;width:max-content;
  animation:roll 3.2s linear infinite;
}
@keyframes roll{0%{transform:translateX(100%)}100%{transform:translateX(-50%)}}
.marquee-inner span{
  font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;
  color:#a855b5;letter-spacing:2.5px;text-transform:uppercase;white-space:nowrap;
}
.marquee-inner span b{color:#e879a0;font-weight:800;}

.splash-sub{
  font-family:'Nunito',sans-serif;font-size:13px;color:#c084d4;
  letter-spacing:1px;position:relative;z-index:1;
  animation:splashIn 0.6s 0.8s both;
}

#main-page{
  min-height:100vh;
  font-family:'Nunito',sans-serif;
  opacity:0;
  animation:pageShow 0.6s 3.7s ease forwards;
}
@keyframes pageShow{to{opacity:1}}

.layout{display:grid;grid-template-columns:1fr 1fr;min-height:100vh;}

.left{
  background:#fdf4fb;
  position:relative;overflow:hidden;
  display:flex;flex-direction:column;padding:2.5rem;
}

.left-deco{position:absolute;inset:0;overflow:hidden;pointer-events:none;}
.blob{position:absolute;border-radius:50%;}
.blob-1{width:400px;height:400px;background:#f5d0f9;top:-80px;right:-100px;opacity:0.6;}
.blob-2{width:280px;height:280px;background:#ddd6fe;bottom:-60px;left:-60px;opacity:0.5;}
.blob-3{width:160px;height:160px;background:#fbcfe8;top:45%;left:-30px;opacity:0.4;}

.soap-bubbles{position:absolute;inset:0;overflow:hidden;}
.bub{
  position:absolute;border-radius:50%;
  border:1.5px solid rgba(200,150,220,0.3);
  background:rgba(255,255,255,0.15);
  animation:rise 5s ease-in infinite;
}
.bub:nth-child(1){width:18px;height:18px;left:12%;animation-duration:4.5s;animation-delay:0s;}
.bub:nth-child(2){width:11px;height:11px;left:25%;animation-duration:5.5s;animation-delay:1s;}
.bub:nth-child(3){width:22px;height:22px;left:40%;animation-duration:4.8s;animation-delay:2s;}
.bub:nth-child(4){width:9px;height:9px;left:60%;animation-duration:5.2s;animation-delay:0.5s;}
.bub:nth-child(5){width:15px;height:15px;left:75%;animation-duration:4.2s;animation-delay:1.5s;}
.bub:nth-child(6){width:13px;height:13px;left:88%;animation-duration:5.8s;animation-delay:0.8s;}
@keyframes rise{
  0%{bottom:-30px;opacity:0;}
  15%{opacity:0.7;}
  85%{opacity:0.3;}
  100%{bottom:110%;opacity:0;}
}

.left-inner{position:relative;z-index:2;display:flex;flex-direction:column;height:100%;}

.brand-top{display:flex;align-items:center;gap:10px;margin-bottom:auto;}
.logo-box{
  width:36px;height:36px;object-fit:contain;
  border-radius:50%;overflow:hidden;border:1.5px solid #e9c4f0;
  display:flex;align-items:center;justify-content:center;
}
.logo-box img{width:28px;height:28px;object-fit:contain;}
.brand-name{font-family:'Playfair Display',serif;font-weight:800;color:#7c3d8f;font-size:1.25rem;letter-spacing:-0.3px;}
.brand-name span{color:#e879a0;}

.hero-area{padding:2.2rem 0 2.5rem;}
.hero-pill{
  display:inline-flex;align-items:center;gap:6px;
  background:#fff;border:1.5px solid #e9c4f0;
  color:#a855b5;font-size:11px;font-weight:700;
  padding:4px 13px;border-radius:20px;letter-spacing:1px;
  text-transform:uppercase;margin-bottom:1.1rem;
}
.pill-dot{width:7px;height:7px;border-radius:50%;background:#e879a0;animation:blink 1.6s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.3}}

.hero-title{
  font-family:'Playfair Display',serif;
  font-size:3rem;font-weight:800;
  color:#4a1d6e;line-height:1.1;
  letter-spacing:-1.5px;margin-bottom:1.1rem;
}
.hero-title em{font-style:normal;color:#e879a0;}
.hero-desc{color:#a47ab8;font-size:0.9rem;line-height:1.8;max-width:290px;}

.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.stat-card{
  background:#fff;border-radius:16px;
  border:1.5px solid #f0d4f8;
  padding:1.1rem 1.2rem;
  transition:border-color 0.2s;
}
.stat-card:hover{border-color:#c084d4;}
.stat-num{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:700;color:#7c3d8f;line-height:1;}
.stat-lbl{font-size:11px;color:#c084d4;font-weight:600;letter-spacing:0.5px;margin-top:3px;text-transform:uppercase;}

.service-badges{display:flex;flex-wrap:wrap;gap:7px;padding:1.3rem 0 0;}
.sbadge{
  background:#fff;border:1.5px solid #f0d4f8;
  color:#9333a4;font-size:11px;font-weight:700;
  padding:5px 13px;border-radius:20px;
  display:flex;align-items:center;gap:6px;
}
.badge-dot{width:5px;height:5px;border-radius:50%;background:#e879a0;}

.right{
  background:#fff;
  display:flex;flex-direction:column;
  position:relative;overflow:hidden;
}

.top-stripe{
  height:5px;
  background:repeating-linear-gradient(90deg,#f9a8d4 0px,#f9a8d4 20px,#ddd6fe 20px,#ddd6fe 40px,#bfdbfe 40px,#bfdbfe 60px,#bbf7d0 60px,#bbf7d0 80px);
  animation:stripeScroll 3s linear infinite;
  background-size:80px 5px;
}
@keyframes stripeScroll{0%{background-position:0}100%{background-position:80px}}

.right-deco{position:absolute;top:-60px;right:-60px;width:220px;height:220px;border-radius:50%;background:#fdf4fb;z-index:0;}

.form-area{
  flex:1;display:flex;flex-direction:column;
  justify-content:center;align-items:center;
  padding:1.5rem 2.5rem;position:relative;z-index:1;
  overflow-y:auto;
}
.form-wrap{width:100%;max-width:370px;}

.back-link{
  display:inline-flex;align-items:center;gap:6px;
  font-size:12px;font-weight:700;color:#c084d4;
  background:none;border:none;cursor:pointer;padding:0;margin-bottom:1.5rem;
  font-family:'Nunito',sans-serif;text-decoration:none;
  transition:color 0.2s;
}
.back-link:hover{color:#a855b5;}
.back-arrow{font-size:14px;font-weight:800;}

.greeting-chip{
  display:inline-flex;align-items:center;gap:7px;
  background:#fdf4fb;border:1.5px solid #f0d4f8;
  color:#9333a4;font-size:11px;font-weight:800;
  padding:5px 13px;border-radius:20px;letter-spacing:0.5px;
  margin-bottom:10px;text-transform:uppercase;
}

.form-title{
  font-family:'Playfair Display',serif;
  font-size:2.1rem;font-weight:800;
  color:#4a1d6e;letter-spacing:-1px;margin-bottom:5px;
}
.form-sub{font-size:13px;color:#b4a0c4;margin-bottom:1.5rem;}
.form-sub a{color:#a855b5;font-weight:700;text-decoration:none;}
.form-sub a:hover{text-decoration:underline;}

.err-box{
  background:#fff0f6;border:1.5px solid #fbcfe8;border-radius:12px;
  padding:10px 14px;font-size:13px;color:#be185d;
  display:flex;align-items:center;gap:8px;margin-bottom:1rem;
  animation:errShake 0.4s ease;
}
@keyframes errShake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-3px)}80%{transform:translateX(3px)}}
.err-icon{width:16px;height:16px;border-radius:50%;background:#fbcfe8;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;}

.ok-box{
  background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;
  padding:12px 14px;font-size:13px;color:#15803d;
  display:flex;align-items:center;gap:8px;margin-bottom:1rem;
  animation:okPop 0.4s ease;
}
@keyframes okPop{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}
.ok-icon{width:16px;height:16px;border-radius:50%;background:#bbf7d0;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;}

.fg{margin-bottom:0.85rem;}
.fl{
  font-size:12px;font-weight:700;color:#6b21a8;
  margin-bottom:5px;
}

.field-err{
  font-size:11px;color:#be185d;margin-top:4px;
  display:flex;align-items:center;gap:4px;
  animation:errShake 0.3s ease;
}
.field-err i{font-size:10px;}

.inp-shell{
  display:flex;align-items:center;
  border:1.5px solid #f0d4f8;border-radius:13px;
  background:#fdf4fb;overflow:hidden;
  transition:border-color 0.2s,background 0.2s;
}
.inp-shell:focus-within{border-color:#c084d4;background:#fff;box-shadow:0 0 0 4px rgba(192,132,212,0.12);}
.inp-shell.err-state{border-color:#f9a8d4 !important;background:#fff0f6 !important;box-shadow:0 0 0 4px rgba(249,168,212,0.15) !important;}
.inp-ico{
  width:42px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.inp-shell input{
  flex:1;border:none;background:transparent;
  padding:0.7rem 0.7rem;font-size:13px;font-weight:500;
  color:#4a1d6e;outline:none;font-family:'Nunito',sans-serif;
}
.inp-shell input::placeholder{color:#d4b4e4;}
.eye-btn{
  width:42px;border:none;background:transparent;cursor:pointer;
  border-left:1.5px solid #f0d4f8;align-self:stretch;
  display:flex;align-items:center;justify-content:center;
  transition:background 0.15s;
}
.eye-btn:hover{background:#fdf4fb;}

.sbar{display:flex;gap:4px;margin-top:5px;}
.ss{flex:1;height:3px;border-radius:4px;background:#f0d4f8;transition:background 0.3s;}
.ss.lv1{background:#fbcfe8;}
.ss.lv2{background:#f0abfc;}
.ss.lv3{background:#c084d4;}
.ss.lv4{background:#a855b5;}

.row-2{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}

.opts-row{display:flex;align-items:flex-start;gap:7px;margin-bottom:1.1rem;}
.chk-wrap{display:flex;align-items:flex-start;gap:7px;cursor:pointer;}
.chk-wrap input{accent-color:#a855b5;width:14px;height:14px;cursor:pointer;margin-top:2px;flex-shrink:0;}
.chk-wrap span{font-size:12px;color:#b4a0c4;font-weight:600;line-height:1.4;}
.chk-wrap span a{color:#a855b5;font-weight:700;text-decoration:none;}
.chk-wrap span a:hover{text-decoration:underline;}

.btn-main{
  width:100%;padding:0.9rem;
  background:#9333a4;color:#fff;
  border:none;border-radius:13px;
  font-family:'Nunito',sans-serif;font-size:15px;font-weight:800;
  cursor:pointer;letter-spacing:0.2px;
  display:flex;align-items:center;justify-content:center;gap:10px;
  transition:background 0.2s,transform 0.15s;
  position:relative;overflow:hidden;
}
.btn-main::before{
  content:'';position:absolute;
  top:-50%;left:-50%;width:200%;height:200%;
  background:rgba(255,255,255,0.08);
  border-radius:50%;transform:scale(0);
  transition:transform 0.4s;
}
.btn-main:hover{background:#7c3d8f;transform:translateY(-1px);}
.btn-main:hover::before{transform:scale(1);}
.btn-main:active{transform:scale(0.98);}
.btn-main.loading{pointer-events:none;opacity:0.8;}
.btn-arr{font-size:17px;transition:transform 0.2s;}
.btn-main:hover .btn-arr{transform:translateX(4px);}

.divider{height:1px;background:#f5e8fc;margin:1.2rem 0;}

.footer-note{text-align:center;font-size:12px;color:#d4b4e4;line-height:1.6;}
.footer-note a{color:#a855b5;text-decoration:none;font-weight:700;}

.page-footer{
  display:flex;align-items:center;justify-content:space-between;
  padding:1.1rem 2.5rem;border-top:1.5px solid #fdf4fb;
}
.pf-brand{font-size:11px;color:#d4b4e4;font-weight:700;display:flex;align-items:center;gap:6px;}
.pf-links{display:flex;gap:16px;}
.pf-links a{font-size:11px;color:#d4b4e4;text-decoration:none;font-weight:600;transition:color 0.2s;}
.pf-links a:hover{color:#a855b5;}

@media(max-width:700px){
  .layout{grid-template-columns:1fr;}
  .left{display:none;}
  .form-area{padding:2rem 1.5rem;}
  .page-footer{flex-direction:column;gap:8px;text-align:center;}
  .row-2{grid-template-columns:1fr;}
}
  </style>
</head>
<body>

<!-- SPLASH -->
<div id="splash">
  <div class="splash-circles">
    <div class="sc"></div><div class="sc"></div><div class="sc"></div><div class="sc"></div>
  </div>
  <div class="splash-logo-wrap">
    <svg class="splash-icon" viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg">
      <circle cx="45" cy="45" r="42" fill="#f5d0f9" stroke="#e9c4f0" stroke-width="1.5"/>
      <circle cx="45" cy="45" r="33" fill="#fdf4fb" stroke="#e0b8f5" stroke-width="1.5"/>
      <circle cx="45" cy="45" r="20" fill="#f5d0f9" stroke="#d4a8ea" stroke-width="1.5"/>
      <circle cx="27" cy="30" r="5" fill="#fff" stroke="#e0b8f5" stroke-width="1"/>
      <circle cx="63" cy="30" r="5" fill="#fff" stroke="#e0b8f5" stroke-width="1"/>
      <circle cx="63" cy="60" r="5" fill="#fff" stroke="#e0b8f5" stroke-width="1"/>
      <circle cx="27" cy="60" r="5" fill="#fff" stroke="#e0b8f5" stroke-width="1"/>
      <circle cx="45" cy="45" r="9" fill="#e879a0"/>
      <circle cx="45" cy="45" r="4" fill="#fff"/>
      <circle cx="41" cy="41" r="2" fill="rgba(255,255,255,0.6)"/>
    </svg>
    <div class="splash-wordmark">CUCI<span>.IN</span></div>
  </div>
  <div class="marquee-track">
    <div class="marquee-inner">
      <span>Welcome to our website &mdash; <b>CUCI.IN</b> &mdash; Laundry Digital Terpercaya</span>
      <span>Welcome to our website &mdash; <b>CUCI.IN</b> &mdash; Laundry Digital Terpercaya</span>
    </div>
  </div>
  <p class="splash-sub">Bersih &middot; Wangi &middot; Tepat Waktu</p>
</div>

<!-- MAIN PAGE -->
<div id="main-page">
<div class="layout">

  <!-- LEFT -->
  <div class="left">
    <div class="left-deco">
      <div class="blob blob-1"></div>
      <div class="blob blob-2"></div>
      <div class="blob blob-3"></div>
    </div>
    <div class="soap-bubbles">
      <div class="bub"></div><div class="bub"></div><div class="bub"></div>
      <div class="bub"></div><div class="bub"></div><div class="bub"></div>
    </div>
    <div class="left-inner">
      <div class="brand-top">
        <div class="logo-box">
          <img src="<?= APP_URL ?>/img/logo.png" alt="CUCI.IN">
        </div>
        <span class="brand-name">CUCI<span>.IN</span></span>
      </div>
      <div class="hero-area">
        <div class="hero-pill">
          <div class="pill-dot"></div>
          Platform Laundry No.1
        </div>
        <h1 class="hero-title">
          Daftar<br><em>Mudah</em><br>Cucian<br>Bersih.
        </h1>
        <p class="hero-desc">Buat akun gratis, pesan layanan, pantau cucian real-time — semua dari genggaman tanganmu.</p>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-num">12K+</div><div class="stat-lbl">Pelanggan</div></div>
        <div class="stat-card"><div class="stat-num">99%</div><div class="stat-lbl">Kepuasan</div></div>
        <div class="stat-card"><div class="stat-num">3 Jam</div><div class="stat-lbl">Ekspres</div></div>
        <div class="stat-card"><div class="stat-num">24/7</div><div class="stat-lbl">Pickup</div></div>
      </div>
      <div class="service-badges">
        <span class="sbadge"><span class="badge-dot"></span>Antar Jemput</span>
        <span class="sbadge"><span class="badge-dot"></span>Cuci Kering</span>
        <span class="sbadge"><span class="badge-dot"></span>Setrika</span>
        <span class="sbadge"><span class="badge-dot"></span>Dry Clean</span>
        <span class="sbadge"><span class="badge-dot"></span>Ekspres</span>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="right">
    <div class="top-stripe"></div>
    <div class="right-deco"></div>
    <div class="form-area">
      <div class="form-wrap">

        <a href="<?= APP_URL ?>" class="back-link">
          <span class="back-arrow">←</span> Kembali ke Beranda
        </a>

        <div class="greeting-chip">
          <div class="pill-dot"></div>
          Buat Akun Baru
        </div>
        <h1 class="form-title">Daftar Sekarang</h1>
        <p class="form-sub">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>

        <?php if ($hasError && isset($errors['general'])): ?>
        <div class="err-box">
          <div class="err-icon">
            <svg width="10" height="10" viewBox="0 0 10 10"><line x1="2" y1="2" x2="8" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="2" x2="2" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <span><?= htmlspecialchars($errors['general']) ?></span>
        </div>
        <?php elseif ($hasError): ?>
        <div class="err-box">
          <div class="err-icon">
            <svg width="10" height="10" viewBox="0 0 10 10"><line x1="2" y1="2" x2="8" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="2" x2="2" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <span>Periksa kembali data yang kamu isi.</span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="ok-box">
          <div class="ok-icon">
            <svg width="10" height="10" viewBox="0 0 10 10"><polyline points="2,5.5 4,7.5 8,3" stroke="#15803d" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <span><?= htmlspecialchars($success) ?></span>
        </div>
        <a href="login.php" class="btn-main" style="text-decoration:none;margin-top:0.5rem;">
          <span>Masuk Sekarang</span>
          <svg class="btn-arr" width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M10 5l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <?php else: ?>

        <form method="POST" action="" id="regForm" novalidate>
          
          <div class="fg">
            <label class="fl">Nama Lengkap</label>
            <div class="inp-shell <?= isset($errors['nama']) ? 'err-state' : '' ?>">
              <div class="inp-ico">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="#d4b4e4" stroke-width="1.4"/><path d="M2 14c0-2.8 2.7-5 6-5s6 2.2 6 5" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/></svg>
              </div>
              <input type="text" name="nama" placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($data['nama'] ?? '') ?>" required>
            </div>
            <?php if (isset($errors['nama'])): ?>
            <div class="field-err"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['nama'] ?></div>
            <?php endif; ?>
          </div>

          <div class="fg">
            <label class="fl">Alamat Email</label>
            <div class="inp-shell <?= isset($errors['email']) ? 'err-state' : '' ?>">
              <div class="inp-ico">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><path d="M1 5l7 5 7-5" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/></svg>
              </div>
              <input type="email" name="email" placeholder="email@kamu.com" value="<?= htmlspecialchars($data['email'] ?? '') ?>" required>
            </div>
            <?php if (isset($errors['email'])): ?>
            <div class="field-err"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['email'] ?></div>
            <?php endif; ?>
          </div>

          <div class="row-2">
            <div class="fg">
              <label class="fl">No. HP</label>
              <div class="inp-shell <?= isset($errors['no_hp']) ? 'err-state' : '' ?>">
                <div class="inp-ico">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="4" y="1" width="8" height="14" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><line x1="7" y1="12" x2="9" y2="12" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/></svg>
                </div>
                <input type="tel" name="no_hp" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($data['no_hp'] ?? '') ?>">
              </div>
              <?php if (isset($errors['no_hp'])): ?>
              <div class="field-err"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['no_hp'] ?></div>
              <?php endif; ?>
            </div>
            <div class="fg">
              <label class="fl">Alamat</label>
              <div class="inp-shell">
                <div class="inp-ico">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1C5.2 1 3 3.2 3 6c0 4 5 9 5 9s5-5 5-9c0-2.8-2.2-5-5-5z" stroke="#d4b4e4" stroke-width="1.4"/><circle cx="8" cy="6" r="1.5" stroke="#d4b4e4" stroke-width="1.4"/></svg>
                </div>
                <input type="text" name="alamat" placeholder="Kota / Kecamatan" value="<?= htmlspecialchars($data['alamat'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="fg">
            <label class="fl">Password</label>
            <div class="inp-shell <?= isset($errors['password']) ? 'err-state' : '' ?>">
              <div class="inp-ico">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="6" width="10" height="8" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><path d="M5 6V4a3 3 0 016 0v2" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="10" r="1.2" fill="#d4b4e4"/></svg>
              </div>
              <input type="password" id="pw" name="password" placeholder="Minimal 6 karakter" oninput="strength(this.value)" required>
              <button type="button" class="eye-btn" onclick="toggleEye('pw','eye-open-pw','eye-shut-pw')">
                <svg id="eye-open-pw" width="16" height="16" viewBox="0 0 16 16" fill="none"><ellipse cx="8" cy="8" rx="7" ry="4.5" stroke="#c084d4" stroke-width="1.4"/><circle cx="8" cy="8" r="2" fill="#c084d4"/></svg>
                <svg id="eye-shut-pw" width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:none"><path d="M2 3l12 10" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M4 5.5C3 6.3 2 7.2 1 8c1.5 2.5 4 4 7 4 1 0 2-.2 2.9-.5" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M7 4.1C7.3 4 7.7 4 8 4c3 0 5.5 1.5 7 4-.4.6-.8 1.2-1.3 1.7" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
            </div>
            <?php if (isset($errors['password'])): ?>
            <div class="field-err"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['password'] ?></div>
            <?php endif; ?>
            <div class="sbar" id="sbar" style="display:none">
              <div class="ss" id="s1"></div><div class="ss" id="s2"></div>
              <div class="ss" id="s3"></div><div class="ss" id="s4"></div>
            </div>
          </div>

          <div class="fg">
            <label class="fl">Konfirmasi Password</label>
            <div class="inp-shell <?= isset($errors['konfirm']) ? 'err-state' : '' ?>">
              <div class="inp-ico">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="6" width="10" height="8" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><path d="M5 6V4a3 3 0 016 0v2" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="10" r="1.2" fill="#d4b4e4"/></svg>
              </div>
              <input type="password" id="pw2" name="konfirm" placeholder="Ulangi password" required>
              <button type="button" class="eye-btn" onclick="toggleEye('pw2','eye-open-pw2','eye-shut-pw2')">
                <svg id="eye-open-pw2" width="16" height="16" viewBox="0 0 16 16" fill="none"><ellipse cx="8" cy="8" rx="7" ry="4.5" stroke="#c084d4" stroke-width="1.4"/><circle cx="8" cy="8" r="2" fill="#c084d4"/></svg>
                <svg id="eye-shut-pw2" width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:none"><path d="M2 3l12 10" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M4 5.5C3 6.3 2 7.2 1 8c1.5 2.5 4 4 7 4 1 0 2-.2 2.9-.5" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M7 4.1C7.3 4 7.7 4 8 4c3 0 5.5 1.5 7 4-.4.6-.8 1.2-1.3 1.7" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
            </div>
            <?php if (isset($errors['konfirm'])): ?>
            <div class="field-err"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['konfirm'] ?></div>
            <?php endif; ?>
          </div>

          <div class="opts-row">
            <label class="chk-wrap">
              <input type="checkbox" name="terms" required>
              <span>Saya menyetujui <a href="#">Syarat & Ketentuan</a> CUCI.IN</span>
            </label>
          </div>

          <button type="submit" class="btn-main" id="btnSubmit">
            <span id="btnText">Daftar Sekarang</span>
            <svg class="btn-arr" width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M10 5l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </form>

        <div class="divider"></div>
        <p class="footer-note">
          Dengan mendaftar, kamu menyetujui <a href="#">Syarat &amp; Ketentuan</a> kami
        </p>

        <?php endif; ?>

      </div>
    </div>
    <div class="page-footer">
      <div class="pf-brand">
        <svg width="14" height="14" viewBox="0 0 14 14"><circle cx="7" cy="7" r="6" fill="#f5d0f9" stroke="#e0b8f5" stroke-width="1"/><circle cx="7" cy="7" r="3" fill="#e879a0"/><circle cx="7" cy="7" r="1.2" fill="#fff"/></svg>
        CUCI.IN &mdash; Powered by InforbisTechno
      </div>
      <div class="pf-links">
        <a href="#">Bantuan</a>
        <a href="#">Privasi</a>
        <a href="#">Kontak</a>
      </div>
    </div>
  </div>

</div>
</div>

<script>
function toggleEye(inputId, openId, shutId){
  const pw=document.getElementById(inputId);
  const o=document.getElementById(openId);
  const s=document.getElementById(shutId);
  if(pw.type==='password'){pw.type='text';o.style.display='none';s.style.display='';}
  else{pw.type='password';o.style.display='';s.style.display='none';}
}
function strength(v){
  const bar=document.getElementById('sbar');
  if(!v){bar.style.display='none';return;}
  bar.style.display='flex';
  let sc=0;
  if(v.length>=6)sc++;if(v.length>=10)sc++;
  if(/[A-Z]/.test(v)&&/[0-9]/.test(v))sc++;
  if(/[^A-Za-z0-9]/.test(v))sc++;
  ['s1','s2','s3','s4'].forEach((id,i)=>{
    const el=document.getElementById(id);
    el.className='ss';
    if(i<sc)el.classList.add('lv'+sc);
  });
}

document.getElementById('regForm')?.addEventListener('submit',function(){
  const btn=document.getElementById('btnSubmit');
  const txt=document.getElementById('btnText');
  btn.classList.add('loading');
  txt.innerHTML='<i class="fa-solid fa-spinner fa-spin" style="font-size:16px"></i> Memproses...';
});
</script>
</body>
</html>