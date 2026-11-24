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

 $error = '';
 $success = '';
 $step = 1; // 1 = input email, 2 = ganti password, 3 = success
 $verifiedEmail = '';

// === PROSES POST DULU SEBELUM CAPTCHA DIGENERATE ULANG ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // === STEP 1: Verifikasi Email + Captcha ===
    if ($action === 'verify_email') {
        $email = trim($_POST['email'] ?? '');
        $captcha = trim($_POST['captcha'] ?? '');

        if (empty($email)) {
            $error = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (!isset($_SESSION['reset_captcha']) || $captcha != $_SESSION['reset_captcha']) {
            $error = 'Jawaban captcha salah. Coba hitung lagi.';
        } else {
            // Cek email di database
            $stmt = $db->prepare("SELECT id_user, nama FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user) {
                $step = 2;
                $verifiedEmail = $email;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_id'] = $user['id_user'];
            } else {
                $error = 'Email tidak terdaftar di sistem CUCI.IN.';
            }
        }
    }

    // === STEP 2: Ganti Password ===
    if ($action === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $konfirm = $_POST['konfirm'] ?? '';

        if (empty($password) || empty($konfirm)) {
            $error = 'Kedua field password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($password !== $konfirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } elseif (!isset($_SESSION['reset_email'])) {
            $error = 'Sesi tidak valid. Silakan ulangi dari awal.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $userId = (int)$_SESSION['reset_user_id'];
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id_user = ?");
            $stmt->bind_param("si", $hash, $userId);

            if ($stmt->execute()) {
                $success = 'Password berhasil diubah! Silakan masuk dengan password baru.';
                $step = 3; // success state
                
                // Bersihkan session reset
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_captcha']);
            } else {
                $error = 'Gagal mengubah password. Silakan coba lagi.';
            }
        }
    }
}

// === GENERATE CAPTCHA DI SINI (SETELAH POST) ===
// Kalau masih di step 1 (ada error atau baru buka), generate captcha baru
if ($step === 1) {
    $capA = rand(1, 10);
    $capB = rand(1, 10);
    $capOp = rand(0, 1); // 0 = tambah, 1 = kurang
    $capResult = $capOp === 0 ? $capA + $capB : $capA - $capB;
    $capSymbol = $capOp === 0 ? '+' : '-';
    $capQuestion = "$capA $capSymbol $capB = ?";
    $_SESSION['reset_captcha'] = $capResult;
}

 $pageTitle = 'Lupa Password — CUCI.IN';
?>
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
  padding:2rem 2.5rem;position:relative;z-index:1;
  overflow-y:auto;
}
.form-wrap{width:100%;max-width:370px;}

.back-link{
  display:inline-flex;align-items:center;gap:6px;
  font-size:12px;font-weight:700;color:#c084d4;
  background:none;border:none;cursor:pointer;padding:0;margin-bottom:1.75rem;
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
.form-sub{font-size:13px;color:#b4a0c4;margin-bottom:1.5rem;line-height:1.6;}
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
  background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:14px;
  padding:20px 18px;margin-bottom:1rem;
  animation:okPop 0.5s cubic-bezier(.16,1,.3,1);
}
@keyframes okPop{from{opacity:0;transform:scale(0.95) translateY(-8px)}to{opacity:1;transform:scale(1) translateY(0)}}

.ok-box-icon{
  width:50px;height:50px;border-radius:50%;
  background:linear-gradient(135deg,#22c55e,#16a34a);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 14px;
  animation:okBounce 0.6s 0.2s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes okBounce{from{transform:scale(0)}to{transform:scale(1)}}
.ok-box-icon svg{width:24px;height:24px;}

.ok-box-title{
  text-align:center;font-size:16px;font-weight:800;color:#15803d;
  margin-bottom:6px;
}
.ok-box-desc{
  text-align:center;font-size:13px;color:#4ade80;
  line-height:1.5;
}

/* Step indicator */
.steps{
  display:flex;align-items:center;gap:0;margin-bottom:1.5rem;
}
.step-item{
  display:flex;align-items:center;gap:8px;
}
.step-dot{
  width:28px;height:28px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:800;
  border:2px solid #f0d4f8;color:#d4b4e4;
  background:#fff;transition:all 0.3s;
}
.step-dot.active{border-color:#a855b5;background:#a855b5;color:#fff;}
.step-dot.done{border-color:#22c55e;background:#22c55e;color:#fff;}
.step-label{font-size:11px;font-weight:700;color:#d4b4e4;transition:color 0.3s;}
.step-label.active{color:#7c3d8f;}
.step-label.done{color:#22c55e;}
.step-line{
  width:40px;height:2px;background:#f0d4f8;margin:0 6px;transition:background 0.3s;
}
.step-line.done{background:#22c55e;}

.fg{margin-bottom:0.9rem;}
.fl{
  font-size:12px;font-weight:700;color:#6b21a8;
  margin-bottom:5px;
}

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
  padding:0.72rem 0.7rem;font-size:14px;font-weight:500;
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

/* Captcha */
.captcha-row{
  display:flex;align-items:center;gap:10px;
}
.captcha-box{
  flex:1;
  display:flex;align-items:center;
  border:1.5px solid #f0d4f8;border-radius:13px;
  background:#fdf4fb;overflow:hidden;
  transition:border-color 0.2s,background 0.2s;
}
.captcha-box:focus-within{border-color:#c084d4;background:#fff;box-shadow:0 0 0 4px rgba(192,132,212,0.12);}
.captcha-box.err-state{border-color:#f9a8d4 !important;background:#fff0f6 !important;}
.captcha-box .inp-ico{
  width:42px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.captcha-box input{
  flex:1;border:none;background:transparent;
  padding:0.72rem 0.7rem;font-size:14px;font-weight:500;
  color:#4a1d6e;outline:none;font-family:'Nunito',sans-serif;
}
.captcha-box input::placeholder{color:#d4b4e4;}
.captcha-display{
  background:linear-gradient(135deg,#7c3d8f,#a855b5);
  color:#fff;padding:10px 16px;border-radius:13px;
  font-size:18px;font-weight:800;letter-spacing:2px;
  user-select:none;min-width:100px;text-align:center;
  font-family:'Playfair Display',serif;
  position:relative;overflow:hidden;
}
.captcha-display::after{
  content:'';position:absolute;inset:0;
  background:repeating-linear-gradient(
    45deg,
    transparent,
    transparent 3px,
    rgba(255,255,255,0.08) 3px,
    rgba(255,255,255,0.08) 6px
  );
}
.captcha-label{
  font-size:11px;color:#c084d4;font-weight:600;
  margin-bottom:5px;display:flex;align-items:center;gap:4px;
}
.captcha-label i{font-size:10px;}

.field-err{
  font-size:11px;color:#be185d;margin-top:4px;
  display:flex;align-items:center;gap:4px;
}

/* Email confirmed chip */
.email-chip{
  display:inline-flex;align-items:center;gap:6px;
  background:#f0fdf4;border:1.5px solid #bbf7d0;
  color:#15803d;font-size:12px;font-weight:700;
  padding:6px 14px;border-radius:20px;margin-bottom:1rem;
}
.email-chip i{color:#22c55e;}

.sbar{display:flex;gap:4px;margin-top:5px;}
.ss{flex:1;height:3px;border-radius:4px;background:#f0d4f8;transition:background 0.3s;}
.ss.lv1{background:#fbcfe8;}
.ss.lv2{background:#f0abfc;}
.ss.lv3{background:#c084d4;}
.ss.lv4{background:#a855b5;}

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

.divider{height:1px;background:#f5e8fc;margin:1.4rem 0;}

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

/* Animation for step 2 */
.step-anim{
  animation:stepFade 0.5s cubic-bezier(.16,1,.3,1);
}
@keyframes stepFade{
  from{opacity:0;transform:translateY(15px)}
  to{opacity:1;transform:none}
}

@media(max-width:700px){
  .layout{grid-template-columns:1fr;}
  .left{display:none;}
  .form-area{padding:2rem 1.5rem;}
  .page-footer{flex-direction:column;gap:8px;text-align:center;}
  .captcha-row{flex-direction:column;}
  .captcha-display{min-width:auto;width:100%;}
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
          Lupa<br><em>Password?</em><br>Kami<br>Bantu.
        </h1>
        <p class="hero-desc">Verifikasi email, buat password baru — selesai dalam hitungan detik.</p>
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

        <a href="login.php" class="back-link">
          <span class="back-arrow">←</span> Kembali ke Masuk
        </a>

        <div class="greeting-chip">
          <div class="pill-dot"></div>
          Reset Password
        </div>

        <!-- STEP INDICATOR -->
        <div class="steps">
          <div class="step-item">
            <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
              <?= $step > 1 ? '<i class="fa-solid fa-check" style="font-size:11px"></i>' : '1' ?>
            </div>
            <span class="step-label <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">Verifikasi</span>
          </div>
          <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
          <div class="step-item">
            <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
              <?= $step > 2 ? '<i class="fa-solid fa-check" style="font-size:11px"></i>' : '2' ?>
            </div>
            <span class="step-label <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">Password Baru</span>
          </div>
        </div>

        <?php if ($step === 1): ?>
        <!-- ========== STEP 1: EMAIL + CAPTCHA ========== -->
        <h1 class="form-title">Lupa Password</h1>
        <p class="form-sub">Masukkan email terdaftar dan jawab captcha untuk verifikasi.</p>

        <?php if ($error): ?>
        <div class="err-box">
          <div class="err-icon">
            <svg width="10" height="10" viewBox="0 0 10 10"><line x1="2" y1="2" x2="8" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="2" x2="2" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="step1Form" novalidate>
          <input type="hidden" name="action" value="verify_email">

          <div class="fg">
            <label class="fl">Alamat Email</label>
            <div class="inp-shell <?= $error ? 'err-state' : '' ?>">
              <div class="inp-ico">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><path d="M1 5l7 5 7-5" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/></svg>
              </div>
              <input type="email" name="email" placeholder="email@kamu.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
            </div>
          </div>

          <div class="fg">
            <div class="captcha-label"><i class="fa-solid fa-shield-halved"></i> Jawab captcha di bawah</div>
            <div class="captcha-row">
              <div class="captcha-box <?= $error ? 'err-state' : '' ?>">
                <div class="inp-ico">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1L1 5v3c0 4 3 6.5 7 7 4-.5 7-3 7-7V5L8 1z" stroke="#d4b4e4" stroke-width="1.4" stroke-linejoin="round"/></svg>
                </div>
                <input type="text" name="captcha" placeholder="Ketik jawaban" required autocomplete="off">
              </div>
              <div class="captcha-display"><?= $capQuestion ?></div>
            </div>
          </div>

          <button type="submit" class="btn-main" id="btnSubmit" style="margin-top:0.5rem;">
            <span id="btnText">Verifikasi Email</span>
            <svg class="btn-arr" width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M10 5l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </form>

        <div class="divider"></div>
        <p class="footer-note">
          Ingat passwordmu? <a href="login.php">Masuk langsung</a>
        </p>

        <?php elseif ($step === 2): ?>
        <!-- ========== STEP 2: PASSWORD BARU ========== -->
        <div class="step-anim">
          <h1 class="form-title">Buat Password Baru</h1>
          <p class="form-sub">Buat password baru untuk akun <strong style="color:#7c3d8f"><?= htmlspecialchars($verifiedEmail) ?></strong></p>

          <div class="email-chip">
            <i class="fa-solid fa-circle-check"></i>
            Email terverifikasi
          </div>

          <?php if ($error): ?>
          <div class="err-box">
            <div class="err-icon">
              <svg width="10" height="10" viewBox="0 0 10 10"><line x1="2" y1="2" x2="8" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="2" x2="2" y2="8" stroke="#be185d" stroke-width="1.5" stroke-linecap="round"/></svg>
            </div>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
          <?php endif; ?>

          <form method="POST" action="" id="step2Form" novalidate>
            <input type="hidden" name="action" value="reset_password">

            <div class="fg">
              <label class="fl">Password Baru</label>
              <div class="inp-shell <?= $error ? 'err-state' : '' ?>">
                <div class="inp-ico">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="6" width="10" height="8" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><path d="M5 6V4a3 3 0 016 0v2" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="10" r="1.2" fill="#d4b4e4"/></svg>
                </div>
                <input type="password" id="pw" name="password" placeholder="Minimal 6 karakter" oninput="strength(this.value)" required>
                <button type="button" class="eye-btn" onclick="toggleEye('pw','eo1','es1')">
                  <svg id="eo1" width="16" height="16" viewBox="0 0 16 16" fill="none"><ellipse cx="8" cy="8" rx="7" ry="4.5" stroke="#c084d4" stroke-width="1.4"/><circle cx="8" cy="8" r="2" fill="#c084d4"/></svg>
                  <svg id="es1" width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:none"><path d="M2 3l12 10" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M4 5.5C3 6.3 2 7.2 1 8c1.5 2.5 4 4 7 4 1 0 2-.2 2.9-.5" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M7 4.1C7.3 4 7.7 4 8 4c3 0 5.5 1.5 7 4-.4.6-.8 1.2-1.3 1.7" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/></svg>
                </button>
              </div>
              <div class="sbar" id="sbar" style="display:none">
                <div class="ss" id="s1"></div><div class="ss" id="s2"></div>
                <div class="ss" id="s3"></div><div class="ss" id="s4"></div>
              </div>
            </div>

            <div class="fg">
              <label class="fl">Ulangi Password Baru</label>
              <div class="inp-shell <?= $error ? 'err-state' : '' ?>">
                <div class="inp-ico">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="6" width="10" height="8" rx="2" stroke="#d4b4e4" stroke-width="1.4"/><path d="M5 6V4a3 3 0 016 0v2" stroke="#d4b4e4" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="10" r="1.2" fill="#d4b4e4"/></svg>
                </div>
                <input type="password" id="pw2" name="konfirm" placeholder="Ulangi password baru" required>
                <button type="button" class="eye-btn" onclick="toggleEye('pw2','eo2','es2')">
                  <svg id="eo2" width="16" height="16" viewBox="0 0 16 16" fill="none"><ellipse cx="8" cy="8" rx="7" ry="4.5" stroke="#c084d4" stroke-width="1.4"/><circle cx="8" cy="8" r="2" fill="#c084d4"/></svg>
                  <svg id="es2" width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:none"><path d="M2 3l12 10" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M4 5.5C3 6.3 2 7.2 1 8c1.5 2.5 4 4 7 4 1 0 2-.2 2.9-.5" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/><path d="M7 4.1C7.3 4 7.7 4 8 4c3 0 5.5 1.5 7 4-.4.6-.8 1.2-1.3 1.7" stroke="#c084d4" stroke-width="1.4" stroke-linecap="round"/></svg>
                </button>
              </div>
            </div>

            <button type="submit" class="btn-main" id="btnSubmit" style="margin-top:0.5rem;">
              <span id="btnText">Simpan Password Baru</span>
              <svg class="btn-arr" width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M10 5l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </form>

          <div class="divider"></div>
          <p class="footer-note">
            <a href="resetpass.php"><i class="fa-solid fa-arrow-left"></i> Ulangi dari awal</a>
          </p>
        </div>

        <?php elseif ($step === 3): ?>
        <!-- ========== STEP 3: SUCCESS ========== -->
        <div class="step-anim" style="text-align:center;padding-top:0.5rem;">
          <div class="ok-box">
            <div class="ok-box-icon">
              <svg viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L19 7" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="ok-box-title">Password Berhasil Diubah!</div>
            <div class="ok-box-desc"><?= htmlspecialchars($success) ?></div>
          </div>

          <a href="login.php" class="btn-main" style="text-decoration:none;">
            <span>Masuk Sekarang</span>
            <svg class="btn-arr" width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M10 5l4 4-4 4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>

          <div class="divider"></div>
          <p class="footer-note">
            <a href="<?= APP_URL ?>"><i class="fa-solid fa-house"></i> Kembali ke Beranda</a>
          </p>
        </div>
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
  if(!bar){return;}
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

document.getElementById('step1Form')?.addEventListener('submit',function(){
  const btn=document.getElementById('btnSubmit');
  const txt=document.getElementById('btnText');
  btn.classList.add('loading');
  txt.innerHTML='<i class="fa-solid fa-spinner fa-spin" style="font-size:16px"></i> Memverifikasi...';
});
document.getElementById('step2Form')?.addEventListener('submit',function(){
  const btn=document.getElementById('btnSubmit');
  const txt=document.getElementById('btnText');
  btn.classList.add('loading');
  txt.innerHTML='<i class="fa-solid fa-spinner fa-spin" style="font-size:16px"></i> Menyimpan...';
});
</script>
</body>
</html>