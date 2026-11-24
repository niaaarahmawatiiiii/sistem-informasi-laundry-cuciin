<?php
/**
 * Kelola Pesanan - Admin Panel
 * CUCI.IN Theme Version — Konsisten dengan users.php
 */
 $pageTitle = 'Kelola Pesanan - Admin Panel';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireAdmin();

 $flash = getFlash();

// --- Helper Functions ---
function cols($db, $table) {
    try {
        $r = $db->query("SHOW COLUMNS FROM $table");
        return $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'Field') : [];
    } catch (Exception $e) { return []; }
}
function pickCol($cols, $candidates) {
    foreach ($candidates as $c) {
        if (in_array($c, $cols)) return $c;
    }
    return $candidates[0];
}
function tableExists($db, $name) {
    try {
        $r = $db->query("SHOW TABLES LIKE '$name'");
        return $r && $r->num_rows > 0;
    } catch (Exception $e) { return false; }
}
function sv($db, $sql, $d = 0) {
    try {
        $r = $db->query($sql);
        if ($r && $row = $r->fetch_assoc()) return (float)($row['t'] ?? $d);
    } catch (Exception $e) {}
    return $d;
}

// ===== DETECT TABLES =====
 $oCols = cols($db, 'orders');
 $uCols = cols($db, 'users');
 $canQuery = !empty($oCols);

 $hasOD = tableExists($db, 'order_detail') || tableExists($db, 'order_details');
 $odTable = tableExists($db, 'order_detail') ? 'order_detail' : 'order_details';
 $odCols = $hasOD ? cols($db, $odTable) : [];
 $lCols = cols($db, 'layanan');

// ===== DETECT KOLOM =====
 $colId      = pickCol($oCols, ['id_order','order_id','id']);
 $colUser    = pickCol($oCols, ['id_user','user_id','customer_id']);
 $colTotal   = pickCol($oCols, ['total_harga','total','total_bayar','grand_total','amount']);
 $colStatus  = pickCol($oCols, ['status','order_status','state']);
 $colDate    = pickCol($oCols, ['tanggal_order','created_at','tgl_order','tanggal','date_created','created','order_date']);
 $colKode    = in_array('kode_order', $oCols) ? 'kode_order' : '';
 $colAlamat  = in_array('alamat', $oCols) ? 'alamat' : (in_array('address', $oCols) ? 'address' : '');
 $colCatatan = in_array('catatan', $oCols) ? 'catatan' : (in_array('notes', $oCols) ? 'notes' : '');

 $uIdCol    = pickCol($uCols, ['id_user','user_id','id']);
 $uNameCol  = pickCol($uCols, ['nama','name','username','nama_lengkap']);
 $uPhoneCol = pickCol($uCols, ['no_hp','telepon','phone','whatsapp']);
 $uEmailCol = in_array('email', $uCols) ? 'email' : '';
 $uAddrCol  = in_array('alamat', $uCols) ? 'alamat' : '';

 $odIdCol = $hasOD ? pickCol($odCols, ['id_order','order_id']) : '';
 $odLayId = $hasOD ? pickCol($odCols, ['id_layanan','layanan_id','service_id']) : '';
 $odQty   = $hasOD ? pickCol($odCols, ['qty','jumlah','quantity']) : '';
 $odSub   = $hasOD ? pickCol($odCols, ['subtotal','sub_total','harga_total','amount']) : '';

 $lIdCol   = pickCol($lCols, ['id_layanan','id','layanan_id']);
 $lNamaCol = pickCol($lCols, ['nama_layanan','nama','name']);
 $lIconCol = in_array('icon', $lCols) ? 'icon' : '';
 $lSatCol  = in_array('satuan', $lCols) ? 'satuan' : '';

// ===== STATUS MAP =====
 $statusMap = [
    'pending'      => ['label'=>'Pending',      'color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)',  'icon'=>'fa-clock'],
    'dikonfirmasi' => ['label'=>'Dikonfirmasi', 'color'=>'#6366f1','bg'=>'rgba(99,102,241,.1)',  'icon'=>'fa-circle-check'],
    'diproses'     => ['label'=>'Diproses',     'color'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)',  'icon'=>'fa-spinner'],
    'dicuci'       => ['label'=>'Dicuci',       'color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.1)',  'icon'=>'fa-water'],
    'disetrika'    => ['label'=>'Disetrika',    'color'=>'#ec4899','bg'=>'rgba(236,72,153,.1)',  'icon'=>'fa-temperature-high'],
    'siap_ambil'   => ['label'=>'Siap Ambil',   'color'=>'#10b981','bg'=>'rgba(16,185,129,.1)',  'icon'=>'fa-box-open'],
    'diantar'      => ['label'=>'Diantar',      'color'=>'#06b6d4','bg'=>'rgba(6,182,212,.1)',   'icon'=>'fa-truck-fast'],
    'selesai'      => ['label'=>'Selesai',      'color'=>'#22c55e','bg'=>'rgba(34,197,94,.1)',   'icon'=>'fa-circle-check'],
    'dibatalkan'   => ['label'=>'Dibatalkan',   'color'=>'#ef4444','bg'=>'rgba(239,68,68,.1)',   'icon'=>'fa-circle-xmark'],
];

 $statusAliases = [
    'menunggu'  => 'pending',
    'done'      => 'selesai',
    'batal'     => 'dibatalkan',
    'cancelled' => 'dibatalkan',
];

function statusBadge($s) {
    global $statusMap, $statusAliases;
    $s = $statusAliases[$s] ?? $s;
    $m = $statusMap[$s] ?? ['label'=>ucfirst($s),'color'=>'#94a3b8','bg'=>'rgba(148,163,184,.1)','icon'=>'fa-circle'];
    return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:.72rem;font-weight:700;color:'.$m['color'].';background:'.$m['bg'].';border:1px solid '.$m['color'].'40"><i class="fa-solid '.$m['icon'].'" style="font-size:.65rem"></i>'.$m['label'].'</span>';
}

// ===== HANDLE AJAX UPDATE STATUS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_status']) && $canQuery) {
    header('Content-Type: application/json');
    $oid = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($oid && $newStatus) {
        $ns = $db->real_escape_string($newStatus);
        try {
            $db->query("UPDATE orders SET $colStatus='$ns' WHERE $colId=$oid");
            $label = isset($statusMap[$newStatus]) ? $statusMap[$newStatus]['label'] : ucfirst($newStatus);
            echo json_encode(['status'=>'success', 'message'=>'Status diubah ke <b>'.$label.'</b>']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status'=>'error', 'message'=>'Database Error: ' . $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['status'=>'error', 'message'=>'Data tidak valid.']);
    exit;
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && $canQuery) {
    $delId = (int)$_GET['delete'];
    if ($odIdCol) {
        try { $db->query("DELETE FROM $odTable WHERE $odIdCol=$delId"); } catch (Exception $e) {}
    }
    try { $db->query("DELETE FROM orders WHERE $colId=$delId"); } catch (Exception $e) {}
    setFlash('success', 'Pesanan berhasil dihapus.');
    header('Location: orders.php');
    exit;
}

// ===== FILTERS & DATA =====
 $filterStatus = $_GET['filter'] ?? '';
 $searchQ = trim($_GET['q'] ?? '');

 $allOrders = [];
if ($canQuery) {
    $wp = [];
    if ($filterStatus) $wp[] = "o.$colStatus='".$db->real_escape_string($filterStatus)."'";
    if ($searchQ) {
        $sq = $db->real_escape_string($searchQ);
        $searchParts = ["o.$colId LIKE '%$sq%'"];
        if ($colKode) $searchParts[] = "o.$colKode LIKE '%$sq%'";
        if ($uNameCol) $searchParts[] = "u.$uNameCol LIKE '%$sq%'";
        if ($uPhoneCol) $searchParts[] = "u.$uPhoneCol LIKE '%$sq%'";
        $wp[] = '(' . implode(' OR ', $searchParts) . ')';
    }
    $wSql = !empty($wp) ? 'WHERE ' . implode(' AND ', $wp) : '';
    try {
        $sql = "SELECT o.*, u.$uNameCol as user_name";
        if ($uPhoneCol) $sql .= ", u.$uPhoneCol as user_phone";
        if ($uEmailCol) $sql .= ", u.$uEmailCol as user_email";
        if ($uAddrCol) $sql .= ", u.$uAddrCol as user_address";
        $sql .= " FROM orders o LEFT JOIN users u ON u.$uIdCol=o.$colUser $wSql ORDER BY o.$colDate DESC LIMIT 200";
        $oq = $db->query($sql);
        if ($oq) $allOrders = $oq->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {}
}

// Stats
 $counts = [];
foreach (array_keys($statusMap) as $st) $counts[$st] = 0;
 $totalAll = 0;
if ($canQuery) {
    try {
        $totalAll = (int)$db->query("SELECT COUNT(*) as t FROM orders")->fetch_assoc()['t'];
        $cntQ = $db->query("SELECT $colStatus as s, COUNT(*) as c FROM orders GROUP BY $colStatus");
        if ($cntQ) {
            while ($r = $cntQ->fetch_assoc()) {
                if (isset($r['s'])) $counts[$r['s']] = (int)($r['c'] ?? 0);
            }
        }
    } catch (Exception $e) {}
}

// ===== REVENUE STATS =====
 $totalRevenue = 0;
if ($canQuery) {
    try {
        $totalRevenue = (float)sv($db, "SELECT COALESCE(SUM($colTotal),0) as t FROM orders WHERE $colStatus='selesai'", 0);
    } catch (Exception $e) {}
}

// ===== DETAIL AJAX =====
if (isset($_GET['detail']) && isset($_GET['ajax']) && $canQuery) {
    $did = (int)$_GET['detail'];
    $detailOrder = null;
    $detailItems = [];
    try {
        $sql = "SELECT o.*, u.$uNameCol as user_name";
        if ($uPhoneCol) $sql .= ", u.$uPhoneCol as user_phone";
        if ($uEmailCol) $sql .= ", u.$uEmailCol as user_email";
        if ($uAddrCol) $sql .= ", u.$uAddrCol as user_address";
        $sql .= " FROM orders o LEFT JOIN users u ON u.$uIdCol=o.$colUser WHERE o.$colId=$did";
        $dr = $db->query($sql);
        if ($dr) $detailOrder = $dr->fetch_assoc();
    } catch (Exception $e) {}

    if ($detailOrder) {
        if ($odIdCol && $odLayId) {
            try {
                $diq = $db->query("SELECT od.*, l.$lNamaCol as layanan_nama, l.$lIconCol as layanan_icon, l.$lSatCol as layanan_satuan FROM $odTable od LEFT JOIN layanan l ON l.$lIdCol=od.$odLayId WHERE od.$odIdCol=$did");
                if ($diq) $detailItems = $diq->fetch_all(MYSQLI_ASSOC);
            } catch (Exception $e) {}
        }

        $id = (int)$detailOrder[$colId];
        $displayId = ($colKode && !empty($detailOrder[$colKode])) ? htmlspecialchars($detailOrder[$colKode]) : '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
        $user = htmlspecialchars($detailOrder['user_name'] ?? 'Guest');
        $userPhone = htmlspecialchars($detailOrder['user_phone'] ?? '-');
        $rawStatus = $statusAliases[$detailOrder[$colStatus] ?? ''] ?? ($detailOrder[$colStatus] ?? 'pending');
        $dateOrder = !empty($detailOrder[$colDate]) ? date('d F Y, H:i', strtotime($detailOrder[$colDate])) : '-';
        $totalFinal = formatRupiah($detailOrder[$colTotal]);
        $addressOrder = ($colAlamat && !empty($detailOrder[$colAlamat])) ? htmlspecialchars($detailOrder[$colAlamat]) : '';
        $notesOrder = ($colCatatan && !empty($detailOrder[$colCatatan])) ? htmlspecialchars($detailOrder[$colCatatan]) : '';

        $statusOptions = '';
        foreach ($statusMap as $key => $val) {
            $selected = ($key === $rawStatus) ? 'selected' : '';
            $statusOptions .= '<option value="'.$key.'" '.$selected.'>'.$val['label'].'</option>';
        }

        $steps = ['pending', 'dikonfirmasi', 'diproses', 'dicuci', 'disetrika', 'siap_ambil', 'diantar', 'selesai'];
        if ($rawStatus === 'dibatalkan') $steps = ['pending', 'dibatalkan'];
        $currentIndex = array_search($rawStatus, $steps);

        $itemRows = '';
        foreach ($detailItems as $it) {
            $name = htmlspecialchars($it['layanan_nama'] ?? 'Produk');
            $qty = (float)($it[$odQty] ?? 1);
            $price = formatRupiah($it[$odSub] ?? 0);
            $itemRows .= '<tr><td style="padding:12px 15px;font-weight:600">'.$name.'</td><td style="text-align:center">x'.$qty.'</td><td style="text-align:right;font-weight:700;color:var(--pk1)">'.$price.'</td></tr>';
        }
        ?>
        <style>
            .d-modal-stepper{display:flex;justify-content:space-between;margin-bottom:2rem;position:relative;padding:0 .5rem}
            .d-modal-stepper::before{content:"";position:absolute;top:15px;left:2.5rem;right:2.5rem;height:2px;background:var(--bd);z-index:1}
            .d-step{display:flex;flex-direction:column;align-items:center;gap:.4rem;z-index:2;flex:1}
            .d-step-dot{width:32px;height:32px;border-radius:50%;background:var(--card);border:2px solid var(--bd);display:flex;align-items:center;justify-content:center;font-size:.75rem;color:var(--t4);transition:.3s}
            .d-step-lbl{font-size:.58rem;font-weight:700;color:var(--t4);text-align:center;text-transform:uppercase;letter-spacing:.3px;max-width:60px;line-height:1.2}
            .d-step.active .d-step-dot{background:linear-gradient(135deg,var(--pk3),var(--pk1));border-color:var(--pk1);color:#fff;box-shadow:0 0 0 4px rgba(236,72,153,.15)}
            .d-step.active .d-step-lbl{color:var(--pk1);font-weight:800}
            .d-step.done .d-step-dot{background:var(--pk1);border-color:var(--pk1);color:#fff}
            .d-step.done .d-step-lbl{color:var(--t2)}
            .d-info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-bottom:1.5rem}
            .d-info-card{background:var(--bg);padding:1rem;border-radius:12px;border:1px solid var(--bd);transition:.3s cubic-bezier(.4,0,.2,1)}
            .d-info-card:hover{border-color:var(--bd2);transform:translateY(-1px);box-shadow:var(--shadow)}
            .d-info-label{font-size:.65rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;display:flex;align-items:center;gap:5px}
            .d-info-label i{color:var(--pk3);font-size:.7rem}
            .d-info-val{font-weight:700;color:var(--t1);font-size:.88rem;line-height:1.4}
            .d-items-box{margin-bottom:1.5rem}
            .d-items-box h4{font-size:.88rem;font-weight:800;color:var(--t1);margin-bottom:.75rem;display:flex;align-items:center;gap:8px}
            .d-items-table{width:100%;border-collapse:collapse;background:var(--card);border-radius:12px;overflow:hidden;border:1px solid var(--bd)}
            .d-items-table th{text-align:left;padding:10px 14px;font-size:.65rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;background:var(--bg);border-bottom:1px solid var(--bd)}
            .d-items-table td{padding:11px 14px;font-size:.82rem;border-bottom:1px solid var(--bd);color:var(--t2)}
            .d-items-table tr:last-child td{border-bottom:none}
            .d-status-section{background:linear-gradient(135deg,var(--bg),#fff);border:1px solid var(--bd);border-radius:14px;padding:1.25rem;position:relative;overflow:hidden}
            .d-status-section::before{content:"";position:absolute;top:0;left:0;width:4px;height:100%;background:linear-gradient(180deg,var(--pk3),var(--pk1))}
            .d-status-form{display:flex;gap:.75rem;margin-top:.75rem;flex-wrap:wrap}
            .d-status-select{flex:1;min-width:200px;padding:10px 14px;border:1.5px solid var(--bd);border-radius:10px;font-size:.85rem;font-family:inherit;font-weight:600;background:var(--card);color:var(--t1);outline:none;transition:.3s;cursor:pointer}
            .d-status-select:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)}
            .d-status-btn{padding:10px 22px;border-radius:10px;border:none;background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;font-weight:700;font-size:.82rem;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.3s cubic-bezier(.4,0,.2,1);box-shadow:0 4px 14px rgba(236,72,153,.25)}
            .d-status-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(236,72,153,.35)}
            .d-note-text{font-size:.72rem;color:var(--t4);margin-top:.6rem}
            @media(max-width:500px){.d-info-grid{grid-template-columns:1fr}.d-modal-stepper{overflow-x:auto;gap:0}.d-step{min-width:50px}}
        </style>

        <div class="modal-head">
            <h2 style="display:flex;align-items:center;gap:10px"><i class="fa-solid fa-receipt" style="color:var(--pk3)"></i> Detail Pesanan</h2>
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:1.5rem">

            <div style="text-align:center;margin-bottom:1.25rem">
                <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 16px;background:var(--bg2);border-radius:999px;font-weight:800;font-size:.82rem;color:var(--pk1);border:1px solid var(--bd)">
                    <i class="fa-solid fa-hashtag"></i> <?= $displayId ?>
                </div>
            </div>

            <div class="d-modal-stepper">
                <?php foreach($steps as $idx => $s):
                    $cls = '';
                    if ($idx < $currentIndex) $cls = 'done';
                    elseif ($idx === $currentIndex) $cls = 'active';
                    $icon = $statusMap[$s]['icon'] ?? 'fa-circle';
                ?>
                    <div class="d-step <?= $cls ?>">
                        <div class="d-step-dot"><i class="fa-solid <?= $icon ?>"></i></div>
                        <div class="d-step-lbl"><?= $statusMap[$s]['label'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-info-grid">
                <div class="d-info-card">
                    <div class="d-info-label"><i class="fa-solid fa-user"></i> Pelanggan</div>
                    <div class="d-info-val"><?= $user ?></div>
                    <div style="font-size:.75rem;color:var(--t4);margin-top:2px"><?= $userPhone ?></div>
                </div>
                <div class="d-info-card">
                    <div class="d-info-label"><i class="fa-solid fa-signal"></i> Status</div>
                    <div class="d-info-val" style="margin-top:5px"><?= statusBadge($rawStatus) ?></div>
                </div>
                <div class="d-info-card">
                    <div class="d-info-label"><i class="fa-regular fa-calendar-alt"></i> Waktu Order</div>
                    <div class="d-info-val"><?= $dateOrder ?></div>
                </div>
                <div class="d-info-card">
                    <div class="d-info-label"><i class="fa-solid fa-wallet"></i> Total Tagihan</div>
                    <div class="d-info-val" style="color:var(--pk1);font-size:1.15rem"><?= $totalFinal ?></div>
                </div>
                <?php if($addressOrder): ?>
                <div class="d-info-card" style="grid-column:1/-1">
                    <div class="d-info-label"><i class="fa-solid fa-location-dot"></i> Alamat</div>
                    <div class="d-info-val" style="font-weight:500"><?= nl2br($addressOrder) ?></div>
                </div>
                <?php endif; ?>
                <?php if($notesOrder): ?>
                <div class="d-info-card" style="grid-column:1/-1">
                    <div class="d-info-label"><i class="fa-solid fa-comment-dots"></i> Catatan</div>
                    <div class="d-info-val" style="font-style:italic;color:var(--t3);font-weight:500"><?= nl2br($notesOrder) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if(!empty($detailItems)): ?>
            <div class="d-items-box">
                <h4><i class="fa-solid fa-list-check" style="color:var(--pk3)"></i> Rincian Layanan <span style="font-size:.7rem;background:var(--bg2);padding:3px 10px;border-radius:999px;font-weight:700;color:var(--t3);margin-left:auto"><?= count($detailItems) ?> item</span></h4>
                <table class="d-items-table">
                    <thead><tr><th>Layanan</th><th style="text-align:center">QTY</th><th style="text-align:right">Subtotal</th></tr></thead>
                    <tbody><?= $itemRows ?></tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="d-status-section">
                <h4 style="font-size:.88rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px">
                    <i class="fa-solid fa-rotate" style="color:var(--pk3)"></i> Ubah Status Pesanan
                </h4>
                <form onsubmit="return submitStatusUpdate(this)">
                    <input type="hidden" name="ajax_update_status" value="1">
                    <input type="hidden" name="order_id" value="<?= $id ?>">
                    <div class="d-status-form">
                        <select name="new_status" class="d-status-select"><?= $statusOptions ?></select>
                        <button type="submit" class="d-status-btn"><i class="fa-solid fa-save"></i> Update Status</button>
                    </div>
                </form>
                <p class="d-note-text"><i class="fa-solid fa-circle-info"></i> Perubahan status otomatis tersimpan dan muncul di akun pelanggan.</p>
            </div>
        </div>
        <?php
    } else {
        echo '<div style="text-align:center;padding:3rem;color:var(--t4)"><i class="fa-solid fa-receipt fa-2x" style="opacity:.3"></i><p style="margin-top:1rem">Pesanan tidak ditemukan.</p></div>';
    }
    exit;
}

 $filterLink = function ($f = '') use ($searchQ) {
    $p = [];
    if ($f) $p[] = 'filter=' . urlencode($f);
    if ($searchQ) $p[] = 'q=' . urlencode($searchQ);
    return 'orders.php' . (!empty($p) ? '?' . implode('&', $p) : '');
};
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

/* ===== SIDEBAR — IDENTIK DENGAN users.php ===== */
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

/* ===== CONTENT ===== */
.dashboard-content{flex:1;margin-left:var(--sidebar-w);padding:2rem 2.5rem;min-height:100vh;position:relative}
.dashboard-content::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(249,168,212,.08),transparent 70%);pointer-events:none;z-index:0}

/* ===== ALERT ===== */
.alert{padding:.85rem 1.25rem;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;animation:alertIn .4s ease}
@keyframes alertIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.15)}
.alert-danger{background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.15)}

/* ===== PAGE HEADER ===== */
.page-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;position:relative;z-index:1}
.page-title{font-size:1.5rem;font-weight:900;color:var(--t1);display:flex;align-items:center;gap:10px}.page-title i{color:var(--pk3)}
.page-sub{font-size:.82rem;color:var(--t4);margin-top:2px}

/* ===== MINI STATS ===== */
.mini-stats{display:flex;gap:.75rem;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.5rem}
.mini-stat{display:flex;align-items:center;gap:8px;padding:10px 18px;background:var(--card);border:1px solid var(--bd);border-radius:12px;box-shadow:var(--shadow);font-size:.78rem;font-weight:600;transition:var(--tr)}
.mini-stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
.mini-stat i{font-size:.75rem}.mini-stat b{color:var(--t1);font-size:1rem;font-weight:900}.mini-stat span{color:var(--t4)}

/* ===== FILTER BAR ===== */
.filter-bar{display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:1.25rem}
.filter-tab{padding:7px 16px;border-radius:20px;font-size:.75rem;font-weight:700;border:1px solid var(--bd);background:var(--card);color:var(--t3);cursor:pointer;transition:var(--tr);display:flex;align-items:center;gap:5px}
.filter-tab:hover{border-color:var(--bd2);color:var(--t1)}
.filter-tab.active{background:linear-gradient(135deg,var(--pk3),var(--pk1));color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(236,72,153,.2)}
.filter-tab .ft-count{font-size:.65rem;padding:1px 6px;border-radius:8px;background:rgba(0,0,0,.08)}.filter-tab.active .ft-count{background:rgba(255,255,255,.2)}

/* ===== CARD ===== */
.card{background:var(--card);border:1px solid var(--bd);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;transition:var(--tr);position:relative;z-index:1}
.card-header{padding:1rem 1.4rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
.card-title{font-size:.92rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}.card-title i{color:var(--pk3);font-size:.85rem}

/* ===== SEARCH ===== */
.search-box{position:relative;width:300px}
.search-box input{width:100%;padding:8px 14px 8px 36px;border:1px solid var(--bd);border-radius:10px;font-size:.8rem;font-family:inherit;background:var(--bg);color:var(--t1);transition:var(--tr);outline:none}
.search-box input:focus{border-color:var(--pk3);box-shadow:0 0 0 3px rgba(236,72,153,.08)}
.search-box input::placeholder{color:var(--t4)}
.search-box>i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--t4);font-size:.78rem}

/* ===== TABLE ===== */
.table-wrapper{overflow-x:auto}
.table{width:100%;border-collapse:collapse}
.table th{text-align:left;padding:11px 14px;font-size:.68rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--bd);background:var(--bg);white-space:nowrap}
.table td{padding:13px 14px;font-size:.82rem;border-bottom:1px solid var(--bd);vertical-align:middle}
.table tbody tr{transition:var(--tr);cursor:pointer}.table tbody tr:hover{background:rgba(190,24,93,.02)}

.order-id-cell{font-weight:800;color:var(--pk1);font-size:.82rem;white-space:nowrap}
.order-customer-cell .oc-name{font-weight:700;color:var(--t1);font-size:.84rem}
.order-customer-cell .oc-phone{font-size:.7rem;color:var(--t4);margin-top:1px}
.order-total-cell{font-weight:800;color:var(--t1);white-space:nowrap}
.order-date-cell{color:var(--t4);font-size:.78rem;white-space:nowrap}

/* ===== ACTION BUTTONS ===== */
.action-btns{display:flex;gap:5px}
.act-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--card);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.72rem;transition:var(--tr)}
.act-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.act-btn.act-view{color:#3b82f6}.act-btn.act-view:hover{background:rgba(59,130,246,.08)}
.act-btn.act-delete{color:#ef4444}.act-btn.act-delete:hover{background:rgba(239,68,68,.08)}

/* ===== MODAL ===== */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,4,16,.45);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal-box{background:var(--card);border-radius:var(--radius-lg);max-width:750px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 24px 60px rgba(26,4,16,.3);animation:modalIn .35s cubic-bezier(.16,1,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-head{padding:1.3rem 1.6rem;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
.modal-head h2{font-size:1.1rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px}
.modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t4);font-size:.8rem;transition:var(--tr)}
.modal-close:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.15);color:#ef4444}
.modal-body{padding:1.6rem;overflow-y:auto;max-height:calc(90vh - 70px)}

/* ===== TOAST ===== */
.toast{position:fixed;top:20px;right:20px;background:var(--card);padding:.9rem 1.4rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.12);z-index:20000;display:flex;align-items:center;gap:10px;transform:translateX(140%);transition:.4s cubic-bezier(.68,-.55,.27,1.55);border:1px solid var(--bd);font-size:.85rem;font-weight:600}
.toast.show{transform:translateX(0)}
.toast.success{border-left:4px solid #10b981}.toast.success .toast-icon{color:#10b981}
.toast.error{border-left:4px solid #ef4444}.toast.error .toast-icon{color:#ef4444}
.toast-icon{font-size:1.15rem}

.empty{text-align:center;padding:3rem 1rem;color:var(--t4)}.empty i{font-size:2.5rem;margin-bottom:.75rem;opacity:.25;display:block}

/* ===== RESPONSIVE ===== */
@media(max-width:768px){
    .mobile-toggle{display:flex}
    .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
    .sidebar-overlay.open{display:block}
    .dashboard-content{margin-left:0;padding:1.2rem;padding-top:64px}
    .page-header{flex-direction:column}
    .card-header{flex-direction:column}.search-box{width:100%}
    .mini-stats{gap:.5rem}.mini-stat{padding:8px 12px;font-size:.72rem}.mini-stat b{font-size:.85rem}
    .filter-bar{gap:4px}.filter-tab{padding:6px 12px;font-size:.7rem}
}
    </style>
</head>
<body>

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="dashboard-layout">
    <!-- ===== SIDEBAR — PERSIS USERS.PHP ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">⚙️</div>
            <span>Admin <em>Panel</em></span>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <a href="dashboard.php" class="sidebar-link">
                    <span class="link-icon"><i class="fa-solid fa-chart-pie"></i></span> Dashboard
                </a>
                <a href="orders.php" class="sidebar-link active">
                    <span class="link-icon"><i class="fa-solid fa-box"></i></span> Pesanan
                </a>
                <a href="layanan.php" class="sidebar-link">
                    <span class="link-icon"><i class="fa-solid fa-shirt"></i></span> Layanan
                </a>
                <a href="users.php" class="sidebar-link">
                    <span class="link-icon"><i class="fa-solid fa-users"></i></span> Users
                </a>
                <a href="payments.php" class="sidebar-link">
                    <span class="link-icon"><i class="fa-solid fa-credit-card"></i></span> Pembayaran
                </a>
                <a href="reports.php" class="sidebar-link">
                    <span class="link-icon"><i class="fa-solid fa-chart-line"></i></span> Laporan
                </a>
            </div>
            <div class="sidebar-section">
                <a href="<?= APP_URL ?>/index.php" class="sidebar-link">
                    <span class="link-icon"><i class="fa-solid fa-globe"></i></span> Ke Website
                </a>
                <a href="<?= APP_URL ?>/user/logout.php" class="sidebar-link danger-link">
                    <span class="link-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Keluar
                </a>
            </div>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <?php $sessName = $_SESSION['nama'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin'; ?>
                <div class="user-avatar" style="background:linear-gradient(135deg,var(--pk3),var(--pk1))"><?= strtoupper(substr($sessName,0,1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($sessName) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard-content">
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
            <?= $flash['message'] ?>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <div class="page-title"><i class="fa-solid fa-box"></i> Kelola Pesanan</div>
                <div class="page-sub"><?= number_format($totalAll) ?> pesanan tercatat</div>
            </div>
        </div>

        <div class="mini-stats">
            <div class="mini-stat"><i class="fa-solid fa-box" style="color:var(--pk3)"></i><b><?= number_format($totalAll) ?></b><span>Total</span></div>
            <div class="mini-stat"><i class="fa-solid fa-clock" style="color:#f59e0b"></i><b><?= number_format($counts['pending'] ?? 0) ?></b><span>Pending</span></div>
            <div class="mini-stat"><i class="fa-solid fa-spinner" style="color:#3b82f6"></i><b><?= number_format(($counts['diproses']??0)+($counts['dicuci']??0)+($counts['disetrika']??0)) ?></b><span>Diproses</span></div>
            <div class="mini-stat"><i class="fa-solid fa-circle-check" style="color:#22c55e"></i><b><?= number_format($counts['selesai'] ?? 0) ?></b><span>Selesai</span></div>
            <div class="mini-stat"><i class="fa-solid fa-wallet" style="color:var(--pk1)"></i><b><?= formatRupiah($totalRevenue) ?></b><span>Revenue Selesai</span></div>
        </div>

        <div class="filter-bar">
            <a href="<?= $filterLink() ?>" class="filter-tab <?= !$filterStatus ? 'active' : '' ?>">
                <i class="fa-solid fa-list"></i> Semua <span class="ft-count"><?= $totalAll ?></span>
            </a>
            <a href="<?= $filterLink('pending') ?>" class="filter-tab <?= $filterStatus==='pending' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock"></i> Pending <span class="ft-count"><?= $counts['pending']??0 ?></span>
            </a>
            <a href="<?= $filterLink('dikonfirmasi') ?>" class="filter-tab <?= $filterStatus==='dikonfirmasi' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check"></i> Dikonfirmasi <span class="ft-count"><?= $counts['dikonfirmasi']??0 ?></span>
            </a>
            <a href="<?= $filterLink('diproses') ?>" class="filter-tab <?= $filterStatus==='diproses' ? 'active' : '' ?>">
                <i class="fa-solid fa-spinner"></i> Diproses <span class="ft-count"><?= $counts['diproses']??0 ?></span>
            </a>
            <a href="<?= $filterLink('dicuci') ?>" class="filter-tab <?= $filterStatus==='dicuci' ? 'active' : '' ?>">
                <i class="fa-solid fa-water"></i> Dicuci <span class="ft-count"><?= $counts['dicuci']??0 ?></span>
            </a>
            <a href="<?= $filterLink('disetrika') ?>" class="filter-tab <?= $filterStatus==='disetrika' ? 'active' : '' ?>">
                <i class="fa-solid fa-temperature-high"></i> Disetrika <span class="ft-count"><?= $counts['disetrika']??0 ?></span>
            </a>
            <a href="<?= $filterLink('siap_ambil') ?>" class="filter-tab <?= $filterStatus==='siap_ambil' ? 'active' : '' ?>">
                <i class="fa-solid fa-box-open"></i> Siap Ambil <span class="ft-count"><?= $counts['siap_ambil']??0 ?></span>
            </a>
            <a href="<?= $filterLink('selesai') ?>" class="filter-tab <?= $filterStatus==='selesai' ? 'active' : '' ?>">
                <i class="fa-solid fa-check-double"></i> Selesai <span class="ft-count"><?= $counts['selesai']??0 ?></span>
            </a>
            <a href="<?= $filterLink('dibatalkan') ?>" class="filter-tab <?= $filterStatus==='dibatalkan' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-xmark"></i> Batal <span class="ft-count"><?= $counts['dibatalkan']??0 ?></span>
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-table-list"></i> Daftar Pesanan</div>
                <form method="GET" class="search-box" style="display:flex">
                    <?php if ($filterStatus): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Cari nama, no HP, ID order...">
                    <button type="submit" style="padding:8px 14px;border:1px solid var(--bd);border-radius:10px;background:var(--bg2);color:var(--t3);cursor:pointer;margin-left:8px;transition:var(--tr)" onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background='var(--bg2)'"><i class="fa-solid fa-search"></i></button>
                </form>
            </div>
            <div class="table-wrapper">
                <?php if (!empty($allOrders)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID Order</th>
                            <th>Pelanggan</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Tanggal</th>
                            <th style="width:90px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allOrders as $o):
                        $oid = (int)$o[$colId];
                        $displayId = ($colKode && !empty($o[$colKode])) ? htmlspecialchars($o[$colKode]) : '#' . str_pad($oid, 4, '0', STR_PAD_LEFT);
                        $status = $o[$colStatus] ?? '-';
                        $date = !empty($o[$colDate]) ? date('d M Y, H:i', strtotime($o[$colDate])) : '-';
                    ?>
                        <tr onclick="openDetail(<?= $oid ?>)">
                            <td><span class="order-id-cell"><?= $displayId ?></span></td>
                            <td>
                                <div class="order-customer-cell">
                                    <div class="oc-name"><?= htmlspecialchars($o['user_name'] ?? 'Guest') ?></div>
                                    <div class="oc-phone"><?= htmlspecialchars($o['user_phone'] ?? '-') ?></div>
                                </div>
                            </td>
                            <td><?= statusBadge($status) ?></td>
                            <td><span class="order-total-cell"><?= formatRupiah($o[$colTotal]) ?></span></td>
                            <td><span class="order-date-cell"><?= $date ?></span></td>
                            <td>
                                <div class="action-btns" onclick="event.stopPropagation()">
                                    <button class="act-btn act-view" title="Detail & Update Status" onclick="openDetail(<?= $oid ?>)"><i class="fa-solid fa-eye"></i></button>
                                    <a href="?delete=<?= $oid ?>" onclick="return confirm('Yakin ingin menghapus pesanan ini?')" class="act-btn act-delete" title="Hapus"><i class="fa-solid fa-trash-can"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty"><i class="fa-solid fa-box-open"></i><p>Tidak ada pesanan ditemukan</p></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box" id="modalBox">
        <div style="text-align:center;padding:3rem">
            <i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--pk3)"></i>
            <p style="margin-top:1rem;color:var(--t4)">Memuat detail pesanan...</p>
        </div>
    </div>
</div>

<!-- ===== TOAST ===== -->
<div class="toast" id="toast">
    <div class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="toast-msg">Berhasil disimpan</div>
</div>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
window.addEventListener('resize',function(){
    if(window.innerWidth>768){
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
    }
});

function showToast(msg, type){
    type = type || 'success';
    var t = document.getElementById('toast');
    var icon = t.querySelector('.toast-icon');
    var txt = t.querySelector('.toast-msg');
    t.className = 'toast ' + type;
    txt.innerHTML = msg;
    icon.innerHTML = type === 'success'
        ? '<i class="fa-solid fa-circle-check"></i>'
        : '<i class="fa-solid fa-circle-xmark"></i>';
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 3000);
}

function openDetail(id){
    var overlay = document.getElementById('detailModal');
    var box = document.getElementById('modalBox');
    overlay.classList.add('open');
    box.innerHTML = '<div style="text-align:center;padding:3rem"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--pk3)"></i><p style="margin-top:1rem;color:var(--t4)">Memuat detail pesanan...</p></div>';
    fetch('orders.php?detail='+id+'&ajax=1')
        .then(function(r){ return r.text(); })
        .then(function(html){
            box.innerHTML = html;
        })
        .catch(function(e){
            box.innerHTML = '<div style="text-align:center;padding:3rem;color:#ef4444"><i class="fa-solid fa-circle-exclamation fa-2x"></i><p style="margin-top:1rem">Gagal memuat data pesanan.</p></div>';
        });
}

function closeModal(){
    document.getElementById('detailModal').classList.remove('open');
}

document.getElementById('detailModal').addEventListener('click', function(e){
    if(e.target === this) closeModal();
});

function submitStatusUpdate(formElement){
    var btn = formElement.querySelector('.d-status-btn');
    var originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

    var formData = new FormData(formElement);

    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if(data.status === 'success'){
            showToast(data.message, 'success');
            closeModal();
            setTimeout(function(){ location.reload(); }, 800);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(function(e){
        showToast('Terjadi kesalahan koneksi.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });

    return false;
}

// Keyboard shortcut: Escape to close modal
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeModal();
});
</script>
</body>
</html>