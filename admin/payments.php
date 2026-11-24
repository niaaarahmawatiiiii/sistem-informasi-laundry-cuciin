<?php
/**
 * Kelola Pembayaran - Admin Panel
 * Termasuk flow COD: cod_menunggu → cod_bukti → paid
 * Upload bukti & verifikasi dalam satu file
 */
 $pageTitle = 'Kelola Pembayaran - Admin Panel';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireAdmin();

 $flash = getFlash();

// ===== HELPER FUNCTIONS =====
function cols($db, $table) {
    try { $r = $db->query("SHOW COLUMNS FROM $table"); return $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'Field') : []; }
    catch (Exception $e) { return []; }
}
function pickCol($cols, $candidates) {
    foreach ($candidates as $c) { if (in_array($c, $cols)) return $c; }
    return $candidates[0];
}
function sv($db, $sql, $d = 0) {
    try { $r = $db->query($sql); if ($r && $row = $r->fetch_assoc()) return (float)$row['t']; }
    catch (Exception $e) {} return $d;
}

// ===== DETECT KOLOM =====
 $payCols = cols($db, 'pembayaran');
 $ordCols = cols($db, 'orders');
 $usrCols = cols($db, 'users');

 $pIdCol     = pickCol($payCols, ['id_pembayaran','id','payment_id']);
 $pOrderCol  = pickCol($payCols, ['id_order','order_id']);
 $pAmountCol = pickCol($payCols, ['jumlah','amount','total','nominal']);
 $pMethodCol = pickCol($payCols, ['metode_pembayaran','metode','method']);
 $pStatusCol = pickCol($payCols, ['status_pembayaran','status','state']);
 $pDateCol   = pickCol($payCols, ['tanggal_bayar','created_at','tgl_bayar','tanggal','date_created']);
 $pProofCol  = in_array('bukti_pembayaran', $payCols) ? 'bukti_pembayaran' : (in_array('bukti_bayar', $payCols) ? 'bukti_bayar' : '');

 $oIdCol    = pickCol($ordCols, ['id_order','order_id','id']);
 $oUserCol  = pickCol($ordCols, ['id_user','user_id','customer_id']);
 $oTotalCol = pickCol($ordCols, ['total_harga','total','total_bayar','grand_total']);
 $oStatusCol= pickCol($ordCols, ['status','order_status','state']);
 $oCodeCol  = in_array('kode_order', $ordCols) ? 'kode_order' : (in_array('order_code', $ordCols) ? 'order_code' : '');

 $uIdCol    = pickCol($usrCols, ['id_user','user_id','id']);
 $uNameCol  = pickCol($usrCols, ['nama','name','username','nama_lengkap']);
 $uEmailCol = in_array('email', $usrCols) ? 'email' : '';
 $uPhoneCol = pickCol($usrCols, ['no_hp','telepon','phone','whatsapp']);

 $trkExists = in_array('tracking', array_map(function($t) use ($db) {
    try { $r = $db->query("SHOW TABLES LIKE '$t'"); return $r && $r->num_rows > 0; } catch(Exception $e) { return false; }
}, ['tracking']));

 $canQuery = !empty($payCols) && !empty($ordCols);

// ===== STATUS MAP =====
 $statusMap = [
    'pending'      => ['label'=>'Pending',         'color'=>'#d97706','bg'=>'rgba(245,158,11,.1)',  'border'=>'rgba(245,158,11,.15)',  'icon'=>'fa-clock'],
    'paid'         => ['label'=>'Dibayar',         'color'=>'#059669','bg'=>'rgba(16,185,129,.1)',  'border'=>'rgba(16,185,129,.15)',  'icon'=>'fa-circle-check'],
    'dibayar'      => ['label'=>'Dibayar',         'color'=>'#059669','bg'=>'rgba(16,185,129,.1)',  'border'=>'rgba(16,185,129,.15)',  'icon'=>'fa-circle-check'],
    'cod_menunggu' => ['label'=>'COD Menunggu',    'color'=>'#ea580c','bg'=>'rgba(234,88,12,.1)',   'border'=>'rgba(234,88,12,.15)',   'icon'=>'fa-truck'],
    'cod_bukti'    => ['label'=>'Bukti Dikirim',   'color'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)',  'border'=>'rgba(59,130,246,.15)',  'icon'=>'fa-image'],
    'gagal'        => ['label'=>'Gagal',           'color'=>'#dc2626','bg'=>'rgba(239,68,68,.1)',   'border'=>'rgba(239,68,68,.15)',   'icon'=>'fa-circle-xmark'],
    'refund'       => ['label'=>'Refund',          'color'=>'#6b7280','bg'=>'rgba(107,114,128,.1)', 'border'=>'rgba(107,114,128,.15)', 'icon'=>'fa-rotate-left'],
];

function statusBadge($s) {
    global $statusMap;
    $s = strtolower($s ?? '');
    // Alias
    if ($s === 'success' || $s === 'lunas') $s = 'paid';
    $m = $statusMap[$s] ?? ['label'=>ucfirst($s),'color'=>'#94a3b8','bg'=>'rgba(148,163,184,.1)','border'=>'rgba(148,163,184,.15)','icon'=>'fa-circle'];
    return '<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:8px;font-size:.7rem;font-weight:700;color:'.$m['color'].';background:'.$m['bg'].';border:1px solid '.$m['border'].'"><i class="fa-solid '.$m['icon'].'" style="font-size:.6rem"></i>'.$m['label'].'</span>';
}

function methodIcon($m) {
    $m = strtolower($m ?? '');
    $map = [
        'transfer'=>['fa-building-columns','#3b82f6','Transfer Bank'],
        'cod'=>['fa-money-bill-wave','#ea580c','COD / Tunai'],
        'ewallet'=>['fa-wallet','#8b5cf6','E-Wallet'],
        'qris'=>['fa-qrcode','#ec4899','QRIS'],
        'kartu'=>['fa-credit-card','#f59e0b','Kartu Kredit'],
    ];
    if (isset($map[$m])) {
        $x = $map[$m];
        return '<span style="display:inline-flex;align-items:center;gap:7px;font-size:.8rem;font-weight:600;color:var(--t1)"><i class="fa-solid '.$x[0].'" style="color:'.$x[1].';font-size:.85rem"></i><span>'.$x[2].'</span></span>';
    }
    return '<span style="font-size:.8rem;font-weight:600">'.htmlspecialchars($m).'</span>';
}

function isCodStatus($s) {
    return in_array(strtolower($s ?? ''), ['cod_menunggu','cod_bukti']);
}

// ===== HANDLE AJAX: UPLOAD BUKTI COD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_bukti_cod') {
    header('Content-Type: application/json');
    $payId = (int)($_POST['id_pembayaran'] ?? 0);
    if (!$payId) { echo json_encode(['status'=>'error','message'=>'ID tidak valid']); exit; }

    $pay = $db->query("SELECT * FROM pembayaran WHERE $pIdCol=$payId")->fetch_assoc();
    if (!$pay) { echo json_encode(['status'=>'error','message'=>'Pembayaran tidak ditemukan']); exit; }
    if (strtolower($pay[$pMethodCol] ?? '') !== 'cod') { echo json_encode(['status'=>'error','message'=>'Bukan pesanan COD']); exit; }
    $curSt = strtolower($pay[$pStatusCol] ?? '');
    if (!in_array($curSt, ['cod_menunggu','pending','cod_bukti'])) { echo json_encode(['status'=>'error','message'=>'Status tidak memungkinkan upload']); exit; }

    if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status'=>'error','message'=>'File bukti wajib diupload']); exit;
    }
    $allowed = ['image/jpeg','image/png','image/webp','image/jpg'];
    if (!in_array($_FILES['bukti']['type'], $allowed)) {
        echo json_encode(['status'=>'error','message'=>'Format harus JPG/PNG/WebP']); exit;
    }
    if ($_FILES['bukti']['size'] > 5*1024*1024) {
        echo json_encode(['status'=>'error','message'=>'Ukuran maksimal 5MB']); exit;
    }

    $ext = pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION);
    $fileName = 'cod_' . $payId . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/bukti_cod/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $destPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['bukti']['tmp_name'], $destPath)) {
        echo json_encode(['status'=>'error','message'=>'Gagal mengupload file']); exit;
    }

    $buktiUrl = APP_URL . '/uploads/bukti_cod/' . $fileName;
    $proofCol = $pProofCol ?: 'bukti_pembayaran';

    try {
        if ($proofCol && in_array($proofCol, $payCols)) {
            $db->query("UPDATE pembayaran SET $pStatusCol='cod_bukti', $proofCol='$buktiUrl' WHERE $pIdCol=$payId");
        } else {
            $db->query("UPDATE pembayaran SET $pStatusCol='cod_bukti' WHERE $pIdCol=$payId");
        }

        // Tracking
        if ($trkExists) {
            $oid = (int)$pay[$pOrderCol];
            $trkC = cols($db, 'tracking');
            $trkKet = in_array('keterangan', $trkC) ? 'keterangan' : 'status';
            $trkBy = in_array('updated_by', $trkC) ? 'updated_by' : 'updated_by';
            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $db->query("INSERT INTO tracking (id_order, status, $trkKet, $trkBy) VALUES ($oid, 'Bukti COD diterima', 'Kurir telah mengupload bukti pembayaran COD. Menunggu verifikasi admin.', $adminId)");
        }

        echo json_encode(['status'=>'success','message'=>'Bukti pembayaran COD berhasil diupload. Menunggu verifikasi admin.','bukti_url'=>$buktiUrl]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>'Database error: '.$e->getMessage()]);
    }
    exit;
}

// ===== HANDLE AJAX: VERIFIKASI COD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verifikasi_cod') {
    header('Content-Type: application/json');
    $payId = (int)($_POST['id_pembayaran'] ?? 0);
    $act = $_POST['verdict'] ?? '';
    if (!$payId || !in_array($act, ['approve','reject'])) { echo json_encode(['status'=>'error','message'=>'Parameter tidak valid']); exit; }

    $pay = $db->query("SELECT * FROM pembayaran WHERE $pIdCol=$payId")->fetch_assoc();
    if (!$pay || strtolower($pay[$pStatusCol] ?? '') !== 'cod_bukti') {
        echo json_encode(['status'=>'error','message'=>'Bukan status menunggu verifikasi']); exit;
    }

    $oid = (int)$pay[$pOrderCol];
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $proofCol = $pProofCol ?: 'bukti_pembayaran';

    $db->begin_transaction();
    try {
        if ($act === 'approve') {
            $db->query("UPDATE pembayaran SET $pStatusCol='paid' WHERE $pIdCol=$payId");
            // Update order status ke dikonfirmasi
            $db->query("UPDATE orders SET $oStatusCol='dikonfirmasi' WHERE $oIdCol=$oid");
            $trkMsg = 'Admin telah memverifikasi bukti pembayaran COD. Pembayaran dinyatakan VALID.';
            $trkStatus = 'Pembayaran COD diverifikasi';
            $label = '<b>diverifikasi</b>. Status pembayaran diubah menjadi <b>PAID</b>.';
        } else {
            if ($proofCol && in_array($proofCol, $payCols)) {
                $db->query("UPDATE pembayaran SET $pStatusCol='cod_menunggu', $proofCol=NULL WHERE $pIdCol=$payId");
            } else {
                $db->query("UPDATE pembayaran SET $pStatusCol='cod_menunggu' WHERE $pIdCol=$payId");
            }
            $trkMsg = 'Admin menolak bukti pembayaran COD. Kurir diminta upload ulang.';
            $trkStatus = 'Bukti COD ditolak';
            $label = '<b>ditolak</b>. Kurir perlu upload ulang bukti.';
        }

        if ($trkExists) {
            $trkC = cols($db, 'tracking');
            $trkKet = in_array('keterangan', $trkC) ? 'keterangan' : 'status';
            $trkBy = in_array('updated_by', $trkC) ? 'updated_by' : 'updated_by';
            $db->query("INSERT INTO tracking (id_order, status, $trkKet, $trkBy) VALUES ($oid, '$trkStatus', '$trkMsg', $adminId)");
        }

        $db->commit();
        echo json_encode(['status'=>'success','message'=>'Bukti pembayaran COD '.$label]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status'=>'error','message'=>'Database error: '.$e->getMessage()]);
    }
    exit;
}

// ===== HANDLE POST: UPDATE STATUS MANUAL =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pid = (int)($_POST['pid'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $validStatuses = ['pending','paid','dibayar','gagal','refund','cod_menunggu','cod_bukti'];

    if ($pid && in_array($newStatus, $validStatuses)) {
        try {
            $db->query("UPDATE pembayaran SET $pStatusCol='$newStatus' WHERE $pIdCol=$pid");
            if (in_array($newStatus, ['paid','dibayar'])) {
                $q = $db->query("SELECT $pOrderCol FROM pembayaran WHERE $pIdCol=$pid");
                if ($q && $row = $q->fetch_assoc()) {
                    $oid = $row[$pOrderCol];
                    $db->query("UPDATE orders SET $oStatusCol='dikonfirmasi' WHERE $oIdCol=$oid");
                }
            }
            setFlash('success', 'Status pembayaran berhasil diperbarui.');
        } catch (Exception $e) {
            setFlash('danger', 'Gagal update: ' . $e->getMessage());
        }
    } else {
        setFlash('danger', 'Status tidak valid.');
    }
    header('Location: payments.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try { $db->query("DELETE FROM pembayaran WHERE $pIdCol=$delId"); } catch (Exception $e) {}
    setFlash('success', 'Data pembayaran berhasil dihapus.');
    header('Location: payments.php');
    exit;
}

// ===== FILTER & SEARCH =====
 $filterStatus = $_GET['filter'] ?? '';
 $searchQ = trim($_GET['q'] ?? '');

// ===== FETCH PAYMENTS =====
 $allPayments = [];
if ($canQuery) {
    $wp = [];
    if ($filterStatus) {
        $fs = $db->real_escape_string($filterStatus);
        $wp[] = "p.$pStatusCol='$fs'";
    }
    if ($searchQ) {
        $sq = $db->real_escape_string($searchQ);
        $searchParts = [];
        if ($oCodeCol) $searchParts[] = "o.$oCodeCol LIKE '%$sq%'";
        $searchParts[] = "u.$uNameCol LIKE '%$sq%'";
        if ($uPhoneCol) $searchParts[] = "u.$uPhoneCol LIKE '%$sq%'";
        $wp[] = '(' . implode(' OR ', $searchParts) . ')';
    }
    $wSql = !empty($wp) ? 'WHERE ' . implode(' AND ', $wp) : '';

    try {
        $sql = "SELECT p.*, o.$oCodeCol as order_code, o.$oTotalCol as order_total, o.$oStatusCol as order_status, u.$uNameCol as customer_name";
        if ($uEmailCol) $sql .= ", u.$uEmailCol as customer_email";
        if ($uPhoneCol) $sql .= ", u.$uPhoneCol as customer_phone";
        $sql .= " FROM pembayaran p JOIN orders o ON o.$oIdCol = p.$pOrderCol JOIN users u ON u.$uIdCol = o.$oUserCol $wSql ORDER BY p.$pIdCol DESC LIMIT 500";
        $pq = $db->query($sql);
        if ($pq) $allPayments = $pq->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

// ===== STATS =====
 $totalPay = 0; $cPaid = 0; $cPending = 0; $cFailed = 0; $cCodMenunggu = 0; $cCodBukti = 0; $totalRevenue = 0;
if ($canQuery) {
    $totalPay = (int)sv($db, "SELECT COUNT(*) as t FROM pembayaran", 0);
    $totalRevenue = sv($db, "SELECT COALESCE(SUM($pAmountCol),0) as t FROM pembayaran WHERE $pStatusCol IN ('paid','dibayar')", 0);
    $cPaid = (int)sv($db, "SELECT COUNT(*) as t FROM pembayaran WHERE $pStatusCol IN ('paid','dibayar')", 0);
    $cPending = (int)sv($db, "SELECT COUNT(*) as t FROM pembayaran WHERE $pStatusCol='pending'", 0);
    $cFailed = (int)sv($db, "SELECT COUNT(*) as t FROM pembayaran WHERE $pStatusCol IN ('gagal','refund')", 0);
    $cCodMenunggu = (int)sv($db, "SELECT COUNT(*) as t FROM pembayaran WHERE $pStatusCol='cod_menunggu'", 0);
    $cCodBukti = (int)sv($db, "SELECT COUNT(*) as t FROM pembayaran WHERE $pStatusCol='cod_bukti'", 0);
}

 $filterLink = function ($f = '') use ($searchQ) {
    $p = [];
    if ($f) $p[] = 'filter=' . urlencode($f);
    if ($searchQ) $p[] = 'q=' . urlencode($searchQ);
    return 'payments.php' . (!empty($p) ? '?' . implode('&', $p) : '');
};

 $sessName = $_SESSION['nama'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#fff5f7;--bg2:#ffedf1;--bg3:#ffe4ec;--pk1:#be185d;--pk2:#db2777;--pk3:#ec4899;--pk4:#f472b6;--t1:#4a1028;--t2:#6b2040;--t3:#9b4068;--t4:#c48098;--bd:rgba(190,24,93,.08);--bd2:rgba(190,24,93,.15);--card:#fff;--shadow:0 2px 16px rgba(190,24,93,.06);--shadow-lg:0 8px 32px rgba(190,24,93,.1);--radius:14px;--radius-lg:20px;--sidebar-w:260px;--tr:all .3s cubic-bezier(.4,0,.2,1)}
body{font-family:'Plus Jakarta Sans',-apple-system,sans-serif;background:var(--bg);color:var(--t1);line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit;transition:var(--tr)}::selection{background:var(--pk3);color:#fff}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--bg2)}::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--pk3),var(--pk1));border-radius:10px}
.dashboard-layout{display:flex;min-height:100vh}

/* ===== SIDEBAR ===== */
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
.mobile-toggle{display:none;position:fixed;top:14px;left:14px;z-index:200;width:42px;height:42px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;font-size:1.1rem;cursor:pointer;box-shadow:0 4px 16px rgba(236,72,153,.3);align-items:center;justify-content:center}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99}

/* ===== CONTENT ===== */
.dashboard-content{flex:1;margin-left:var(--sidebar-w);padding:2rem 2.5rem;min-height:100vh;position:relative}
.dashboard-content::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(249,168,212,.08),transparent 70%);pointer-events:none;z-index:0}
.alert{padding:.85rem 1.25rem;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;animation:alertIn .4s ease}
@keyframes alertIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.15)}
.alert-danger{background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.15)}
.page-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;position:relative;z-index:1}
.page-title{font-size:1.5rem;font-weight:900;color:var(--t1);display:flex;align-items:center;gap:10px}.page-title i{color:var(--pk3)}
.page-sub{font-size:.82rem;color:var(--t4);margin-top:2px}

/* ===== MINI STATS ===== */
.mini-stats{display:flex;gap:.75rem;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.5rem}
.mini-stat{display:flex;align-items:center;gap:8px;padding:10px 18px;background:var(--card);border:1px solid var(--bd);border-radius:12px;box-shadow:var(--shadow);font-size:.78rem;font-weight:600;transition:var(--tr)}
.mini-stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
.mini-stat i{font-size:.75rem}.mini-stat b{color:var(--t1);font-size:1rem;font-weight:900}.mini-stat span{color:var(--t4)}
.mini-stat.big{padding:14px 22px}.mini-stat.big b{font-size:1.15rem}

/* ===== FILTER ===== */
.filter-bar{display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.25rem}
.filter-tab{padding:7px 16px;border-radius:20px;font-size:.75rem;font-weight:700;border:1px solid var(--bd);background:var(--card);color:var(--t3);cursor:pointer;transition:var(--tr);display:flex;align-items:center;gap:5px}
.filter-tab:hover{border-color:var(--bd2);color:var(--t1)}
.filter-tab.active{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(236,72,153,.2)}
.filter-tab .ft-count{font-size:.65rem;padding:1px 6px;border-radius:8px;background:rgba(0,0,0,.08)}.filter-tab.active .ft-count{background:rgba(255,255,255,.2)}
.filter-tab.cod-tab{border-color:rgba(234,88,12,.2)}.filter-tab.cod-tab.active{background:linear-gradient(135deg,#ea580c,#c2410c);box-shadow:0 4px 14px rgba(234,88,12,.2)}
.filter-tab.bukti-tab{border-color:rgba(59,130,246,.2)}.filter-tab.bukti-tab.active{background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 4px 14px rgba(59,130,246,.2)}

/* ===== CARD & TABLE ===== */
.card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;position:relative;z-index:1}
.card-header{padding:1rem 1.4rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
.card-title{font-size:.92rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}.card-title i{color:var(--pk3);font-size:.85rem}
.search-box{position:relative;width:280px}
.search-box input{width:100%;padding:8px 14px 8px 36px;border:1px solid var(--bd);border-radius:10px;font-size:.8rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--tr);outline:none}
.search-box input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)}
.search-box input::placeholder{color:var(--t4)}
.search-box>i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--t4);font-size:.78rem}
.table-wrapper{overflow-x:auto}
.table{width:100%;border-collapse:collapse}
.table th{text-align:left;padding:11px 14px;font-size:.68rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--bd);background:var(--bg);white-space:nowrap}
.table td{padding:13px 14px;font-size:.82rem;border-bottom:1px solid var(--bd);vertical-align:middle}
.table tbody tr{transition:var(--tr)}.table tbody tr:hover{background:rgba(190,24,93,.02)}.table tbody tr:last-child td{border-bottom:none}
.table tbody tr.row-cod{background:rgba(234,88,12,.015)}.table tbody tr.row-cod:hover{background:rgba(234,88,12,.04)}
.table tbody tr.row-bukti{background:rgba(59,130,246,.015)}.table tbody tr.row-bukti:hover{background:rgba(59,130,246,.04)}
.table tbody tr.row-failed{opacity:.5}.table tbody tr.row-failed:hover{opacity:.7}
.amount-cell{font-weight:800;font-size:.88rem;color:var(--t1)}
.amount-cell.amt-paid{color:#059669}.amount-cell.amt-pending{color:#d97706}.amount-cell.amt-failed{color:#dc2626;text-decoration:line-through;opacity:.6}
.amount-cell.amt-cod{color:#ea580c}.amount-cell.amt-bukti{color:#3b82f6}
.customer-cell .cc-name{font-weight:700;color:var(--t1);font-size:.82rem}.customer-cell .cc-sub{font-size:.7rem;color:var(--t4)}
.proof-thumb{width:42px;height:42px;border-radius:10px;object-fit:cover;border:1px solid var(--bd);cursor:pointer;transition:var(--tr)}
.proof-thumb:hover{transform:scale(1.1);box-shadow:var(--shadow-lg)}
.proof-none{width:42px;height:42px;border-radius:10px;border:1.5px dashed var(--bd2);display:flex;align-items:center;justify-content:center;color:var(--t4);font-size:.7rem}
.action-btns{display:flex;gap:5px;flex-wrap:wrap}
.act-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--card);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.72rem;transition:var(--tr)}
.act-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.act-btn.act-view{color:#8b5cf6}.act-btn.act-view:hover{background:rgba(139,92,246,.08)}
.act-btn.act-upload{color:#ea580c;border-color:rgba(234,88,12,.15)}.act-btn.act-upload:hover{background:rgba(234,88,12,.08)}
.act-btn.act-check{color:#22c55e;border-color:rgba(34,197,94,.15)}.act-btn.act-check:hover{background:rgba(34,197,94,.08)}
.act-btn.act-reject{color:#ef4444;border-color:rgba(239,68,68,.12)}.act-btn.act-reject:hover{background:rgba(239,68,68,.08)}
.act-btn.act-delete{color:#ef4444}.act-btn.act-delete:hover{background:rgba(239,68,68,.08)}

/* ===== MODAL ===== */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,4,16,.45);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal-box{background:var(--card);border-radius:var(--radius-lg);max-width:650px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 24px 60px rgba(26,4,16,.3);animation:modalIn .35s cubic-bezier(.16,1,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-head{padding:1.3rem 1.6rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
.modal-head h2{font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}.modal-head h2 i{color:var(--pk3)}
.modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t4);font-size:.8rem;transition:var(--tr)}
.modal-close:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.15);color:#ef4444}
.modal-body{padding:1.6rem;overflow-y:auto;max-height:calc(90vh - 70px)}
.modal-foot{padding:1.2rem 1.6rem;border-top:1px solid var(--bd);display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap}

/* ===== FORM ===== */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{display:flex;flex-direction:column;gap:5px}.form-group.full{grid-column:1/-1}
.form-label{font-size:.78rem;font-weight:700;color:var(--t2)}
.form-input{padding:10px 14px;border:1.5px solid var(--bd);border-radius:10px;font-size:.85rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--tr);outline:none}
.form-input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)}
select.form-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23c48098' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
textarea.form-input{resize:vertical;min-height:70px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:10px;font-weight:700;font-size:.82rem;border:none;cursor:pointer;font-family:inherit;transition:var(--tr)}
.btn:hover{transform:translateY(-2px)}
.btn-cancel{background:var(--card);color:var(--t3);border:1.5px solid var(--bd)}.btn-cancel:hover{background:var(--bg2)}
.btn-pink{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;box-shadow:0 4px 14px rgba(236,72,153,.25)}.btn-pink:hover{box-shadow:0 6px 20px rgba(236,72,153,.35)}
.btn-green{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;box-shadow:0 4px 14px rgba(34,197,94,.25)}.btn-green:hover{box-shadow:0 6px 20px rgba(34,197,94,.35)}
.btn-red{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 4px 14px rgba(239,68,68,.25)}.btn-red:hover{box-shadow:0 6px 20px rgba(239,68,68,.35)}
.btn-orange{background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;box-shadow:0 4px 14px rgba(234,88,12,.25)}.btn-orange:hover{box-shadow:0 6px 20px rgba(234,88,12,.35)}
.btn-blue{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.25)}.btn-blue:hover{box-shadow:0 6px 20px rgba(59,130,246,.35)}

/* ===== DETAIL INFO ROWS ===== */
.info-row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--bd)}.info-row:last-child{border-bottom:none}
.info-label{font-size:.72rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.3px;min-width:120px;flex-shrink:0}
.info-value{font-size:.85rem;font-weight:600;color:var(--t1)}
.proof-preview img{max-width:100%;max-height:260px;border-radius:12px;border:1px solid var(--bd);object-fit:contain;cursor:pointer;transition:var(--tr)}
.proof-preview img:hover{box-shadow:var(--shadow-lg)}
.status-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem}

/* ===== UPLOAD SECTION ===== */
.upload-zone{border:2px dashed var(--bd2);border-radius:14px;padding:2rem;text-align:center;background:var(--bg);transition:var(--tr);cursor:pointer;position:relative}
.upload-zone:hover{border-color:var(--pk3);background:var(--bg2)}
.upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer}
.upload-zone i{font-size:2rem;color:var(--pk4);margin-bottom:.5rem;display:block}
.upload-zone .uz-title{font-size:.88rem;font-weight:700;color:var(--t1);margin-bottom:.25rem}
.upload-zone .uz-sub{font-size:.72rem;color:var(--t4)}
.upload-preview{margin-top:1rem;display:none}
.upload-preview img{max-width:200px;max-height:150px;border-radius:10px;border:1px solid var(--bd);object-fit:contain}

/* ===== COD SECTION IN DETAIL ===== */
.cod-action-box{margin-top:1.25rem;padding:1.25rem;border-radius:14px;position:relative;overflow:hidden}
.cod-action-box.cod-wait{background:linear-gradient(135deg,rgba(234,88,12,.04),rgba(234,88,12,.01));border:1px solid rgba(234,88,12,.12)}
.cod-action-box.cod-wait::before{content:"";position:absolute;top:0;left:0;width:4px;height:100%;background:linear-gradient(180deg,#ea580c,#c2410c)}
.cod-action-box.cod-verify{background:linear-gradient(135deg,rgba(59,130,246,.04),rgba(59,130,246,.01));border:1px solid rgba(59,130,246,.12)}
.cod-action-box.cod-verify::before{content:"";position:absolute;top:0;left:0;width:4px;height:100%;background:linear-gradient(180deg,#3b82f6,#2563eb)}
.cod-action-title{font-size:.88rem;font-weight:800;color:var(--t1);margin-bottom:.75rem;display:flex;align-items:center;gap:8px}
.cod-action-title i{font-size:.85rem}

/* ===== LIGHTBOX ===== */
.img-lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:99999;align-items:center;justify-content:center;padding:2rem;cursor:pointer;backdrop-filter:blur(4px)}
.img-lightbox.open{display:flex}
.img-lightbox img{max-width:90%;max-height:90%;border-radius:12px;object-fit:contain;box-shadow:0 0 60px rgba(0,0,0,.5)}
.img-lightbox .lb-close{position:absolute;top:20px;right:20px;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.1);border:none;color:#fff;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:var(--tr)}
.img-lightbox .lb-close:hover{background:rgba(255,255,255,.2)}

/* ===== TOAST ===== */
.toast{position:fixed;top:20px;right:20px;background:var(--card);padding:.9rem 1.4rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.12);z-index:20000;display:flex;align-items:center;gap:10px;transform:translateX(140%);transition:.4s cubic-bezier(.68,-.55,.27,1.55);border:1px solid var(--bd);font-size:.85rem;font-weight:600;max-width:400px}
.toast.show{transform:translateX(0)}
.toast.success{border-left:4px solid #10b981}.toast.success .toast-icon{color:#10b981}
.toast.error{border-left:4px solid #ef4444}.toast.error .toast-icon{color:#ef4444}
.toast-icon{font-size:1.15rem;flex-shrink:0}

.empty{text-align:center;padding:3rem 1rem;color:var(--t4)}.empty i{font-size:2.5rem;margin-bottom:.75rem;opacity:.25;display:block}.empty p{font-size:.85rem}

@media(max-width:768px){
    .mobile-toggle{display:flex}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.sidebar-overlay.open{display:block}
    .dashboard-content{margin-left:0;padding:1.2rem;padding-top:64px}
    .page-header{flex-direction:column;align-items:flex-start}
    .card-header{flex-direction:column;align-items:flex-start}.search-box{width:100%}
    .form-grid{grid-template-columns:1fr}
    .mini-stats{gap:.5rem}.mini-stat{padding:8px 12px;font-size:.72rem}.mini-stat.big{padding:10px 14px}.mini-stat.big b{font-size:1rem}
    .filter-bar{gap:4px}.filter-tab{padding:6px 12px;font-size:.7rem}
    .modal-foot{flex-direction:column}.modal-foot .btn{width:100%;justify-content:center}
}
    </style>
</head>
<body>

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="dashboard-layout">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><div class="brand-icon">⚙️</div><span>Admin <em>Panel</em></span></div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <a href="dashboard.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard</a>
                <a href="orders.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-box"></i></span> Pesanan</a>
                <a href="layanan.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-shirt"></i></span> Layanan</a>
                <a href="users.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-users"></i></span> Users</a>
                <a href="payments.php" class="sidebar-link active"><span class="link-icon"><i class="fa-solid fa-credit-card"></i></span> Pembayaran</a>
                <a href="reports.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-chart-line"></i></span> Laporan</a>
            </div>
            <div class="sidebar-section">
                <a href="<?= APP_URL ?>/index.php" class="sidebar-link"><span class="link-icon"><i class="fa-solid fa-globe"></i></span> Ke Website</a>
                <a href="<?= APP_URL ?>/user/logout.php" class="sidebar-link danger-link"><span class="link-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Keluar</a>
            </div>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <div class="user-avatar" style="background:linear-gradient(135deg,var(--pk3),var(--pk1))"><?= strtoupper(substr($sessName,0,1)) ?></div>
                <div><div class="user-name"><?= htmlspecialchars($sessName) ?></div><div class="user-role">Administrator</div></div>
            </div>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="dashboard-content">
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <div class="page-title"><i class="fa-solid fa-credit-card"></i> Kelola Pembayaran</div>
                <div class="page-sub"><?= number_format($totalPay) ?> transaksi tercatat</div>
            </div>
        </div>

        <div class="mini-stats">
            <div class="mini-stat big"><i class="fa-solid fa-wallet" style="color:var(--pk3)"></i><b><?= formatRupiah($totalRevenue) ?></b><span>Revenue Dibayar</span></div>
            <div class="mini-stat"><i class="fa-solid fa-circle-check" style="color:#059669"></i><b><?= $cPaid ?></b><span>Dibayar</span></div>
            <div class="mini-stat"><i class="fa-solid fa-clock" style="color:#d97706"></i><b><?= $cPending ?></b><span>Pending</span></div>
            <div class="mini-stat"><i class="fa-solid fa-truck" style="color:#ea580c"></i><b><?= $cCodMenunggu ?></b><span>COD Menunggu</span></div>
            <div class="mini-stat"><i class="fa-solid fa-image" style="color:#3b82f6"></i><b><?= $cCodBukti ?></b><span>Bukti Dikirim</span></div>
            <div class="mini-stat"><i class="fa-solid fa-circle-xmark" style="color:#dc2626"></i><b><?= $cFailed ?></b><span>Gagal</span></div>
        </div>

        <div class="filter-bar">
            <a href="<?= $filterLink() ?>" class="filter-tab <?= !$filterStatus ? 'active' : '' ?>"><i class="fa-solid fa-list"></i> Semua <span class="ft-count"><?= $totalPay ?></span></a>
            <a href="<?= $filterLink('pending') ?>" class="filter-tab <?= $filterStatus==='pending' ? 'active' : '' ?>"><i class="fa-solid fa-clock"></i> Pending <span class="ft-count"><?= $cPending ?></span></a>
            <a href="<?= $filterLink('paid') ?>" class="filter-tab <?= in_array($filterStatus,['paid','dibayar']) ? 'active' : '' ?>"><i class="fa-solid fa-circle-check"></i> Dibayar <span class="ft-count"><?= $cPaid ?></span></a>
            <a href="<?= $filterLink('cod_menunggu') ?>" class="filter-tab cod-tab <?= $filterStatus==='cod_menunggu' ? 'active' : '' ?>"><i class="fa-solid fa-truck"></i> COD Menunggu <span class="ft-count"><?= $cCodMenunggu ?></span></a>
            <a href="<?= $filterLink('cod_bukti') ?>" class="filter-tab bukti-tab <?= $filterStatus==='cod_bukti' ? 'active' : '' ?>"><i class="fa-solid fa-image"></i> Bukti Dikirim <span class="ft-count"><?= $cCodBukti ?></span></a>
            <a href="<?= $filterLink('gagal') ?>" class="filter-tab <?= in_array($filterStatus,['gagal','refund']) ? 'active' : '' ?>"><i class="fa-solid fa-circle-xmark"></i> Gagal <span class="ft-count"><?= $cFailed ?></span></a>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-table-list"></i> Riwayat Pembayaran</div>
                <form method="GET" class="search-box" style="display:flex">
                    <?php if ($filterStatus): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Cari kode order / nama...">
                    <button type="submit" style="padding:8px 14px;border:1px solid var(--bd);border-radius:10px;background:var(--bg2);color:var(--t3);cursor:pointer;margin-left:8px;transition:var(--tr)" onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background='var(--bg2)'"><i class="fa-solid fa-search"></i></button>
                </form>
            </div>
            <div class="table-wrapper">
                <?php if (!empty($allPayments)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Bukti</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th style="width:140px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allPayments as $p):
                        $pid = (int)$p[$pIdCol];
                        $pOrderCode = htmlspecialchars($p['order_code'] ?? '-');
                        $pAmount = (float)($p[$pAmountCol] ?? 0);
                        $pMethod = strtolower($p[$pMethodCol] ?? '-');
                        $pStatus = strtolower($p[$pStatusCol] ?? '');
                        $pDate = !empty($p[$pDateCol]) ? date('d M Y, H:i', strtotime($p[$pDateCol])) : '-';
                        $pProof = $p[$pProofCol] ?? '';
                        $cName = htmlspecialchars($p['customer_name'] ?? '-');
                        $cPhone = htmlspecialchars($p['customer_phone'] ?? '');

                        $rowClass = '';
                        $amtClass = 'amt-pending';
                        if (in_array($pStatus, ['paid','dibayar'])) { $amtClass = 'amt-paid'; }
                        elseif ($pStatus === 'cod_menunggu') { $rowClass = 'row-cod'; $amtClass = 'amt-cod'; }
                        elseif ($pStatus === 'cod_bukti') { $rowClass = 'row-bukti'; $amtClass = 'amt-bukti'; }
                        elseif (in_array($pStatus, ['gagal','refund'])) { $rowClass = 'row-failed'; $amtClass = 'amt-failed'; }

                        $proofPath = '';
                        if ($pProof) {
                            $proofPath = (strpos($pProof, 'http') === 0 || strpos($pProof, '//') === 0) ? $pProof : APP_URL . '/uploads/bukti_cod/' . $pProof;
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><span style="font-weight:800;color:var(--t3);font-size:.78rem">#<?= str_pad($pid, 4, '0', STR_PAD_LEFT) ?></span></td>
                        <td><span style="font-weight:700;color:var(--t1);font-size:.82rem"><?= $pOrderCode ?></span></td>
                        <td>
                            <div class="customer-cell">
                                <div class="cc-name"><?= $cName ?></div>
                                <?php if ($cPhone): ?><div class="cc-sub"><i class="fa-solid fa-phone" style="font-size:.55rem;margin-right:2px"></i><?= $cPhone ?></div><?php endif; ?>
                            </div>
                        </td>
                        <td><?= methodIcon($pMethod) ?></td>
                        <td><div class="amount-cell <?= $amtClass ?>"><?= formatRupiah($pAmount) ?></div></td>
                        <td>
                            <?php if ($proofPath): ?>
                            <img src="<?= $proofPath ?>" class="proof-thumb" onclick="openLightbox('<?= $proofPath ?>')" alt="Bukti" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="proof-none" style="display:none"><i class="fa-solid fa-image-slash"></i></div>
                            <?php else: ?>
                            <div class="proof-none"><i class="fa-solid fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><?= statusBadge($pStatus) ?></td>
                        <td><span style="font-size:.75rem;color:var(--t4);white-space:nowrap"><?= $pDate ?></span></td>
                        <td>
                            <div class="action-btns">
                                <button class="act-btn act-view" title="Detail" onclick='openDetail(<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>)'><i class="fa-solid fa-eye"></i></button>

                                <?php if ($pMethod === 'cod' && $pStatus === 'cod_menunggu'): ?>
                                <button class="act-btn act-upload" title="Upload Bukti COD" onclick="openUploadModal(<?= $pid ?>)"><i class="fa-solid fa-camera"></i></button>
                                <?php endif; ?>

                                <?php if ($pMethod === 'cod' && $pStatus === 'cod_bukti' && $proofPath): ?>
                                <button class="act-btn act-check" title="Verifikasi Bukti" onclick="openVerifyModal(<?= $pid ?>, '<?= $proofPath ?>')"><i class="fa-solid fa-check"></i></button>
                                <?php endif; ?>

                                <a href="?delete=<?= $pid ?>" onclick="return confirm('Hapus data pembayaran ini?')" class="act-btn act-delete" title="Hapus"><i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty"><i class="fa-solid fa-credit-card"></i><p><?= ($filterStatus || $searchQ) ? 'Tidak ada pembayaran yang cocok dengan filter.' : 'Belum ada data pembayaran.' ?></p></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- ===== LIGHTBOX ===== -->
<div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()">
    <button class="lb-close"><i class="fa-solid fa-xmark"></i></button>
    <img id="lightboxImg" src="" alt="Preview" onclick="event.stopPropagation()">
</div>

<!-- ===== MODAL: DETAIL / UPDATE STATUS ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fa-solid fa-eye"></i> Detail Pembayaran</h2>
            <button class="modal-close" onclick="closeModal('detailModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="detailBody"></div>
    </div>
</div>

<!-- ===== MODAL: UPLOAD BUKTI COD ===== -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-head">
            <h2><i class="fa-solid fa-camera" style="color:#ea580c"></i> Upload Bukti COD</h2>
            <button class="modal-close" onclick="closeModal('uploadModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div style="padding:1rem;background:rgba(234,88,12,.05);border:1px solid rgba(234,88,12,.12);border-radius:12px;margin-bottom:1.25rem;font-size:.8rem;color:#9a3412;display:flex;align-items:flex-start;gap:8px;line-height:1.5">
                <i class="fa-solid fa-circle-info" style="margin-top:3px;flex-shrink:0"></i>
                <div>Foto bukti penerimaan uang dari pelanggan. Pastikan foto <strong>jelas</strong> dan menunjukkan nominal yang diterima.</div>
            </div>
            <form id="uploadForm" onsubmit="return submitUpload(this)">
                <input type="hidden" name="action" value="upload_bukti_cod">
                <input type="hidden" name="id_pembayaran" id="upPayId">
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Foto Bukti *</label>
                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('upFile').click()">
                        <input type="file" name="bukti" id="upFile" accept="image/jpeg,image/png,image/webp" required onchange="previewUpload(this)">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <div class="uz-title">Klik atau drag foto ke sini</div>
                        <div class="uz-sub">JPG / PNG / WebP, maks 5MB</div>
                    </div>
                    <div class="upload-preview" id="uploadPreview">
                        <img id="uploadPreviewImg" src="" alt="Preview">
                    </div>
                </div>
                <div class="modal-foot" style="padding:0;border:none;margin-top:1rem">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('uploadModal')">Batal</button>
                    <button type="submit" class="btn btn-orange" id="upSubmitBtn"><i class="fa-solid fa-upload"></i> Upload Bukti</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL: VERIFIKASI BUKTI COD ===== -->
<div class="modal-overlay" id="verifyModal">
    <div class="modal-box" style="max-width:620px">
        <div class="modal-head">
            <h2><i class="fa-solid fa-shield-halved" style="color:#3b82f6"></i> Verifikasi Bukti COD</h2>
            <button class="modal-close" onclick="closeModal('verifyModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:1.25rem">
                <div style="font-size:.78rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem">Bukti Pembayaran</div>
                <img id="verifyImg" src="" alt="Bukti" style="max-width:100%;max-height:320px;border-radius:14px;border:1px solid var(--bd);object-fit:contain;box-shadow:var(--shadow);cursor:pointer" onclick="openLightbox(this.src)">
            </div>

            <div style="padding:1rem;background:rgba(59,130,246,.04);border:1px solid rgba(59,130,246,.1);border-radius:12px;margin-bottom:1.25rem;font-size:.8rem;color:#1e40af;display:flex;align-items:flex-start;gap:8px;line-height:1.5">
                <i class="fa-solid fa-shield-halved" style="margin-top:2px;flex-shrink:0"></i>
                <div>Periksa dengan teliti: apakah foto menunjukkan uang yang diterima dari pelanggan dengan nominal yang sesuai? Jika ya, klik <strong>Verifikasi</strong>. Jika tidak jelas atau salah, klik <strong>Tolak</strong>.</div>
            </div>

            <input type="hidden" id="verifyPayId">
            <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
                <button class="btn btn-red" onclick="submitVerify('reject')" id="vRejectBtn">
                    <i class="fa-solid fa-xmark"></i> Tolak & Minta Ulang
                </button>
                <button class="btn btn-green" onclick="submitVerify('approve')" id="vApproveBtn">
                    <i class="fa-solid fa-check"></i> Verifikasi & Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== TOAST ===== -->
<div class="toast" id="toast">
    <div class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="toast-msg">Berhasil</div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
window.addEventListener('resize',function(){if(window.innerWidth>768){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}});

// ===== MODAL HELPERS =====
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(function(m){m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.modal-overlay.open').forEach(function(m){m.classList.remove('open')});closeLightbox()}});

// ===== LIGHTBOX =====
function openLightbox(src){document.getElementById('lightboxImg').src=src;document.getElementById('imgLightbox').classList.add('open')}
function closeLightbox(){document.getElementById('imgLightbox').classList.remove('open')}

// ===== TOAST =====
function showToast(msg,type){type=type||'success';var t=document.getElementById('toast');var ic=t.querySelector('.toast-icon');var tx=t.querySelector('.toast-msg');t.className='toast '+type;tx.innerHTML=msg;ic.innerHTML=type==='success'?'<i class="fa-solid fa-circle-check"></i>':'<i class="fa-solid fa-circle-xmark"></i>';t.classList.add('show');setTimeout(function(){t.classList.remove('show')},3500)}

// ===== FORMAT RUPIAH =====
function fR(n){return 'Rp '+Math.round(n).toLocaleString('id-ID')}

// ===== OPEN DETAIL =====
function openDetail(d){
    var b=document.getElementById('detailBody');
    var pid=d.<?= $pIdCol ?>;
    var pOrderCode=d.order_code||'-';
    var pAmount=d.<?= $pAmountCol ?>||0;
    var pMethod=d.<?= $pMethodCol ?>||'-';
    var pStatus=(d.<?= $pStatusCol ?>||'').toLowerCase();
    var pDate=d.<?= $pDateCol ?>||'';
    var pProof=d.<?= $pProofCol ?>||'';
    var cName=d.customer_name||'-';
    var cEmail=d.customer_email||'';
    var cPhone=d.customer_phone||'';
    var orderTotal=d.order_total||0;

    var proofPath='';
    if(pProof){proofPath=(pProof.indexOf('http')===0||pProof.indexOf('//')===0)?pProof:'<?= APP_URL ?>/uploads/bukti_cod/'+pProof}

    var fDate='-';
    if(pDate){var dt=new Date(pDate);if(!isNaN(dt))fDate=dt.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'})}

    var isCod=(pMethod==='cod');

    var h='';
    // Header
    h+='<div style="display:flex;align-items:center;gap:14px;margin-bottom:1.25rem;padding:1.1rem;background:var(--bg2);border-radius:14px;border:1px solid var(--bd)">';
    h+='<div style="width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;background:'+(isCod?'linear-gradient(135deg,#ea580c,#c2410c)':'linear-gradient(135deg,var(--pk4),var(--pk2))')+';color:#fff;box-shadow:0 4px 14px '+(isCod?'rgba(234,88,12,.25)':'rgba(236,72,153,.25)')+'"><i class="fa-solid '+(isCod?'fa-money-bill-wave':'fa-receipt')+'"></i></div>';
    h+='<div><div style="font-size:1.05rem;font-weight:900;color:var(--t1)">Pembayaran #'+String(pid).padStart(4,'0')+'</div>';
    h+='<div style="font-size:.75rem;color:var(--t4)">Order: <b style="color:var(--t1)">'+pOrderCode+'</b>'+(isCod?' <span style="color:#ea580c;font-weight:700">• COD</span>':'')+'</div></div></div>';

    // Info rows
    h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-user" style="margin-right:4px;font-size:.6rem"></i>Customer</div><div class="info-value">'+cName+(cPhone?' <span style="color:var(--t4);font-size:.72rem">('+cPhone+')</span>':'')+'</div></div>';
    if(cEmail)h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-envelope" style="margin-right:4px;font-size:.6rem"></i>Email</div><div class="info-value" style="font-size:.8rem;color:var(--t3)">'+cEmail+'</div></div>';
    h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-money-bill" style="margin-right:4px;font-size:.6rem"></i>Jumlah</div><div class="info-value" style="font-size:1.05rem;font-weight:900;color:var(--pk1)">'+fR(pAmount)+'</div></div>';
    if(orderTotal>0)h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-receipt" style="margin-right:4px;font-size:.6rem"></i>Total Order</div><div class="info-value">'+fR(orderTotal)+'</div></div>';
    h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-credit-card" style="margin-right:4px;font-size:.6rem"></i>Metode</div><div class="info-value">'+pMethod+'</div></div>';
    h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-calendar" style="margin-right:4px;font-size:.6rem"></i>Tanggal</div><div class="info-value">'+fDate+'</div></div>';
    h+='<div class="info-row"><div class="info-label"><i class="fa-solid fa-flag" style="margin-right:4px;font-size:.6rem"></i>Status</div><div class="info-value">'+getSB(pStatus)+'</div></div>';

    // Proof
    if(proofPath){
        h+='<div style="margin-top:.75rem"><div style="font-size:.78rem;font-weight:700;color:var(--t1);margin-bottom:.5px;display:flex;align-items:center;gap:6px"><i class="fa-solid fa-image" style="color:var(--pk3);font-size:.7rem"></i> Bukti Pembayaran</div>';
        h+='<div class="proof-preview"><img src="'+proofPath+'" onclick="openLightbox(\''+proofPath+'\')" onerror="this.parentElement.innerHTML=\'<span style=color:var(--t4);font-size:.8rem>Gagal memuat gambar</span>\'" alt="Bukti"></div></div>';
    }

    // COD-specific actions
    if(isCod && pStatus==='cod_menunggu'){
        h+='<div class="cod-action-box cod-wait"><div class="cod-action-title"><i class="fa-solid fa-truck" style="color:#ea580c"></i> Aksi COD — Menunggu Kurir</div>';
        h+='<p style="font-size:.82rem;color:var(--t3);margin-bottom:1rem">Pesanan ini menggunakan metode COD. Kurir belum mengupload bukti penerimaan uang.</p>';
        h+='<button class="btn btn-orange" onclick="closeModal(\'detailModal\');openUploadModal('+pid+')"><i class="fa-solid fa-camera"></i> Upload Bukti Sekarang</button></div>';
    }
    if(isCod && pStatus==='cod_bukti' && proofPath){
        h+='<div class="cod-action-box cod-verify"><div class="cod-action-title"><i class="fa-solid fa-shield-halved" style="color:#3b82f6"></i> Verifikasi Bukti COD</div>';
        h+='<p style="font-size:.82rem;color:var(--t3);margin-bottom:1rem">Kurir sudah mengupload bukti. Periksa dan tentukan apakah bukti valid.</p>';
        h+='<div style="display:flex;gap:.5rem;flex-wrap:wrap">';
        h+='<button class="btn btn-red" onclick="closeModal(\'detailModal\');openVerifyModal('+pid+',\''+proofPath+'\')"><i class="fa-solid fa-xmark"></i> Tolak</button>';
        h+='<button class="btn btn-green" onclick="closeModal(\'detailModal\');openVerifyModal('+pid+',\''+proofPath+'\')"><i class="fa-solid fa-check"></i> Verifikasi</button>';
        h+='</div></div>';
    }

    // Manual status update form (non-COD or fallback)
    if(!isCod || !in_array(pStatus,['cod_menunggu','cod_bukti'])){
        h+='<form method="POST" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--bd)">';
        h+='<input type="hidden" name="update_status" value="1"><input type="hidden" name="pid" value="'+pid+'">';
        h+='<div style="font-size:.82rem;font-weight:800;color:var(--t1);margin-bottom:.75rem;display:flex;align-items:center;gap:6px"><i class="fa-solid fa-pen-to-square" style="color:var(--pk3);font-size:.75rem"></i> Update Status Manual</div>';
        h+='<div class="form-grid"><div class="form-group full"><label class="form-label">Status</label><select name="new_status" class="form-input">';
        h+='<option value="pending"'+(pStatus==='pending'?' selected':'')+'>Pending</option>';
        h+='<option value="paid"'+(in_array(pStatus,['paid','dibayar'])?' selected':'')+'>Dibayar (Lunas)</option>';
        h+='<option value="gagal"'+(pStatus==='gagal'?' selected':'')+'>Gagal / Ditolak</option>';
        h+='<option value="refund"'+(pStatus==='refund'?' selected':'')+'>Refund</option>';
        h+='</select></div></div>';
        h+='<div class="status-actions"><button type="button" class="btn btn-cancel" onclick="closeModal(\'detailModal\')">Tutup</button>';
        h+='<button type="submit" class="btn btn-green"><i class="fa-solid fa-check"></i> Simpan</button></div></form>';
    } else {
        h+='<div class="status-actions" style="margin-top:1rem"><button type="button" class="btn btn-cancel" onclick="closeModal(\'detailModal\')">Tutup</button></div>';
    }

    b.innerHTML=h;
    document.getElementById('detailModal').classList.add('open');
}

// Status badge for JS
function getSB(s){
    var map={
        'pending':['fa-clock','#d97706','rgba(245,158,11,.1)','rgba(245,158,11,.15)','Pending'],
        'paid':['fa-circle-check','#059669','rgba(16,185,129,.1)','rgba(16,185,129,.15)','Dibayar'],
        'dibayar':['fa-circle-check','#059669','rgba(16,185,129,.1)','rgba(16,185,129,.15)','Dibayar'],
        'cod_menunggu':['fa-truck','#ea580c','rgba(234,88,12,.1)','rgba(234,88,12,.15)','COD Menunggu'],
        'cod_bukti':['fa-image','#3b82f6','rgba(59,130,246,.1)','rgba(59,130,246,.15)','Bukti Dikirim'],
        'gagal':['fa-circle-xmark','#dc2626','rgba(239,68,68,.1)','rgba(239,68,68,.15)','Gagal'],
        'refund':['fa-rotate-left','#6b7280','rgba(107,114,128,.1)','rgba(107,114,128,.15)','Refund']
    };
    if(s==='success'||s==='lunas')s='paid';
    var m=map[s]||['fa-circle','#94a3b8','rgba(148,163,184,.1)','rgba(148,163,184,.15)',s];
    return '<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:8px;font-size:.7rem;font-weight:700;color:'+m[1]+';background:'+m[2]+';border:1px solid '+m[3]+'"><i class="fa-solid '+m[0]+'" style="font-size:.6rem"></i>'+m[4]+'</span>';
}

// ===== UPLOAD BUKTI COD =====
function openUploadModal(payId){
    document.getElementById('upPayId').value=payId;
    document.getElementById('uploadForm').reset();
    document.getElementById('uploadPreview').style.display='none';
    document.getElementById('uploadPreviewImg').src='';
    document.getElementById('uploadModal').classList.add('open');
}

function previewUpload(input){
    if(input.files&&input.files[0]){
        var reader=new FileReader();
        reader.onload=function(e){
            document.getElementById('uploadPreviewImg').src=e.target.result;
            document.getElementById('uploadPreview').style.display='block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitUpload(form){
    var btn=document.getElementById('upSubmitBtn');
    btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Mengupload...';
    var fd=new FormData(form);
    fetch('payments.php',{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.status==='success'){
            showToast(d.message,'success');
            closeModal('uploadModal');
            setTimeout(function(){location.reload()},800);
        }else{
            showToast(d.message,'error');
            btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-upload"></i> Upload Bukti';
        }
    })
    .catch(function(e){showToast('Terjadi kesalahan koneksi','error');btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-upload"></i> Upload Bukti'});
    return false;
}

// ===== VERIFIKASI COD =====
function openVerifyModal(payId,imgSrc){
    document.getElementById('verifyPayId').value=payId;
    document.getElementById('verifyImg').src=imgSrc;
    document.getElementById('vApproveBtn').disabled=false;
    document.getElementById('vRejectBtn').disabled=false;
    document.getElementById('vApproveBtn').innerHTML='<i class="fa-solid fa-check"></i> Verifikasi & Approve';
    document.getElementById('vRejectBtn').innerHTML='<i class="fa-solid fa-xmark"></i> Tolak & Minta Ulang';
    document.getElementById('verifyModal').classList.add('open');
}

function submitVerify(verdict){
    var payId=document.getElementById('verifyPayId').value;
    var btn=verdict==='approve'?document.getElementById('vApproveBtn'):document.getElementById('vRejectBtn');
    var otherBtn=verdict==='approve'?document.getElementById('vRejectBtn'):document.getElementById('vApproveBtn');

    btn.disabled=true;otherBtn.disabled=true;
    btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

    var fd=new FormData();
    fd.append('action','verifikasi_cod');
    fd.append('id_pembayaran',payId);
    fd.append('verdict',verdict);

    fetch('payments.php',{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.status==='success'){
            showToast(d.message,'success');
            closeModal('verifyModal');
            setTimeout(function(){location.reload()},800);
        }else{
            showToast(d.message,'error');
            btn.disabled=false;otherBtn.disabled=false;
            btn.innerHTML=verdict==='approve'?'<i class="fa-solid fa-check"></i> Verifikasi & Approve':'<i class="fa-solid fa-xmark"></i> Tolak & Minta Ulang';
        }
    })
    .catch(function(e){
        showToast('Terjadi kesalahan koneksi','error');
        btn.disabled=false;otherBtn.disabled=false;
        btn.innerHTML=verdict==='approve'?'<i class="fa-solid fa-check"></i> Verifikasi & Approve':'<i class="fa-solid fa-xmark"></i> Tolak & Minta Ulang';
    });
}
</script>
</body>
</html>