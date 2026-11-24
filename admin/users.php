<?php 
// Hapus tag <?php ganda di awal jika ada 
ini_set('display_errors', 1); 
error_reporting(E_ALL); 
 
/*  
 * KONFIGURASI DATABASE HOSTING 
 * Pastikan nama database 'if0_41877538_laundry' SAMA PERSIS dengan panel Anda. 
 */ 
 $host = 'sql113.infinityfree.com'; 
 $user = 'if0_41877538'; 
 $pass = 'iselynluvina'; 
 $dbname = 'if0_41877538_laundry'; 
 
// koneksi database 
 $db = new mysqli($host, $user, $pass, $dbname); 
 
// cek koneksi 
if ($db->connect_error) { 
    die("Koneksi Database Gagal: " . $db->connect_error); 
} 
 
// Aktifkan mode lapor error mysqli sebagai Exception (agar lebih mudah ditangkap) 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
 
// session 
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 
 
// base url hosting 
define('APP_URL', 'https://cuciin.xo.je'); 
 
 
// ===== HELPER FUNCTIONS ===== 
 
// Login check 
function requireLogin() { 
    if (!isset($_SESSION['user_id'])) { 
        header('Location: ' . APP_URL . '/user/login.php'); 
        exit; 
    } 
} 
 
// Admin check 
function requireAdmin() { 
    $role = $_SESSION['role'] ?? $_SESSION['level'] ?? ''; 
    if ($role !== 'admin') { 
        die("<h3>Akses Ditolak</h3>Halaman ini khusus Administrator."); 
    } 
} 
 
// Format rupiah 
function formatRupiah($angka) { 
    return 'Rp ' . number_format($angka, 0, ',', '.'); 
} 
 
// Flash message 
function setFlash($type, $message) { 
    $_SESSION['flash'] = [ 
        'type' => $type, 
        'message' => $message 
    ]; 
} 
 
function getFlash() { 
    if (isset($_SESSION['flash'])) { 
        $flash = $_SESSION['flash']; 
        unset($_SESSION['flash']); 
        return $flash; 
    } 
    return null; 
} 
 
requireLogin(); 
requireAdmin(); 
 
 $flash = getFlash(); 
 
// --- PEMETAAN KOLOM DATABASE --- 
 $uIdCol    = 'id_user'; 
 $uNameCol  = 'nama'; 
 $uEmailCol = 'email'; 
 $uPhoneCol = 'no_hp';       
 $uAddrCol  = 'alamat'; 
 $uRoleCol  = 'role'; 
 $uDateCol  = 'created_at'; 
 
// Tabel Orders 
 $oColsRaw = $db->query("SHOW COLUMNS FROM orders"); 
 $oCols = []; 
if ($oColsRaw) { 
    $oCols = array_column($oColsRaw->fetch_all(MYSQLI_ASSOC), 'Field'); 
} 
 
 $oIdCol    = pick($oCols, ['id_order','order_id','id']); 
 $oUserCol  = pick($oCols, ['id_user','user_id','customer_id']); 
 $oStatusCol = pick($oCols, ['status','order_status','state']); 
 $oDateCol  = pick($oCols, ['tanggal_order','created_at','tgl_order','tanggal','date_created','created','order_date']); 
 $oTotalCol = pick($oCols, ['total_harga','total','total_bayar','grand_total','amount']); 
 $oKode   = in_array('kode_order', $oCols) ? 'kode_order' : ''; 
 $oMetode = in_array('metode', $oCols) ? 'metode' : ''; 
 
// Tabel Order Detail 
 $hasOD = tableExists($db, 'order_detail'); 
 $odTable = 'order_detail'; 
 $odCols = $hasOD ? cols($db, $odTable) : []; 
 $odOrdCol = pick($odCols, ['id_order','order_id']); 
 $odQty   = pick($odCols, ['qty','jumlah','quantity']); 
 $odSub   = pick($odCols, ['subtotal','sub_total','harga_total','amount']); 
 $odPrice = pick($odCols, ['harga_satuan','harga']); 
 
// Tabel Payments 
 $hasPay = tableExists($db, 'pembayaran'); 
 $payTable = 'pembayaran'; 
 
// Functions 
function sq($db, $sql) { 
    try { $r = $db->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : []; } 
    catch (Exception $e) { return []; } 
} 
function sv($db, $sql, $def = 0) { 
    try { $r = $db->query($sql); if ($r && $row = $r->fetch_assoc()) return (float)$row['t']; } 
    catch (Exception $e) {} return $def; 
} 
function pick($arr, $candidates) { 
    if (!is_array($arr)) return $candidates[0]; 
    foreach ($candidates as $c) if (in_array($c, $arr)) return $c; 
    return $candidates[0]; 
} 
function tableExists($db, $name) { 
    try { $r = $db->query("SHOW TABLES LIKE '$name'"); return $r && $r->num_rows > 0; } 
    catch (Exception $e) { return false; } 
} 
function cols($db, $table) { 
    try { $r = $db->query("SHOW COLUMNS FROM $table"); return $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'Field') : []; } 
    catch (Exception $e) { return []; } 
} 
 
 $canQuery = true;  
 
// ===== HANDLE POST (Add/Edit User) ===== 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) { 
    $id     = (int)($_POST['id'] ?? 0); 
    $nama   = trim($_POST['nama'] ?? ''); 
    $email  = trim($_POST['email'] ?? ''); 
    $phone  = trim($_POST['telepon'] ?? ''); 
    $role   = $_POST['role'] ?? 'user'; 
    $alamat = trim($_POST['alamat'] ?? ''); 
 
    if (!$nama) { 
        setFlash('danger', 'Nama wajib diisi.'); 
    } elseif ($id) { 
        try { 
            $sqlUpdate = "UPDATE users SET nama=?, email=?, no_hp=?, alamat=?, role=? WHERE $uIdCol=?"; 
            $stmt = $db->prepare($sqlUpdate); 
            $stmt->bind_param("sssssi", $nama, $email, $phone, $alamat, $role, $id); 
            if ($stmt->execute()) { 
                setFlash('success', 'User berhasil diperbarui.'); 
            } 
            $stmt->close(); 
        } catch (Exception $e) { 
            setFlash('danger', 'Gagal update: ' . $e->getMessage()); 
        } 
    } else { 
        try { 
            $cekEmail = $db->query("SELECT COUNT(*) as c FROM users WHERE $uEmailCol='" . $db->real_escape_string($email) . "'"); 
            if ($cekEmail && $cekEmail->fetch_assoc()['c'] > 0) { 
                setFlash('danger', 'Email sudah terdaftar.'); 
            } else { 
                $passwordDefault = '123';  
                $sqlInsert = "INSERT INTO users (nama, email, password, no_hp, alamat, role) VALUES (?, ?, ?, ?, ?, ?)"; 
                $stmt = $db->prepare($sqlInsert); 
                $stmt->bind_param("ssssss", $nama, $email, $passwordDefault, $phone, $alamat, $role); 
                if ($stmt->execute()) { 
                    setFlash('success', 'User baru berhasil ditambahkan.'); 
                } 
                $stmt->close(); 
            } 
        } catch (Exception $e) { 
            setFlash('danger', 'Gagal menambahkan: ' . $e->getMessage()); 
        } 
    } 
    header('Location: users.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '')); 
    exit; 
} 
 
// ===== HANDLE DELETE ===== 
if (isset($_GET['delete'])) { 
    $delId = (int)$_GET['delete']; 
    $currentUserId = $_SESSION['user_id'] ?? 0; 
    if ($delId != $currentUserId) { 
        try { 
            $db->query("DELETE FROM users WHERE $uIdCol=$delId"); 
            setFlash('success', 'User berhasil dihapus.'); 
        } catch (Exception $e) { 
            setFlash('danger', 'Gagal menghapus: ' . $e->getMessage()); 
        } 
    } else { 
        setFlash('danger', 'Anda tidak bisa menghapus akun sendiri.'); 
    } 
    header('Location: users.php'); 
    exit; 
} 
 
// ===== FILTER & SEARCH ===== 
 $filterRole = $_GET['filter'] ?? ''; 
 $searchQ = trim($_GET['q'] ?? ''); 
 
// ===== FETCH USERS ===== 
 $allUsers = []; 
if ($canQuery && !($isAjax ?? false)) { 
    try { 
        $wp = [];  
        $params = [];  
        $types = "";  
 
        if ($filterRole) { 
            $wp[] = "u.$uRoleCol=?"; 
            $params[] = $filterRole; 
            $types .= "s"; 
        } 
         
        if ($searchQ) { 
            $wp[] = "(u.$uNameCol LIKE ? OR u.$uEmailCol LIKE ? OR u.$uPhoneCol LIKE ?)"; 
            $searchTerm = "%$searchQ%"; 
            $params[] = $searchTerm; 
            $params[] = $searchTerm; 
            $params[] = $searchTerm; 
            $types .= "sss"; 
        } 
 
        $wSql = !empty($wp) ? 'WHERE ' . implode(' AND ', $wp) : ''; 
 
        $sql = "SELECT u.*, 
                (SELECT COUNT(*) FROM orders o WHERE o.$oUserCol=u.$uIdCol) as total_orders, 
                (SELECT COALESCE(SUM(od.$odSub),0) 
                 FROM orders o JOIN order_detail od ON o.$oIdCol = od.$odOrdCol 
                 WHERE o.$oUserCol = u.$uIdCol AND o.$oStatusCol NOT IN ('batal','dibatalkan') 
                ) as total_revenue 
                FROM users u  
                $wSql  
                ORDER BY u.$uIdCol DESC LIMIT 300"; 
 
        if (!empty($params)) { 
            $stmt = $db->prepare($sql); 
            $stmt->bind_param($types, ...$params); 
            $stmt->execute(); 
            $result = $stmt->get_result(); 
            if ($result) $allUsers = $result->fetch_all(MYSQLI_ASSOC); 
            $stmt->close(); 
        } else { 
            $uq = $db->query($sql); 
            if ($uq) $allUsers = $uq->fetch_all(MYSQLI_ASSOC); 
        } 
    } catch (Exception $e) {} 
} 
 
// ===== STATS ===== 
 $totalUsers = 0; $cAdmin = 0; $cUser = 0; $cNewToday = 0; 
if ($canQuery && !($isAjax ?? false)) { 
    try { 
        $totalUsers = (int)$db->query("SELECT COUNT(*) as t FROM users")->fetch_assoc()['t']; 
        $rAdmin = $db->query("SELECT COUNT(*) as t FROM users WHERE $uRoleCol='admin'"); 
        if ($rAdmin) $cAdmin = (int)$rAdmin->fetch_assoc()['t']; 
         
        $rUser = $db->query("SELECT COUNT(*) as t FROM users WHERE $uRoleCol='user'"); 
        if ($rUser) $cUser = (int)$rUser->fetch_assoc()['t']; 
 
        $rNew = $db->query("SELECT COUNT(*) as t FROM users WHERE DATE($uDateCol)=CURDATE()"); 
        if ($rNew) $cNewToday = (int)$rNew->fetch_assoc()['t']; 
    } catch (Exception $e) {} 
} 
 
// ===== FETCH EDIT ===== 
 $editData = []; 
if (isset($_GET['edit']) && $canQuery && !($isAjax ?? false)) { 
    try { 
        $eid = (int)$_GET['edit']; 
        $er = $db->query("SELECT * FROM users WHERE $uIdCol=$eid"); 
        if ($er) $editData = $er->fetch_assoc() ?? []; 
    } catch (Exception $e) {} 
} 
 
 $isAjax = isset($_GET['ajax']); 
 
// ===== HELPER FUNCTIONS UI ===== 
 $filterLink = function ($f = '') use ($searchQ) { 
    $q = $searchQ ? '&q=' . urlencode($searchQ) : ''; 
    if ($f) return '?filter=' . urlencode($f) . $q; 
    return 'users.php' . ($q ? '?' . ltrim($q, '&') : ''); 
}; 
 
function roleBadge($r) { 
    if ($r === 'admin') return '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:8px;font-size:.7rem;font-weight:700;color:#8b5cf6;background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.15)"><i class="fa-solid fa-shield-halved" style="font-size:.6rem"></i>Admin</span>'; 
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:8px;font-size:.7rem;font-weight:700;color:#3b82f6;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.12)"><i class="fa-solid fa-user" style="font-size:.6rem"></i>User</span>'; 
} 

// JIKA AJAX DETAIL, LANGSUNG TAMPILKAN MODAL KONTEN DAN EXIT
if ($isAjax && isset($_GET['detail'])): 
    try { 
        $did = (int)$_GET['detail']; 
         
        $dUser = $db->query("SELECT * FROM users WHERE $uIdCol=$did")->fetch_assoc(); 
        if(!$dUser) { 
            echo '<div style="text-align:center;padding:3rem;color:var(--t4)"><i class="fa-solid fa-user-slash fa-2x"></i><p style="margin-top:1rem">User tidak ditemukan.</p></div>'; 
            exit; 
        } 
 
        $dName = htmlspecialchars($dUser[$uNameCol]); 
        $dEmail = !empty($dUser[$uEmailCol]) ? htmlspecialchars($dUser[$uEmailCol]) : '-'; 
        $dPhone = !empty($dUser[$uPhoneCol]) ? htmlspecialchars($dUser[$uPhoneCol]) : '-'; 
        $uRole = $dUser[$uRoleCol] ?? 'user'; 
        $dDate = !empty($dUser[$uDateCol]) ? date('d F Y, H:i', strtotime($dUser[$uDateCol])) : '-'; 
        $dAddr = !empty($dUser[$uAddrCol]) ? nl2br(htmlspecialchars($dUser[$uAddrCol])) : '-'; 
        $dCreated = !empty($dUser[$uDateCol]) ? date('d F Y', strtotime($dUser[$uDateCol])) : '-'; 
 
        $rOrd = $db->query("SELECT COUNT(*) as c FROM orders WHERE $oUserCol=$did")->fetch_assoc(); 
        $dTotalOrd = (int)$rOrd['c']; 
         
        $dTotalRev = 0; 
        if($hasOD) { 
            $rRev = $db->query("SELECT COALESCE(SUM(od.$odSub),0) as s  
                                FROM orders o  
                                JOIN order_detail od ON o.$oIdCol = od.$odOrdCol  
                                WHERE o.$oUserCol=$did AND o.$oStatusCol NOT IN ('batal','dibatalkan')")->fetch_assoc(); 
            $dTotalRev = (float)($rRev['s'] ?? 0); 
        } 
 
        $rLast = $db->query("SELECT $oDateCol as t FROM orders WHERE $oUserCol=$did ORDER BY $oDateCol DESC LIMIT 1")->fetch_assoc(); 
        $dLastOrd = !empty($rLast['t']) ? date('d M Y', strtotime($rLast['t'])) : '-'; 
 
        $recentOrders = []; 
        if($hasOD && $dTotalOrd > 0) { 
            $rq = "SELECT o.$oIdCol as id, o.$oStatusCol as status, o.$oDateCol as tgl, COALESCE(SUM(od.$odSub),0) as total 
                     FROM orders o  
                     LEFT JOIN order_detail od ON o.$oIdCol = od.$odOrdCol 
                     WHERE o.$oUserCol=$did  
                     GROUP BY o.$oIdCol, o.$oStatusCol, o.$oDateCol 
                     ORDER BY o.$oDateCol DESC LIMIT 5"; 
            $rqRes = $db->query($rq); 
            if ($rqRes) $recentOrders = $rqRes->fetch_all(MYSQLI_ASSOC); 
        } 
         
        // TAMPILAN MODAL DETAIL RAPI (TANPA ECHO HTML DARI HALAMAN UTAMA)
        ?>
        <style>
            .detail-header{text-align:center;padding-bottom:1.5rem;border-bottom:1px solid var(--bd);margin-bottom:1.5rem}
            .detail-avatar-lg{width:80px;height:80px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:900;color:#fff;box-shadow:0 4px 15px rgba(236,72,153,.3);margin-bottom:1rem}
            .detail-name-lg{font-size:1.4rem;font-weight:900;color:var(--t1);margin-bottom:.25rem}
            .detail-role-lg{display:inline-flex;align-items:center;gap:8px;margin-top:5px}
            .detail-stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem}
            .detail-stat-card{background:linear-gradient(180deg,var(--bg),#fff);border:1px solid var(--bd);border-radius:12px;padding:1rem;text-align:center;box-shadow:var(--shadow);transition:all .3s cubic-bezier(.4,0,.2,1)}
            .detail-stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
            .dsc-icon{font-size:1.5rem;margin-bottom:.5rem;color:var(--pk3)}
            .dsc-val{font-size:1.2rem;font-weight:900;color:var(--t1);margin-bottom:.25rem}
            .dsc-lbl{font-size:.7rem;color:var(--t4);font-weight:500}
            .detail-grid-2col{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:1.5rem}
            .detail-info-item{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:var(--bg);border-radius:10px;border:1px solid var(--bd)}
            .detail-info-icon{width:28px;height:28px;border-radius:8px;background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--pk3);font-size:.8rem;flex-shrink:0}
            .detail-info-content{flex:1}
            .detail-info-label{font-size:.65rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px}
            .detail-info-value{font-size:.8rem;font-weight:600;color:var(--t1);word-break:break-word}
            .detail-orders-box{margin-top:1.5rem}
            .detail-orders-box h3{font-size:.9rem;font-weight:700;color:var(--t1);margin-bottom:.8rem;padding-bottom:5px;border-bottom:1px solid var(--bd2);display:flex;align-items:center;gap:8px}
            .order-d-list{display:flex;flex-direction:column;gap:.5rem}
            .order-d-list-item{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--bg);border-radius:10px;border:1px solid var(--bd);transition:all .3s cubic-bezier(.4,0,.2,1)}
            .order-d-list-item:hover{background:var(--bg2);border-color:var(--bd2)}
            .od-left{display:flex;flex-direction:column;gap:4px}
            .od-id{font-weight:800;font-size:.75rem;color:var(--t2);background:var(--card);padding:3px 10px;border-radius:6px;display:inline-block;width:fit-content}
            .od-date{font-size:.65rem;color:var(--t4)}
            .od-status{padding:3px 10px;border-radius:6px;font-size:.65rem;font-weight:700;display:inline-block}
            .od-status.done{background:rgba(16,185,129,.1);color:#059669}
            .od-status.pending{background:rgba(245,158,11,.1);color:#d97706}
            .od-status.failed{background:rgba(239,68,68,.1);color:#dc2626}
            .od-total{font-weight:800;font-size:.85rem;color:var(--pk1)}
            .view-detail-btn{display:inline-flex;align-items:center;gap:6px;margin-top:1rem;padding:8px 16px;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;border-radius:8px;font-size:.75rem;font-weight:600;text-decoration:none;transition:all .3s cubic-bezier(.4,0,.2,1)}
            .view-detail-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(236,72,153,.3)}
        </style>
        
        <div class="detail-header"> 
            <div class="detail-avatar-lg" style="background:linear-gradient(135deg, <?= $uRole === 'admin' ? '#8b5cf6, #6d28d9' : 'var(--pk3), var(--pk1)' ?>)"> 
                <?= strtoupper(substr($dName, 0, 1)) ?> 
            </div> 
            <div class="detail-name-lg"><?= $dName ?></div> 
            <div class="detail-role-lg"><?= roleBadge($uRole) ?></div> 
        </div> 
        
        <div class="detail-stats-row"> 
            <div class="detail-stat-card"> 
                <div class="dsc-icon"><i class="fa-solid fa-box-open"></i></div> 
                <div class="dsc-val"><?= number_format($dTotalOrd) ?></div> 
                <div class="dsc-lbl">Total Pesanan</div> 
            </div> 
            <div class="detail-stat-card"> 
                <div class="dsc-icon"><i class="fa-solid fa-money-bill-wave"></i></div> 
                <div class="dsc-val"><?= formatRupiah($dTotalRev) ?></div> 
                <div class="dsc-lbl">Total Pendapatan</div> 
            </div> 
            <div class="detail-stat-card"> 
                <div class="dsc-icon"><i class="fa-regular fa-clock"></i></div> 
                <div class="dsc-val"><?= $dLastOrd ?></div> 
                <div class="dsc-lbl">Pesanan Terakhir</div> 
            </div> 
        </div> 
        
        <div class="detail-grid-2col"> 
            <div class="detail-info-item"> 
                <div class="detail-info-icon"><i class="fa-regular fa-envelope"></i></div> 
                <div class="detail-info-content"> 
                    <div class="detail-info-label">Email</div> 
                    <div class="detail-info-value"><?= $dEmail ?></div> 
                </div> 
            </div> 
            <div class="detail-info-item"> 
                <div class="detail-info-icon"><i class="fa-regular fa-phone"></i></div> 
                <div class="detail-info-content"> 
                    <div class="detail-info-label">Telepon / WhatsApp</div> 
                    <div class="detail-info-value"><?= $dPhone ?></div> 
                </div> 
            </div> 
            <div class="detail-info-item"> 
                <div class="detail-info-icon"><i class="fa-regular fa-calendar-alt"></i></div> 
                <div class="detail-info-content"> 
                    <div class="detail-info-label">Bergabung Sejak</div> 
                    <div class="detail-info-value"><?= $dCreated ?></div> 
                </div> 
            </div> 
            <div class="detail-info-item"> 
                <div class="detail-info-icon"><i class="fa-regular fa-user-tag"></i></div> 
                <div class="detail-info-content"> 
                    <div class="detail-info-label">ID User</div> 
                    <div class="detail-info-value">#<?= str_pad($did, 4, '0', STR_PAD_LEFT) ?></div> 
                </div> 
            </div> 
            <?php if ($dAddr !== '-'): ?> 
            <div class="detail-info-item" style="grid-column:1/-1"> 
                <div class="detail-info-icon"><i class="fa-regular fa-location-dot"></i></div> 
                <div class="detail-info-content"> 
                    <div class="detail-info-label">Alamat Lengkap</div> 
                    <div class="detail-info-value"><?= $dAddr ?></div> 
                </div> 
            </div> 
            <?php endif; ?> 
        </div> 
        
        <div class="detail-orders-box"> 
            <h3><i class="fa-solid fa-receipt"></i> Riwayat Pesanan Terakhir</h3> 
            <?php if (!empty($recentOrders)): ?> 
                <div class="order-d-list"> 
                <?php foreach ($recentOrders as $ro): 
                    $roId = isset($ro['id']) ? (int)$ro['id'] : 0; 
                    $roDate = !empty($ro['tgl']) ? date('d M Y', strtotime($ro['tgl'])) : '-'; 
                    $roSt = $ro['status'] ?? 'pending'; 
                    $roTotal = formatRupiah((float)($ro['total'] ?? 0)); 
                     
                    $stClass = 'pending'; 
                    $stText = 'Diproses'; 
                    if (in_array(strtolower($roSt), ['selesai','done','success','lunas'])) { 
                        $stClass = 'done'; 
                        $stText = 'Selesai'; 
                    } else if (in_array(strtolower($roSt), ['batal','dibatalkan','cancelled','failed'])) { 
                        $stClass = 'failed'; 
                        $stText = 'Dibatalkan'; 
                    } 
                ?> 
                    <div class="order-d-list-item"> 
                        <div class="od-left"> 
                            <span class="od-id">#<?= str_pad($roId, 4, '0', STR_PAD_LEFT) ?></span> 
                            <span class="od-date"><i class="fa-regular fa-calendar" style="font-size:.55rem;margin-right:3px"></i><?= $roDate ?></span> 
                        </div> 
                        <div style="display:flex;align-items:center;gap:12px;"> 
                            <span class="od-status <?= $stClass ?>"><?= $stText ?></span> 
                            <span class="od-total"><?= $roTotal ?></span> 
                        </div> 
                    </div> 
                <?php endforeach; ?> 
                </div> 
                <?php if ($dTotalOrd > 5): ?> 
                <div style="text-align:center;margin-top:1rem"> 
                    <a href="orders.php?user=<?= $did ?>" class="view-detail-btn"> 
                        <i class="fa-solid fa-eye"></i> Lihat Semua (<?= $dTotalOrd ?> Pesanan) 
                    </a> 
                </div> 
                <?php endif; ?> 
            <?php else: ?> 
                <div style="text-align:center;color:var(--t4);padding:2rem;background:var(--bg);border-radius:12px;border:1px solid var(--bd)"> 
                    <i class="fa-solid fa-receipt" style="font-size:2rem;margin-bottom:.5rem;opacity:.3"></i> 
                    <p style="font-size:.8rem">Belum ada pesanan</p> 
                </div> 
            <?php endif; ?> 
        </div> 
        <?php
    } catch (Exception $e) { 
        echo '<div style="text-align:center;padding:3rem;color:var(--t4)"> 
                <i class="fa-solid fa-circle-exclamation fa-2x"></i> 
                <p style="margin-top:1rem">Gagal memuat detail user</p> 
              </div>'; 
    } 
    exit; 
endif; 
?>
<!DOCTYPE html> 
<html lang="id"> 
<head> 
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Kelola Users - Admin Panel</title> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" /> 
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"> 
    <style> 
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0} 
:root{--bg:#fff5f7;--bg2:#ffedf1;--bg3:#ffe4ec;--pk1:#be185d;--pk2:#db2777;--pk3:#ec4899;--pk4:#f472b6;--t1:#4a1028;--t2:#6b2040;--t3:#9b4068;--t4:#c48098;--bd:rgba(190,24,93,.08);--bd2:rgba(190,24,93,.15);--card:#fff;--shadow:0 2px 16px rgba(190,24,93,.06);--shadow-lg:0 8px 32px rgba(190,24,93,.1);--radius:14px;--radius-lg:20px;--sidebar-w:260px;--tr:all .3s cubic-bezier(.4,0,.2,1)} 
body{font-family:'Plus Jakarta Sans',-apple-system,sans-serif;background:var(--bg);color:var(--t1);line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased} 
a{text-decoration:none;color:inherit;transition:var(--tr)}::selection{background:var(--pk3);color:#fff} 
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--bg2)}::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--pk3),var(--pk1));border-radius:10px} 
.dashboard-layout{display:flex;min-height:100vh} 
.sidebar{width:var(--sidebar-w);min-height:100vh;position:fixed;left:0;top:0;bottom:0;z-index:100;background:linear-gradient(180deg,#3a0a1e 0%,#2d0718 40%,#1a0410 100%);border-right:1px solid rgba(249,168,212,.08);display:flex;flex-direction:column;transition:var(--tr)} 
.sidebar::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(249,168,212,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(249,168,212,.02) 1px,transparent 1px);background-size:40px 40px;pointer-events:none} 
.sidebar-brand{padding:1.5rem 1.25rem;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(249,168,212,.08);position:relative} 
.brand-icon{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--pk3),var(--pk1));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 14px rgba(236,72,153,.3)} 
.sidebar-brand span{color:#fff;font-weight:800;font-size:1rem}.sidebar-brand em{color:#f9a8d4;font-style:normal} 
.sidebar-nav{flex:1;padding:.75rem;overflow-y:auto;display:flex;flex-direction:column;gap:2px} 
.sidebar-section{display:flex;flex-direction:column;gap:2px} 
.sidebar-section+.sidebar-section{margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(249,168,212,.06)} 
.sidebar-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,.5);font-size:.82rem;font-weight:600;transition:var(--tr);position:relative} 
.sidebar-link:hover{color:rgba(255,255,255,.85);background:rgba(249,168,212,.06)} 
.sidebar-link.active{color:#fff;background:linear-gradient(135deg,rgba(236,72,153,.2),rgba(190,24,93,.1));border:1px solid rgba(236,72,153,.15);box-shadow:0 2px 12px rgba(236,72,153,.1)} 
.sidebar-link.active .link-icon{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;box-shadow:0 2px 8px rgba(236,72,153,.3)} 
.link-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(249,168,212,.06);font-size:.78rem;transition:var(--tr);flex-shrink:0} 
.sidebar-link.danger-link:hover{color:#fca5a5;background:rgba(239,68,68,.08)} 
.sidebar-user{padding:1rem 1.25rem;border-top:1px solid rgba(249,168,212,.08);position:relative} 
.sidebar-user-info{display:flex;align-items:center;gap:10px} 
.user-avatar{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.8rem;flex-shrink:0} 
.user-name{color:#fff;font-weight:700;font-size:.8rem}.user-role{color:rgba(249,168,212,.5);font-size:.68rem;font-weight:500} 
.mobile-toggle{display:none;position:fixed;top:14px;left:14px;z-index:200;width:42px;height:42px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;font-size:1.1rem;cursor:pointer;box-shadow:0 4px 16px rgba(236,72,153,.3)} 
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99} 
.dashboard-content{flex:1;margin-left:var(--sidebar-w);padding:2rem 2.5rem;min-height:100vh;position:relative} 
.dashboard-content::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(249,168,212,.08),transparent 70%);pointer-events:none;z-index:0} 
.alert{padding:.85rem 1.25rem;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;animation:alertIn .4s ease} 
@keyframes alertIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}} 
.alert-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.15)} 
.alert-danger{background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.15)} 
.page-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;position:relative;z-index:1} 
.page-title{font-size:1.5rem;font-weight:900;color:var(--t1);display:flex;align-items:center;gap:10px}.page-title i{color:var(--pk3)} 
.page-sub{font-size:.82rem;color:var(--t4);margin-top:2px} 
.mini-stats{display:flex;gap:.75rem;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.5rem} 
.mini-stat{display:flex;align-items:center;gap:8px;padding:10px 18px;background:var(--card);border:1px solid var(--bd);border-radius:12px;box-shadow:var(--shadow);font-size:.78rem;font-weight:600} 
.mini-stat i{font-size:.75rem}.mini-stat b{color:var(--t1);font-size:1rem;font-weight:900}.mini-stat span{color:var(--t4)} 
.filter-bar{display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.25rem} 
.filter-tab{padding:7px 16px;border-radius:20px;font-size:.75rem;font-weight:700;border:1px solid var(--bd);background:var(--card);color:var(--t3);cursor:pointer;transition:var(--tr);display:flex;align-items:center;gap:5px} 
.filter-tab:hover{border-color:var(--bd2);color:var(--t1)} 
.filter-tab.active{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(236,72,153,.2)} 
.filter-tab .ft-count{font-size:.65rem;padding:1px 6px;border-radius:8px;background:rgba(0,0,0,.08)}.filter-tab.active .ft-count{background:rgba(255,255,255,.2)} 
.card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;transition:var(--tr)} 
.card-header{padding:1rem 1.4rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem} 
.card-title{font-size:.92rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}.card-title i{color:var(--pk3);font-size:.85rem} 
.search-box{position:relative;width:300px} 
.search-box input{width:100%;padding:8px 14px 8px 36px;border:1px solid var(--bd);border-radius:10px;font-size:.8rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--tr);outline:none} 
.search-box input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)} 
.search-box input::placeholder{color:var(--t4)} 
.search-box>i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--t4);font-size:.78rem} 
.table-wrapper{overflow-x:auto} 
.table{width:100%;border-collapse:collapse} 
.table th{text-align:left;padding:11px 14px;font-size:.68rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--bd);background:var(--bg);white-space:nowrap} 
.table td{padding:13px 14px;font-size:.82rem;border-bottom:1px solid var(--bd);vertical-align:middle} 
.table tbody tr{transition:var(--tr)}.table tbody tr:hover{background:rgba(190,24,93,.02)} 
.user-cell{display:flex;align-items:center;gap:12px} 
.user-cell-avatar{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0;transition:var(--tr)} 
.user-cell-avatar.av-admin{background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;box-shadow:0 4px 12px rgba(139,92,246,.25)} 
.user-cell-avatar.av-user{background:linear-gradient(135deg,var(--pk4),var(--pk2));color:#fff;box-shadow:0 4px 12px rgba(236,72,153,.2)} 
.user-cell-info{min-width:0} 
.user-cell-name{font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px} 
.user-cell-email{font-size:.7rem;color:var(--t4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px} 
.stat-mini{display:flex;gap:12px;flex-wrap:wrap} 
.stat-mini-item{display:flex;align-items:center;gap:4px;font-size:.7rem;color:var(--t4);font-weight:500} 
.stat-mini-item i{font-size:.6rem} 
.stat-mini-item b{color:var(--t1);font-weight:700} 
.stat-mini-item.sv-rev b{color:var(--pk1)} 
.action-btns{display:flex;gap:5px} 
.act-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--card);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.72rem;transition:var(--tr)} 
.act-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow)} 
.act-btn.act-view{color:#3b82f6}.act-btn.act-view:hover{background:rgba(59,130,246,.08)} 
.act-btn.act-edit{color:#3b82f6}.act-btn.act-edit:hover{background:rgba(59,130,246,.08)} 
.act-btn.act-delete{color:#ef4444}.act-btn.act-delete:hover{background:rgba(239,68,68,.08)} 
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;font-weight:700;font-size:.82rem;border:none;cursor:pointer;transition:var(--tr);font-family:inherit} 
.btn-primary{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;box-shadow:0 4px 14px rgba(236,72,153,.25)} 
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.35)} 
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,4,16,.45);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:1rem} 
.modal-overlay.open{display:flex} 
.modal-box{background:var(--card);border-radius:var(--radius-lg);max-width:750px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 24px 60px rgba(26,4,16,.3);animation:modalIn .35s cubic-bezier(.16,1,.3,1)} 
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}} 
.modal-head{padding:1.3rem 1.6rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between} 
.modal-head h2{font-size:1.1rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}.modal-head h2 i{color:var(--pk3)} 
.modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t4);font-size:.8rem;transition:var(--tr)} 
.modal-close:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.15);color:#ef4444} 
.modal-body{padding:1.6rem;overflow-y:auto;max-height:calc(90vh - 140px)} 
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem} 
.form-group{display:flex;flex-direction:column;gap:5px} 
.form-group.full{grid-column:1/-1} 
.form-label{font-size:.78rem;font-weight:700;color:var(--t2)} 
.form-input{padding:10px 14px;border:1.5px solid var(--bd);border-radius:10px;font-size:.85rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--tr);outline:none} 
.form-input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)} 
select.form-input{cursor:pointer} 
textarea.form-input{resize:vertical;min-height:70px} 
.modal-foot{padding:1.2rem 1.6rem;border-top:1px solid var(--bd);display:flex;gap:.75rem;justify-content:flex-end} 
.btn-cancel{padding:10px 20px;border-radius:10px;font-weight:700;font-size:.82rem;border:1.5px solid var(--bd);background:var(--card);color:var(--t3);cursor:pointer} 
.btn-cancel:hover{background:var(--bg2)} 
.btn-submit{padding:10px 24px;border-radius:10px;font-weight:700;font-size:.82rem;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(236,72,153,.25)} 
.btn-submit:hover{transform:translateY(-2px)} 
.empty{text-align:center;padding:3rem 1rem;color:var(--t4)}.empty i{font-size:2.5rem;margin-bottom:.75rem;opacity:.25;display:block} 
@media(max-width:768px){.mobile-toggle{display:flex}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.sidebar-overlay.open{display:block}.dashboard-content{margin-left:0;padding:1.2rem;padding-top:64px}.page-header{flex-direction:column}.card-header{flex-direction:column}.search-box{width:100%}.form-grid{grid-template-columns:1fr}} 
    </style> 
</head> 
<body> 
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button> 
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div> 
<div class="dashboard-layout"> 
    <aside class="sidebar" id="sidebar"> 
        <div class="sidebar-brand"><div class="brand-icon">⚙️</div><span>Admin <em>Panel</em></span></div> 
        <nav class="sidebar-nav"> 
            <div class="sidebar-section"> 
                <a href="dashboard.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard</a> 
                <a href="orders.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-box"></i></span> Pesanan</a> 
                <a href="layanan.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-shirt"></i></span> Layanan</a> 
                <a href="users.php" class="sidebar-link active"><span class="link-icon"><i class="fa-solid fa-users"></i></span> Users</a> 
                <a href="payments.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-credit-card"></i></span> Pembayaran</a> 
                <a href="reports.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-line"></i></span> Laporan</a> 
            </div> 
            <div class="sidebar-section"> 
                <a href="<?= APP_URL ?>/index.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-globe"></i></span> Ke Website</a> 
                <a href="<?= APP_URL ?>/user/logout.php" class="sidebar-link danger-link"><span class="link-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Keluar</a> 
            </div> 
        </nav> 
        <div class="sidebar-user"> 
            <div class="sidebar-user-info"> 
                <?php $sessName = $_SESSION['nama'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin'; ?> 
                <div class="user-avatar" style="background:linear-gradient(135deg,var(--pk3),var(--pk1))"><?= strtoupper(substr($sessName,0,1)) ?></div> 
                <div><div class="user-name"><?= htmlspecialchars($sessName) ?></div><div class="user-role">Administrator</div></div> 
            </div> 
        </div> 
    </aside> 
    <main class="dashboard-content"> 
        <?php if ($flash): ?> 
        <div class="alert alert-<?= $flash['type'] ?>"> 
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> 
            <?= $flash['message'] ?> 
        </div> 
        <?php endif; ?> 
        <div class="page-header"> 
            <div> 
                <div class="page-title"><i class="fa-solid fa-users"></i> Kelola Users</div> 
                <div class="page-sub"><?= number_format($totalUsers) ?> pengguna terdaftar</div> 
            </div> 
            <button onclick="openModal()" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Tambah User</button> 
        </div> 
        <div class="mini-stats"> 
            <div class="mini-stat"><i class="fa-solid fa-users" style="color:var(--pk3)"></i><b><?= $totalUsers ?></b><span>Total</span></div> 
            <div class="mini-stat"><i class="fa-solid fa-user" style="color:#3b82f6"></i><b><?= $cUser ?></b><span>User</span></div> 
            <div class="mini-stat"><i class="fa-solid fa-shield-halved" style="color:#8b5cf6"></i><b><?= $cAdmin ?></b><span>Admin</span></div> 
            <div class="mini-stat"><i class="fa-solid fa-user-plus" style="color:#10b981"></i><b><?= $cNewToday ?></b><span>Hari Ini</span></div> 
        </div> 
        <div class="filter-bar"> 
            <a href="<?= $filterLink() ?>" class="filter-tab <?= !$filterRole ? 'active' : '' ?>"><i class="fa-solid fa-list"></i> Semua <span class="ft-count"><?= $totalUsers ?></span></a> 
            <a href="<?= $filterLink('user') ?>" class="filter-tab <?= $filterRole === 'user' ? 'active' : '' ?>"><i class="fa-solid fa-user"></i> User <span class="ft-count"><?= $cUser ?></span></a> 
            <a href="<?= $filterLink('admin') ?>" class="filter-tab <?= $filterRole === 'admin' ? 'active' : '' ?>"><i class="fa-solid fa-shield-halved"></i> Admin <span class="ft-count"><?= $cAdmin ?></span></a> 
        </div> 
        <div class="card" style="position:relative;z-index:1"> 
            <div class="card-header"> 
                <div class="card-title"><i class="fa-solid fa-table-list"></i> Daftar Users</div> 
                <form method="GET" class="search-box" style="display:flex"> 
                    <?php if ($filterRole): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filterRole) ?>"><?php endif; ?> 
                    <i class="fa-solid fa-magnifying-glass"></i> 
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Cari nama, email, telepon..."> 
                    <button type="submit" style="padding:8px 14px;border:1px solid var(--bd);border-radius:10px;background:var(--bg2);color:var(--t3);cursor:pointer;margin-left:8px"><i class="fa-solid fa-search"></i></button> 
                </form> 
            </div> 
            <div class="table-wrapper"> 
                <?php if (!empty($allUsers)): ?> 
                <table class="table"> 
                    <thead><tr><th>User</th><th>Role</th><th>Pesanan</th><th>Total Belanja</th><th style="width:110px">Aksi</th></tr></thead> 
                    <tbody> 
                    <?php foreach ($allUsers as $u): 
                        $uid = (int)$u[$uIdCol]; 
                        $uName = htmlspecialchars($u[$uNameCol] ?? '?'); 
                        $uEmail = !empty($u[$uEmailCol]) ? htmlspecialchars($u[$uEmailCol]) : '-'; 
                        $uPhone = !empty($u[$uPhoneCol]) ? htmlspecialchars($u[$uPhoneCol]) : ''; 
                        $uRole = $u[$uRoleCol] ?? 'user'; 
                        $initial = strtoupper(substr($uName, 0, 1)); 
                        $avClass = $uRole === 'admin' ? 'av-admin' : 'av-user'; 
                        $totalOrd = (int)($u['total_orders'] ?? 0); 
                        $totalRev = (float)($u['total_revenue'] ?? 0); 
                    ?> 
                    <tr> 
                        <td> 
                            <div class="user-cell"> 
                                <div class="user-cell-avatar <?= $avClass ?>"><?= $initial ?></div> 
                                <div class="user-cell-info"> 
                                    <div class="user-cell-name"><?= $uName ?></div> 
                                    <div class="user-cell-email"><?= $uEmail ?></div> 
                                    <?php if ($uPhone): ?> 
                                    <div style="font-size:.68rem;color:var(--t4)"><i class="fa-solid fa-phone" style="font-size:.55rem;margin-right:3px"></i><?= $uPhone ?></div> 
                                    <?php endif; ?> 
                                </div> 
                            </div> 
                        </td> 
                        <td><?= roleBadge($uRole) ?></td> 
                        <td><div class="stat-mini-item"><i class="fa-solid fa-box"></i> <b><?= $totalOrd ?></b> order</div></td> 
                        <td><div class="stat-mini-item sv-rev"><i class="fa-solid fa-wallet"></i> <b><?= formatRupiah($totalRev) ?></b></div></td> 
                        <td> 
                            <div class="action-btns"> 
                                <button class="act-btn act-view" title="Detail" onclick='openDetail(<?= $uid ?>)'><i class="fa-solid fa-eye"></i></button> 
                                <button class="act-btn act-edit" title="Edit" onclick='openEdit(<?= htmlspecialchars(json_encode($u)) ?>)'><i class="fa-solid fa-pen-to-square"></i></button> 
                                <?php if ($u[$uIdCol] != ($_SESSION['user_id']??0)): ?> 
                                <a href="?delete=<?= $uid ?>" onclick="return confirm('Hapus user ini?')" class="act-btn act-delete" title="Hapus"><i class="fa-solid fa-trash-can"></i></a> 
                                <?php endif; ?> 
                            </div> 
                         </td> 
                    </tr> 
                    <?php endforeach; ?> 
                    </tbody> 
                </table> 
                <?php else: ?> 
                <div class="empty"><i class="fa-solid fa-users"></i><p>Tidak ada user ditemukan</p></div> 
                <?php endif; ?> 
            </div> 
        </div> 
    </main> 
</div> 

<!-- MODAL ADD/EDIT --> 
<div class="modal-overlay" id="userModal"> 
    <div class="modal-box" style="max-width:600px"> 
        <div class="modal-head"> 
            <h2 id="modalTitle"><i class="fa-solid fa-user-plus"></i> Tambah User</h2> 
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button> 
        </div> 
        <form method="POST" id="userForm"> 
            <input type="hidden" name="save_user" value="1"> 
            <input type="hidden" name="id" id="fId" value=""> 
            <div class="modal-body"> 
                <div class="form-grid"> 
                    <div class="form-group full"> 
                        <label class="form-label">Nama Lengkap *</label> 
                        <input type="text" name="nama" id="fNama" class="form-input" placeholder="Nama lengkap user" required> 
                    </div> 
                    <div class="form-group"> 
                        <label class="form-label">Email</label> 
                        <input type="email" name="email" id="fEmail" class="form-input" placeholder="email@contoh.com"> 
                    </div> 
                    <div class="form-group"> 
                        <label class="form-label">Telepon</label> 
                        <input type="text" name="telepon" id="fPhone" class="form-input" placeholder="08xxxxxxxxxx"> 
                    </div> 
                    <div class="form-group"> 
                        <label class="form-label">Role</label> 
                        <select name="role" id="fRole" class="form-input"> 
                            <option value="user">User</option> 
                            <option value="admin">Admin</option> 
                        </select> 
                    </div> 
                    <div class="form-group full"> 
                        <label class="form-label">Alamat</label> 
                        <textarea name="alamat" id="fAlamat" class="form-input" rows="2" placeholder="Alamat lengkap"></textarea> 
                    </div> 
                </div> 
            </div> 
            <div class="modal-foot"> 
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button> 
                <button type="submit" class="btn-submit"><i class="fa-solid fa-check"></i> Simpan</button> 
            </div> 
        </form> 
    </div> 
</div> 

<!-- DETAIL MODAL --> 
<div class="modal-overlay" id="detailModal"> 
    <div class="modal-box" style="max-width:750px"> 
        <div class="modal-head"> 
            <h2><i class="fa-solid fa-user-circle"></i> Detail Profil</h2> 
            <button class="modal-close" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button> 
        </div> 
        <div class="modal-body" id="detailBox" style="padding:1.5rem"> 
            <div style="text-align:center;padding:3rem"> 
                <i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--pk3)"></i> 
                <p style="margin-top:1rem;color:var(--t4)">Memuat data user...</p> 
            </div> 
        </div> 
    </div> 
</div> 

<script> 
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')} 
window.addEventListener('resize',function(){if(window.innerWidth>768){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}}); 
function closeModal(){document.getElementById('userModal').classList.remove('open')} 
document.getElementById('userModal').addEventListener('click',function(e){if(e.target===this)closeModal()}); 
function openModal(){ 
    document.getElementById('modalTitle').innerHTML='<i class="fa-solid fa-user-plus"></i> Tambah User'; 
    document.getElementById('userForm').reset(); 
    document.getElementById('fId').value=''; 
    document.getElementById('userModal').classList.add('open'); 
} 
function openEdit(data){ 
    document.getElementById('modalTitle').innerHTML='<i class="fa-solid fa-pen-to-square"></i> Edit User'; 
    document.getElementById('fId').value=data.id_user || ''; 
    document.getElementById('fNama').value=data.nama || ''; 
    document.getElementById('fEmail').value=data.email || ''; 
    document.getElementById('fPhone').value=data.no_hp || ''; 
    document.getElementById('fRole').value=data.role || 'user'; 
    document.getElementById('fAlamat').value=data.alamat || ''; 
    document.getElementById('userModal').classList.add('open'); 
} 
function closeDetail(){document.getElementById('detailModal').classList.remove('open')} 
document.getElementById('detailModal').addEventListener('click',function(e){if(e.target===this)closeDetail()}); 
function openDetail(id){ 
    var m=document.getElementById('detailModal'),b=document.getElementById('detailBox'); 
    m.classList.add('open'); 
    b.innerHTML='<div style="text-align:center;padding:3rem"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--pk3)"></i><p style="margin-top:1rem;color:var(--t4)">Memuat data user...</p></div>'; 
    fetch('users.php?detail='+id+'&ajax=1') 
        .then(function(r){return r.text()}) 
        .then(function(h){b.innerHTML=h}) 
        .catch(function(){b.innerHTML='<div style="text-align:center;padding:3rem;color:var(--t4)"><i class="fa-solid fa-circle-exclamation fa-2x"></i><p style="margin-top:1rem">Gagal memuat data</p></div>'}); 
} 
<?php if(isset($_GET['edit'])&&!empty($editData)):?>document.getElementById('userModal').classList.add('open');openEdit(<?= htmlspecialchars(json_encode($editData)) ?>);<?php endif; ?> 
</script> 
</body> 
</html>