<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

// BASE PATH
$base = "/";
$userBase = "/user/";

// Dashboard link
$dashboardLink = $userBase . 'dashboard.php';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboardLink = $base . 'admin/dashboard.php';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title><?= $pageTitle ?? 'CUCI.IN - Laundry Modern' ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />

<style>

/* ===== RESET ===== */
*{margin:0;padding:0;box-sizing:border-box}

/* ===== BODY ===== */
body{
  font-family:'Inter',sans-serif;
  background:linear-gradient(135deg,#fff1f7,#ffe4ec,#fbcfe8);
  overflow-x:hidden;
}

/* ===== SPLASH LOADING - ANIMASI BARU ===== */
#splash{
  position:fixed;
  inset:0;
  background:#fff0f6;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-direction:column;
  z-index:9999;
  opacity:0;
  animation: fadeIn 0.8s ease-out 0.2s forwards;
}

.splash-box{
  display:flex;
  align-items:center;
  gap:12px;
  transform: scale(0.8) translateY(-20px);
  animation: bounceIn 1s ease-out 0.4s forwards;
  position: relative;
}

.splash-box img{
  width:85px;
  filter: drop-shadow(0 0 10px rgba(0,0,0,0.1));
  position: relative;
  z-index: 2;
}

/* PARTIKEL DI BELAKANG LOGO - KE KIRI */
.logo-orbit{
  position: absolute;
  width: 60px;
  height: 60px;
  top: 50%;
  left: 20%; /* KE KIRI LOGO */
  transform: translate(-20%, -50%);
  border-radius: 50%;
  z-index: 1; /* DI BELAKANG LOGO */
}

.orbit-particle{
  position: absolute;
  width: 4px;
  height: 4px;
  background: linear-gradient(45deg, #ec4899, #f472b6);
  border-radius: 50%;
  animation: tightOrbitLeft 3s linear infinite;
  box-shadow: 0 0 6px rgba(236, 72, 137, 0.6);
}

/* 8 POSISI PARTIKEL DI BELAKANG - KE KIRI */
.orbit-particle:nth-child(1) { top: 10%; left: 50%; transform: translateX(-50%); animation-delay: 0s; }
.orbit-particle:nth-child(2) { top: 25%; right: 20%; animation-delay: -0.4s; }
.orbit-particle:nth-child(3) { bottom: 25%; right: 20%; animation-delay: -0.8s; }
.orbit-particle:nth-child(4) { bottom: 10%; left: 50%; transform: translateX(-50%); animation-delay: -1.2s; }
.orbit-particle:nth-child(5) { bottom: 25%; left: 20%; animation-delay: -1.6s; }
.orbit-particle:nth-child(6) { top: 25%; left: 20%; animation-delay: -2s; }
.orbit-particle:nth-child(7) { top: 40%; right: 30%; animation-delay: -2.4s; }
.orbit-particle:nth-child(8) { bottom: 40%; left: 30%; animation-delay: -2.8s; }

.splash-text{
  font-size:2.2rem;
  font-weight:800;
  background:linear-gradient(90deg,#ec4899,#f472b6);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  opacity:0;
  animation: textSlideUp 0.8s ease-out 0.8s forwards;
}

/* PROGRESS BAR + PERSEN */
.progress-container{
  margin-top:25px;
  width:200px;
  text-align:center;
}

.progress-bar{
  width:100%;
  height:6px;
  background:#f3f4f6;
  border-radius:12px;
  overflow:hidden;
  position:relative;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.progress-fill{
  height:100%;
  width:0%;
  background:linear-gradient(90deg, #ec4899, #f472b6);
  border-radius:12px;
  transition: width 0.3s ease;
  position:relative;
}

.progress-fill::after{
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
  animation: shine 2s infinite;
}

.progress-text{
  margin-top: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #666;
  letter-spacing: 1px;
}

/* ANIMASI KEYFRAMES */
@keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
@keyframes bounceIn {
  0% {opacity: 0; transform: scale(0.8) translateY(-20px);}
  50% {opacity: 0.8; transform: scale(1.05) translateY(-10px);}
  100% {opacity: 1; transform: scale(1) translateY(0);}
}
@keyframes textSlideUp {
  0% {opacity: 0; transform: translateY(20px);}
  100% {opacity: 1; transform: translateY(0);}
}
@keyframes tightOrbitLeft {
  0% { transform: rotate(0deg) translateX(22px) rotate(0deg) scale(1); }
  100% { transform: rotate(360deg) translateX(22px) rotate(-360deg) scale(1); }
}
@keyframes shine {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
@keyframes splashFadeOut {
  from { opacity: 1; transform: scale(1); }
  to { opacity: 0; transform: scale(0.9) translateY(-20px); }
}

/* SPLASH FADE OUT */
#splash.fade-out { animation: splashFadeOut 0.8s ease-out forwards; }

/* ===== PARTICLES ===== */
.particle{
  position:absolute;
  width:6px;
  height:6px;
  background:#f472b6;
  border-radius:50%;
  opacity:.3;
  animation:float 10s linear infinite;
}
@keyframes float{ from{transform:translateY(100vh);} to{transform:translateY(-10vh);} }

/* ===== GEOFLOWER (Gelembung) ===== */
.bubble {
  position: absolute;
  bottom: 0;
  background: rgba(236, 72, 137, 0.7);
  border-radius: 50%;
  opacity: 0;
  animation: rise 4s forwards;
}
@keyframes rise {
  0% { transform: translateY(0) scale(0.5); opacity: 0; }
  30% { opacity: 1; }
  100% { transform: translateY(-300px) scale(1); opacity: 0; }
}

/* ===== NAVBAR ===== */
.navbar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:20px 40px;
  background:rgba(255,255,255,0.7);
  backdrop-filter:blur(15px);
  border-bottom:1px solid white;
  position:sticky;
  top:0;
  z-index:100;
}

.brand{
  display:flex;
  align-items:center;
  gap:10px;
}

.brand img{
  width:38px;
}

.brand span{
  font-weight:800;
  font-size:1.3rem;
  background:linear-gradient(90deg,#ec4899,#f472b6);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
}

/* ===== BUTTON ===== */
.btn{
  padding:10px 18px;
  border-radius:10px;
  text-decoration:none;
  font-weight:600;
  transition:0.3s;
}

.btn-primary{
  background:linear-gradient(90deg,#ec4899,#f472b6);
  color:white;
}

.btn-primary:hover{
  transform:scale(1.05);
}

.btn-outline{
  border:1px solid #ec4899;
  color:#ec4899;
}

.btn-outline:hover{
  background:#ec4899;
  color:white;
}

/* ===== RESPONSIVE ===== */
@media(max-width:768px){
  .navbar{
    padding:15px 20px;
  }
}

</style>
</head>

<body>

<!-- SPLASH -->
<div id="splash">
  <div class="splash-box">
    <!-- 8 PARTIKEL DI BELAKANG LOGO - KE KIRI -->
    <div class="logo-orbit">
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
      <div class="orbit-particle"></div>
    </div>
    <img src="<?= $base ?>img/logo.png" />
    <div class="splash-text">CUCI.IN</div>
  </div>
  
  <!-- PROGRESS BAR + PERSEN -->
  <div class="progress-container">
    <div class="progress-bar">
      <div class="progress-fill" id="progressFill"></div>
    </div>
    <div class="progress-text" id="progressText">0%</div>
  </div>
</div>

<!-- PARTICLES -->
<?php for($i=0;$i<30;$i++): ?>
<div class="particle" style="left:<?= rand(0,100) ?>%; animation-duration:<?= rand(5,12) ?>s; animation-delay:<?= rand(0,5) ?>s;"></div>
<?php endfor; ?>

<!-- GEBLEMBUNG (Gelembung) -->
<script>
  function createBubble() {
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    const size = Math.random() * 20 + 10;
    bubble.style.width = size + 'px';
    bubble.style.height = size + 'px';
    bubble.style.left = Math.random() * 100 + '%';
    bubble.style.animationDuration = (4 + Math.random() * 4) + 's';
    document.body.appendChild(bubble);
    setTimeout(() => {
      bubble.remove();
    }, 8000);
  }

  setInterval(createBubble, 300);
</script>

<!-- NAVBAR -->
<div class="navbar">
  <div class="brand">
    <img src="<?= $base ?>img/logo.png" />
    <span>CUCI.IN</span>
  </div>

  <div>
    <?php if(isLoggedIn()): ?>
      <a href="<?= $dashboardLink ?>" class="btn btn-outline">Dashboard</a>
      <a href="<?= $userBase ?>logout.php" class="btn btn-primary">Keluar</a>
    <?php else: ?>
      <a href="<?= $userBase ?>login.php" class="btn btn-outline">Masuk</a>
      <a href="<?= $userBase ?>register.php" class="btn btn-primary">Daftar</a>
    <?php endif; ?>
  </div>
</div>

<!-- PROGRESS ANIMATION + FADE OUT -->
<script>
let progress = 0;
const progressFill = document.getElementById('progressFill');
const progressText = document.getElementById('progressText');

const progressInterval = setInterval(() => {
  progress += 2;
  progressFill.style.width = progress + '%';
  progressText.textContent = progress + '%';
  
  if (progress >= 100) {
    clearInterval(progressInterval);
    setTimeout(() => {
      const splash = document.getElementById('splash');
      splash.classList.add('fade-out');
      
      setTimeout(() => {
        splash.style.display = 'none';
      }, 800);
    }, 500);
  }
}, 80); // ~5 DETIK TOTAL
</script>

</body>
</html>