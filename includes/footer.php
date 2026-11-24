<?php // includes/footer.php ?>
<footer class="footer">
    <div class="footer-inner">

        <!-- BRAND -->
        <div class="footer-col">
            <div class="footer-brand">
              <img src="img/logo.png" class="footer-logo" alt="Logo CUCI.IN">
                <span>CUCI.IN</span>
            </div>
            <p class="footer-desc">
                Platform laundry digital modern yang menghadirkan kemudahan layanan cuci, setrika, 
                dan self-service langsung dari genggaman Anda.
            </p>

            <!-- SOCIAL -->
            <div class="footer-social">
                <a aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a aria-label="Chat"><i class="fas fa-comment-dots"></i></a>
            </div>
        </div>

        <!-- LAYANAN -->
        <div class="footer-col">
            <h4 class="footer-title">Layanan</h4>
            <ul class="footer-links">
                <li><a>Jasa Laundry</a></li>
                <li><a>Self Service</a></li>
                <li><a>Keranjang</a></li>
                <li><a>Tracking Pesanan</a></li>
            </ul>
        </div>

        <!-- AKUN -->
        <div class="footer-col">
            <h4 class="footer-title">Akun</h4>
            <ul class="footer-links">
                <li><a>Masuk</a></li>
                <li><a>Daftar</a></li>
                <li><a>Dashboard</a></li>
            </ul>
        </div>

        <!-- INFO -->
        <div class="footer-col">
            <h4 class="footer-title">Info</h4>
            <ul class="footer-links">
                <li><a>Tentang Kami</a></li>
                <li><a>Cara Kerja</a></li>
                <li><a>Kontak</a></li>
                <li><a>FAQ</a></li>
            </ul>
        </div>

    </div>

    <!-- BOTTOM -->
    <div class="footer-bottom">
        <span>© <?= date('Y') ?> CUCI.IN. All rights reserved.</span>
    </div>
</footer>

<!-- JS -->
<script>
function toggleMenu() {
    const links = document.querySelector('.navbar-links');
    links && links.classList.toggle('mobile-open');
}

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = (type==='success'?'✅':'❌') + ' ' + msg;
    document.body.appendChild(t);

    setTimeout(() => t.classList.add('show'), 100);
    setTimeout(() => t.classList.remove('show'), 3000);
    setTimeout(() => t.remove(), 3500);
}
</script>

<!-- STYLE -->
<style>
.footer {
    margin-top: 60px;
    background-color: #ffffff;
    padding: 60px 40px 20px;
    border-top: 1px solid #ffffff;
}

.footer-inner{
    max-width:1200px;
    margin:auto;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:40px;
}

.footer-brand{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:1.4rem;
    font-weight:800;
    margin-bottom:10px;
}

.footer-logo{ width:35px; }

.footer-brand span{
    background:linear-gradient(90deg,#ec4899,#f472b6);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}

.footer-desc{
    font-size:0.9rem;
    color:#6b7280;
    line-height:1.6;
}

.footer-social{
    margin-top:15px;
    display:flex;
    gap:15px;
}

.footer-social a {
    display:flex;
    align-items:center;
    justify-content:center;
    width:40px;
    height:40px;
    background:white;
    border-radius:50%;
    color:#6b7280;
    font-size:1.2rem;
    transition:0.3s;
    text-decoration: none;
    cursor: default;
}

.footer-social a:hover {
    background:#ec4899;
    color:white;
    transform:scale(1.1);
}

.footer-title{
    font-size:1rem;
    font-weight:700;
    margin-bottom:10px;
}

.footer-links{
    list-style:none;
}

.footer-links li{
    margin-bottom:6px;
}

.footer-links a{
    text-decoration:none;
    color:#6b7280;
    font-size:0.9rem;
    transition:0.3s;
    cursor: default;
}

.footer-links a:hover{
    color:#ec4899;
    padding-left:5px;
}

.footer-bottom {
    margin-top:40px;
    padding:15px;
    display:flex;
    justify-content: center;
    font-size:0.85rem;
    color:#6b7280;
    background-color: #ffffff;
}

@media(max-width:768px){
    .footer-bottom{
        flex-direction:column;
        gap:5px;
        text-align:center;
    }
}
</style>