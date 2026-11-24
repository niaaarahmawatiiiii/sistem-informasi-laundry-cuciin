<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/login.php');
    exit;
}

 $userId = $_SESSION['user_id'];
 $userNama = $_SESSION['user_nama'] ?? 'User';
 $flash = getFlash();

// Get cart items
 $cartItems = [];
 $total = 0;
try {
    $cartItems = $db->query("
        SELECT c.id_cart, c.qty, l.id_layanan, l.nama_layanan, l.jenis, l.harga, l.satuan,
               (c.qty * l.harga) as subtotal
        FROM cart c JOIN layanan l ON c.id_layanan = l.id_layanan
        WHERE c.id_user = $userId
    ")->fetch_all(MYSQLI_ASSOC);
    $total = array_sum(array_column($cartItems, 'subtotal'));
} catch (Exception $e) {}

if (empty($cartItems)) {
    setFlash('warning', 'Keranjangmu kosong. Silakan pilih layanan terlebih dahulu.');
    header('Location: ' . APP_URL . '/user/layanan.php');
    exit;
}

// Get user data
 $user = [];
try {
    $user = $db->query("SELECT * FROM users WHERE id_user=$userId")->fetch_assoc();
} catch (Exception $e) {
    $user = ['nama' => '', 'no_hp' => '', 'alamat' => ''];
}

// Process checkout
 $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');
    $metode   = $_POST['metode'] ?? '';
    $metpay   = $_POST['metode_bayar'] ?? '';
    $tgl_jmpt = $_POST['tanggal_jemput'] ?? '';
    $jam_jmpt = $_POST['jam_jemput'] ?? '';
    $catatan  = trim($_POST['catatan'] ?? '');

    if (!$nama)   $errors[] = 'Nama penerima wajib diisi';
    if (!$no_hp)  $errors[] = 'No. HP wajib diisi';
    if (!$metode) $errors[] = 'Metode pengiriman wajib dipilih';
    if ($metode === 'antar-jemput' && !$alamat) $errors[] = 'Alamat wajib diisi untuk antar-jemput';
    if ($metode === 'antar-jemput' && !$tgl_jmpt) $errors[] = 'Tanggal jemput wajib diisi';
    if (!$metpay) $errors[] = 'Metode pembayaran wajib dipilih';

    if (empty($errors)) {
        $kode = generateOrderCode();
        while ($db->query("SELECT id_order FROM orders WHERE kode_order='$kode'")->num_rows > 0) {
            $kode = generateOrderCode();
        }

        $db->begin_transaction();
        try {
            // Cek nama kolom order
            $cols = $db->query("SHOW COLUMNS FROM orders")->fetch_all(MYSQLI_ASSOC);
            $colNames = array_column($cols, 'Field');
            $colCode = in_array('kode_order', $colNames) ? 'kode_order' : 'order_code';
            $colDate = in_array('tanggal_order', $colNames) ? 'tanggal_order' : 'created_at';

            $statusOrder = 'pending';
            $tglJemputVal = !empty($tgl_jmpt) ? $tgl_jmpt : null;
            $jamJemputVal = !empty($jam_jmpt) ? $jam_jmpt : null;

            $stmt = $db->prepare("INSERT INTO orders (id_user, $colCode, total_harga, status, metode, alamat, catatan, tanggal_jemput, jam_jemput) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isdssssss', $userId, $kode, $total, $statusOrder, $metode, $alamat, $catatan, $tglJemputVal, $jamJemputVal);
            $stmt->execute();
            $orderId = $db->insert_id;

            // Insert order detail
            foreach ($cartItems as $item) {
                $vIdLayanan = (int)$item['id_layanan'];
                $vQty = (float)$item['qty'];
                $vHarga = (float)$item['harga'];
                $vSubtotal = (float)$item['subtotal'];
                $st = $db->prepare("INSERT INTO order_detail (id_order, id_layanan, qty, harga_satuan, subtotal) VALUES (?,?,?,?,?)");
                $st->bind_param('iiddd', $orderId, $vIdLayanan, $vQty, $vHarga, $vSubtotal);
                $st->execute();
            }

            // ===== PEMBAYARAN — LOGIKA PER METODE =====
            $payCols = $db->query("SHOW COLUMNS FROM pembayaran")->fetch_all(MYSQLI_ASSOC);
            $payColNames = array_column($payCols, 'Field');

            if (in_array('jumlah', $payColNames)) {
                // Tentukan status pembayaran berdasarkan metode
                if ($metpay === 'qris') {
                    // QRIS: auto-paid
                    $payStatus = 'paid';
                } elseif ($metpay === 'cod') {
                    // COD: status khusus "cod_menunggu" — menunggu kurir kumpulkan & upload bukti
                    $payStatus = 'cod_menunggu';
                } else {
                    // Transfer / E-Wallet: pending biasa
                    $payStatus = 'pending';
                }

                $st = $db->prepare("INSERT INTO pembayaran (id_order, metode_pembayaran, status_pembayaran, jumlah) VALUES (?,?,?,?)");
                $st->bind_param('issd', $orderId, $metpay, $payStatus, $total);
                $st->execute();
            }

            // ===== TRACKING — PESAN BERBEDA PER METODE =====
            $trkCols = $db->query("SHOW COLUMNS FROM tracking")->fetch_all(MYSQLI_ASSOC);
            $trkColNames = array_column($trkCols, 'Field');
            $trkKet = in_array('keterangan', $trkColNames) ? 'keterangan' : (in_array('catatan', $trkColNames) ? 'catatan' : 'status');
            $trkBy = in_array('updated_by', $trkColNames) ? 'updated_by' : (in_array('id_user', $trkColNames) ? 'id_user' : 'updated_by');

            $trkStatusVal = 'Pesanan diterima';

            // Pesan tracking berbeda per metode bayar
            if ($metpay === 'cod') {
                $trkKetVal = 'Pesanan berhasil dibuat dengan metode COD. Silakan tunggu kurir datang — pembayaran akan dilakukan langsung ke kurir saat pengantaran.';
            } elseif ($metpay === 'qris') {
                $trkKetVal = 'Pesanan berhasil dibuat. Pembayaran QRIS telah terverifikasi secara otomatis.';
            } else {
                $trkKetVal = 'Pesanan berhasil dibuat dan menunggu konfirmasi admin.';
            }

            $st = $db->prepare("INSERT INTO tracking (id_order, status, $trkKet, $trkBy) VALUES (?,?,?,?)");
            $st->bind_param('issi', $orderId, $trkStatusVal, $trkKetVal, $userId);
            $st->execute();

            $db->query("DELETE FROM cart WHERE id_user=$userId");
            $db->commit();

            // Flash message berbeda per metode
            if ($metpay === 'cod') {
                setFlash('success', 'Pesanan #' . $kode . ' berhasil dibuat! Kamu bisa bayar langsung ke kurir saat pesanan diantar.');
            } elseif ($metpay === 'qris') {
                setFlash('success', 'Pesanan #' . $kode . ' berhasil dibuat! Pembayaran QRIS terverifikasi otomatis.');
            } else {
                setFlash('success', 'Pesanan #' . $kode . ' berhasil dibuat!');
            }
            header('Location: ' . APP_URL . '/user/order-detail.php?id=' . $orderId);
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

 $pageTitle = 'Checkout — CUCI.IN';
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
            --sidebar-w:260px;--r:14px;--rl:20px;--rx:28px;
            --sh:0 4px 20px rgba(236,72,153,.08);--shl:0 12px 40px rgba(236,72,153,.12);
            --shpk:0 8px 30px rgba(236,72,153,.25);
            --tr:all .3s cubic-bezier(.4,0,.2,1);
            --green:#22c55e;--green-bg:#dcfce7;--green-border:#bbf7d0;
            --blue:#3b82f6;--blue-bg:#dbeafe;
            --orange:#f59e0b;--orange-bg:#fffbeb;--orange-border:#fde68a;
            --red:#ef4444;--red-bg:#fef2f2;--red-border:#fecaca;
        }
        body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--g50);color:var(--g800);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        a{text-decoration:none;color:inherit;transition:var(--tr)}button{cursor:pointer;border:none;font-family:inherit}

        /* SIDEBAR */
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

        /* MAIN */
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

        /* CONTENT */
        .content{flex:1;padding:2rem}
        .content-in{max-width:1200px;margin:0 auto}

        /* BREADCRUMB */
        .breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:1.5rem;font-size:.85rem;color:var(--g400);flex-wrap:wrap}
        .breadcrumb a{color:var(--g500);font-weight:500;display:flex;align-items:center;gap:4px}
        .breadcrumb a:hover{color:var(--pk500)}
        .breadcrumb .bc-sep{color:var(--g300)}
        .breadcrumb .bc-current{color:var(--g800);font-weight:600}

        /* ERRORS */
        .err-box{background:var(--red-bg);border:1px solid var(--red-border);border-radius:var(--rl);padding:1rem 1.2rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:10px;animation:errShake .5s ease}
        @keyframes errShake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
        .err-ic{width:32px;height:32px;min-width:32px;border-radius:10px;background:var(--red-border);display:flex;align-items:center;justify-content:center;color:#ef4444;font-size:.9rem;flex-shrink:0}
        .err-list{list-style:none;font-size:.88rem;color:#dc2626;line-height:1.6}
        .err-list li{display:flex;align-items:center;gap:6px}
        .err-list li::before{content:"•";font-weight:700}

        /* FLASH */
        .flash-box{padding:1rem 1.2rem;border-radius:var(--rl);margin-bottom:1.5rem;display:flex;align-items:center;gap:10px;font-size:.9rem;font-weight:600;animation:fadeUp .5s ease}
        .flash-ok{background:var(--green-bg);color:#16a34a;border:1px solid var(--green-border)}
        .flash-warn{background:#fef3c7;color:#d97706;border:1px solid #fde68a}
        @keyframes fadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}

        /* CHECKOUT GRID */
        .checkout-grid{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start}

        /* SECTION CARD */
        .sec-card{background:var(--w);border:1px solid var(--g100);border-radius:var(--rl);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.02);margin-bottom:1.5rem}
        .sec-hd{padding:1rem 1.3rem;border-bottom:1px solid var(--g100);font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .sec-hd i{color:var(--pk400);font-size:.9rem}
        .sec-bd{padding:1.3rem}

        /* FORM */
        .fg{margin-bottom:1rem}
        .fl{display:block;font-size:.82rem;font-weight:600;color:var(--g700);margin-bottom:5px}
        .fi{width:100%;padding:11px 14px;border:1.5px solid var(--g200);border-radius:12px;font-size:.9rem;font-family:inherit;color:var(--g800);background:var(--w);outline:none;transition:var(--tr)}
        .fi::placeholder{color:var(--g300)}
        .fi:focus{border-color:var(--pk400);box-shadow:0 0 0 4px rgba(236,72,153,.08)}
        .fi.err{border-color:#fca5a5;box-shadow:0 0 0 3px rgba(239,68,68,.06)}
        textarea.fi{resize:vertical;min-height:80px}
        select.fi{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M2 4l4 4 4-4' stroke='%239ca3af' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat;background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

        /* METODE PILIH */
        .method-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem}
        .method-opt{cursor:pointer;position:relative}
        .method-opt input{position:absolute;opacity:0;pointer-events:none}
        .method-card{border:2px solid var(--g200);border-radius:var(--rl);padding:1.2rem;text-align:center;transition:var(--tr)}
        .method-opt input:checked ~ .method-card{border-color:var(--pk500);background:var(--pk50)}
        .method-opt input:checked ~ .method-card .mc-icon{background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;box-shadow:0 4px 15px rgba(236,72,153,.25)}
        .method-card:hover{border-color:rgba(236,72,153,.2);transform:translateY(-2px)}
        .mc-icon{width:52px;height:52px;margin:0 auto .8rem;border-radius:14px;background:var(--g100);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:var(--g500);transition:var(--tr)}
        .mc-name{font-size:.95rem;font-weight:700;color:var(--g800);margin-bottom:.2rem}
        .mc-desc{font-size:.78rem;color:var(--g500)}

        /* PAYMENT */
        .pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
        .pay-opt{cursor:pointer;position:relative}
        .pay-opt input{position:absolute;opacity:0;pointer-events:none}
        .pay-card{border:2px solid var(--g200);border-radius:14px;padding:1rem;text-align:center;transition:var(--tr)}
        .pay-opt input:checked ~ .pay-card{border-color:var(--pk500);background:var(--pk50)}
        .pay-card:hover{border-color:rgba(236,72,153,.2)}
        .pc-icon{font-size:1.5rem;margin-bottom:.4rem}
        .pc-name{font-size:.85rem;font-weight:700;color:var(--g800)}
        .pc-desc{font-size:.72rem;color:var(--g500)}

        /* QRIS SECTION */
        .qris-section{display:none;margin-top:1rem;text-align:center;padding:1.5rem;background:var(--pk50);border:2px dashed var(--pk300);border-radius:var(--rl)}
        .qris-section .qris-title{font-size:.9rem;font-weight:700;color:var(--g800);margin-bottom:.8rem}
        .qris-section .qris-img{width:220px;height:220px;border-radius:12px;border:4px solid var(--w);box-shadow:var(--sh);object-fit:contain}
        .qris-section .qris-note{font-size:.78rem;color:var(--g500);margin-top:.8rem}

        /* ===== COD FLOW SECTION ===== */
        .cod-section{display:none;margin-top:1rem;overflow:hidden}
        .cod-flow-box{background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 50%,#fff7ed 100%);border:2px solid var(--orange-border);border-radius:var(--rl);padding:1.5rem;position:relative}
        .cod-flow-box::before{content:"";position:absolute;top:0;left:0;width:5px;height:100%;background:linear-gradient(180deg,var(--orange),#ea580c);border-radius:0 4px 4px 0}
        .cod-flow-title{font-size:.95rem;font-weight:800;color:#92400e;margin-bottom:1rem;display:flex;align-items:center;gap:8px}
        .cod-flow-title i{color:var(--orange);font-size:1.1rem}
        .cod-steps{display:flex;flex-direction:column;gap:0;position:relative;padding-left:28px}
        .cod-steps::before{content:"";position:absolute;left:11px;top:24px;bottom:24px;width:2px;background:linear-gradient(180deg,var(--orange),#fdba74);border-radius:2px}
        .cod-step{display:flex;align-items:flex-start;gap:14px;padding:.6rem 0;position:relative}
        .cod-step-dot{width:24px;height:24px;min-width:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;color:#fff;z-index:2;margin-left:-28px}
        .cod-step-dot.cs-1{background:var(--orange)}
        .cod-step-dot.cs-2{background:#ea580c}
        .cod-step-dot.cs-3{background:var(--green)}
        .cod-step-dot.cs-4{background:#3b82f6}
        .cod-step-dot.cs-5{background:var(--pk500)}
        .cod-step-text{padding-top:2px}
        .cod-step-text strong{font-size:.82rem;color:#78350f;display:block;margin-bottom:1px}
        .cod-step-text span{font-size:.75rem;color:#a16207;line-height:1.4}
        .cod-warning{margin-top:1rem;padding:.8rem 1rem;background:rgba(234,88,12,.08);border:1px solid rgba(234,88,12,.15);border-radius:10px;font-size:.78rem;color:#92400e;display:flex;align-items:center;gap:8px;line-height:1.5}
        .cod-warning i{color:var(--orange);font-size:.9rem;flex-shrink:0;margin-top:2px}

        /* SUMMARY */
        .summary{position:sticky;top:80px;display:flex;flex-direction:column;gap:1rem}
        .sum-card{background:var(--w);border:1px solid var(--g100);border-radius:var(--rl);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.02)}
        .sum-hd{padding:1rem 1.3rem;border-bottom:1px solid var(--g100);font-size:.95rem;font-weight:700;color:var(--g800);display:flex;align-items:center;gap:8px}
        .sum-hd i{color:var(--pk400)}
        .sum-body{padding:1.3rem}
        .sum-item{display:flex;gap:.75rem;padding:.65rem 0;border-bottom:1px solid var(--g50)}
        .sum-item:last-child{border-bottom:none}
        .sum-ic{width:36px;height:36px;min-width:36px;border-radius:10px;background:var(--pk50);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--pk500);flex-shrink:0}
        .sum-info{flex:1}
        .sum-name{font-size:.85rem;font-weight:600;color:var(--g800)}
        .sum-detail{font-size:.78rem;color:var(--g500)}
        .sum-price{font-weight:700;font-size:.88rem;color:var(--g800);white-space:nowrap}
        .sum-row{display:flex;justify-content:space-between;padding:.6rem 0;font-size:.88rem;color:var(--g500)}
        .sum-row.total{border-top:2px solid var(--pk200);margin-top:.3rem;padding-top:.8rem;font-size:1.15rem;font-weight:800;color:var(--g800)}
        .sum-row.total span:last-child{color:var(--pk600)}
        .sum-row.free span:last-child{color:var(--green);font-weight:700}
        .sum-footer{padding:1.3rem;border-top:1px solid var(--g100);display:flex;flex-direction:column;gap:.8rem}
        .btn-checkout{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border:none;border-radius:12px;font-size:1rem;font-weight:700;background:linear-gradient(135deg,var(--pk500),var(--pk700));color:#fff;box-shadow:0 4px 15px rgba(236,72,153,.3);transition:var(--tr)}
        .btn-checkout:hover{transform:translateY(-2px);box-shadow:var(--shpk)}
        .btn-checkout:active{transform:translateY(0)}
        .terms-note{font-size:.75rem;color:var(--g400);text-align:center;line-height:1.5}
        .terms-note a{color:var(--pk500);font-weight:600}

        /* COD BADGE DI SUMMARY */
        .cod-badge-summ{display:none;padding:.8rem 1rem;background:var(--orange-bg);border:1px solid var(--orange-border);border-radius:12px;margin-top:.5rem;font-size:.78rem;color:#92400e;line-height:1.5}
        .cod-badge-summ strong{color:#78350f}

        /* TOAST */
        .toast-ct{position:fixed;top:80px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px}
        .toast{padding:14px 24px;border-radius:12px;font-size:.88rem;font-weight:600;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,.12);animation:tI .5s ease,tO .4s ease 2.6s forwards;display:flex;align-items:center;gap:10px}
        .toast.ok{background:linear-gradient(135deg,#10b981,#059669)}
        .toast.err{background:linear-gradient(135deg,#ef4444,#dc2626)}
        @keyframes tI{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}
        @keyframes tO{to{opacity:0;transform:translateX(100px)}}

        /* ANIM */
        .fi-anim{opacity:0;transform:translateY(25px);transition:opacity .6s ease,transform .6s ease}
        .fi-anim.v{opacity:1;transform:translateY(0)}
        .d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}

        @media(max-width:1024px){.checkout-grid{grid-template-columns:1fr}.summary{position:static}}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
            .sidebar-toggle{display:flex}.sidebar-overlay.show{display:block}
            .main-ct{margin-left:0}.topbar{padding:0 1rem}
            .content{padding:1rem}
            .method-grid{grid-template-columns:1fr}
            .form-row{grid-template-columns:1fr}
            .pay-grid{grid-template-columns:1fr 1fr}
            .topbar-back span{display:none}
            .topbar-user .topbar-uname{display:none}
            .cod-step-text strong{font-size:.78rem}
            .cod-step-text span{font-size:.7rem}
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
        <a href="orders.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> Pesanan Saya</a>
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
            <a href="cart.php" class="topbar-back"><i class="fa-solid fa-arrow-left"></i> <span>Keranjang</span></a>
            <div class="topbar-title"><i class="fa-solid fa-lock"></i> Checkout</div>
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
        <div class="flash-box flash-<?= $flash['type'] === 'success' ? 'ok' : 'warn' ?>">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="err-box">
            <div class="err-ic"><i class="fa-solid fa-circle-exclamation"></i></div>
            <ul class="err-list">
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="breadcrumb fi-anim">
            <a href="<?= APP_URL ?>"><i class="fa-solid fa-house"></i> Beranda</a>
            <span class="bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
            <a href="layanan.php">Layanan</a>
            <span class="bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
            <span class="bc-current"><i class="fa-solid fa-lock" style="color:var(--pk500)"></i> Checkout</span>
        </div>

        <form method="POST" action="" novalidate>
        <div class="checkout-grid">

            <!-- LEFT: FORM -->
            <div>
                <!-- Data Penerima -->
                <div class="sec-card fi-anim d1">
                    <div class="sec-hd"><i class="fa-solid fa-user"></i> Data Penerima</div>
                    <div class="sec-bd">
                        <div class="form-row">
                            <div class="fg">
                                <label class="fl">Nama Lengkap *</label>
                                <input type="text" name="nama" class="fi" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required>
                            </div>
                            <div class="fg">
                                <label class="fl">No. HP *</label>
                                <input type="tel" name="no_hp" class="fi" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Metode Pengiriman -->
                <div class="sec-card fi-anim d2">
                    <div class="sec-hd"><i class="fa-solid fa-truck-fast"></i> Metode Pengiriman</div>
                    <div class="sec-bd">
                        <div class="method-grid">
                            <label class="method-opt" id="opt-aj">
                                <input type="radio" name="metode" value="antar-jemput" id="m-aj"
                                       <?= ($_POST['metode'] ?? 'antar-jemput') === 'antar-jemput' ? 'checked' : '' ?>
                                       onchange="toggleMetode(this.value)">
                                <div class="method-card" id="card-aj">
                                    <div class="mc-icon"><i class="fa-solid fa-truck"></i></div>
                                    <div class="mc-name">Antar-Jemput</div>
                                    <div class="mc-desc">Kurir kami jemput & antar ke lokasi</div>
                                </div>
                            </label>
                            <label class="method-opt" id="opt-ds">
                                <input type="radio" name="metode" value="datang-sendiri" id="m-ds"
                                       <?= ($_POST['metode'] ?? '') === 'datang-sendiri' ? 'checked' : '' ?>
                                       onchange="toggleMetode(this.value)">
                                <div class="method-card" id="card-ds">
                                    <div class="mc-icon"><i class="fa-solid fa-store"></i></div>
                                    <div class="mc-name">Datang Sendiri</div>
                                    <div class="mc-desc">Ambil sendiri ke outlet kami</div>
                                </div>
                            </label>
                        </div>

                        <!-- Alamat -->
                        <div id="section-alamat" style="display:<?= ($_POST['metode'] ?? 'antar-jemput') === 'datang-sendiri' ? 'none' : 'block' ?>;">
                            <div class="fg">
                                <label class="fl">Alamat Lengkap *</label>
                                <textarea name="alamat" class="fi" rows="3" placeholder="Jl. Nama Jalan No.XX, RT/RW, Kelurahan, Kecamatan, Kota"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="fg">
                                    <label class="fl">Tanggal Jemput *</label>
                                    <input type="date" name="tanggal_jemput" class="fi" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['tanggal_jemput'] ?? '') ?>">
                                </div>
                                <div class="fg">
                                    <label class="fl">Jam Jemput *</label>
                                    <select name="jam_jemput" class="fi" id="jamSelect" onchange="updateMinDate()">
                                        <option value="">Pilih jam</option>
                                        <?php for ($h = 8; $h <= 20; $h++): ?>
                                        <option value="<?= sprintf('%02d:00:00', $h) ?>"
                                            <?= ($_POST['jam_jemput'] ?? '') === sprintf('%02d:00:00', $h) ? 'selected' : '' ?>>
                                            <?= sprintf('%02d:00', $h) ?> WIB
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="section-outlet" style="display:<?= ($_POST['metode'] ?? '') === 'datang-sendiri' ? 'block' : 'none' ?>;">
                            <div style="padding:1rem;background:var(--blue-bg);border:1px solid var(--blue);border-radius:var(--r);font-size:.88rem;color:#1e40af;display:flex;align-items:flex-start;gap:10px">
                                <i class="fa-solid fa-location-dot" style="color:var(--blue);margin-top:3px"></i>
                                <div>
                                    <strong style="color:#1e40af">Outlet CUCI.IN</strong><br>
                                    <span style="color:#3b82f6">Jl. Sudirman No. 45, Jakarta Selatan. Buka Senin–Sabtu, 07.00–20.00 WIB.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Metode Pembayaran -->
                <div class="sec-card fi-anim d3">
                    <div class="sec-hd"><i class="fa-solid fa-credit-card"></i> Metode Pembayaran</div>
                    <div class="sec-bd">
                        <div class="pay-grid">
                            <?php
                            $payMethods = [
                                'transfer' => ['fa-building-columns', 'Transfer Bank', 'BCA, Mandiri, BNI, BRI'],
                                'ewallet'  => ['fa-wallet', 'E-Wallet', 'GoPay, OVO, DANA, ShopeePay'],
                                'cod'      => ['fa-money-bill-wave', 'COD / Tunai', 'Bayar saat kurir antar'],
                                'qris'     => ['fa-qrcode', 'QRIS', 'Scan QR & Bayar Instan'],
                            ];
                            foreach ($payMethods as $val => [$icon, $name, $sub]):
                            ?>
                            <label class="pay-opt">
                                <input type="radio" name="metode_bayar" value="<?= $val ?>"
                                       <?= ($_POST['metode_bayar'] ?? 'transfer') === $val ? 'checked' : '' ?>
                                       onchange="onPaymentChange(this.value)">
                                <div class="pay-card">
                                    <div class="pc-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                                    <div class="pc-name"><?= $name ?></div>
                                    <div class="pc-desc"><?= $sub ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- QRIS SECTION -->
                        <div class="qris-section" id="qrisSection">
                            <div class="qris-title"><i class="fa-solid fa-qrcode"></i> Scan QRIS untuk Pembayaran</div>
                            <img src="<?= APP_URL ?>/img/qris.png" alt="QRIS" class="qris-img">
                            <div class="qris-note">
                                <i class="fa-solid fa-circle-check" style="color:var(--green)"></i>
                                Pembayaran QRIS akan otomatis terverifikasi & diterima oleh sistem
                            </div>
                        </div>

                        <!-- ===== COD FLOW SECTION ===== -->
                        <div class="cod-section" id="codSection">
                            <div class="cod-flow-box">
                                <div class="cod-flow-title">
                                    <i class="fa-solid fa-route"></i> Alur Pembayaran COD (Bayar di Tempat)
                                </div>
                                <div class="cod-steps">
                                    <div class="cod-step">
                                        <div class="cod-step-dot cs-1">1</div>
                                        <div class="cod-step-text">
                                            <strong>Konfirmasi Pesanan</strong>
                                            <span>Pesanan kamu akan diproses dan disiapkan oleh tim kami</span>
                                        </div>
                                    </div>
                                    <div class="cod-step">
                                        <div class="cod-step-dot cs-2">2</div>
                                        <div class="cod-step-text">
                                            <strong>Kurir Datang ke Lokasi</strong>
                                            <span>Kurir akan mengantar pesanan sesuai jadwal yang ditentukan</span>
                                        </div>
                                    </div>
                                    <div class="cod-step">
                                        <div class="cod-step-dot cs-3">3</div>
                                        <div class="cod-step-text">
                                            <strong>Bayar Langsung ke Kurir</strong>
                                            <span>Siapkan uang pas sebesar <strong style="color:var(--pk600)"><?= formatRupiah($total) ?></strong> saat kurir tiba</span>
                                        </div>
                                    </div>
                                    <div class="cod-step">
                                        <div class="cod-step-dot cs-4">4</div>
                                        <div class="cod-step-text">
                                            <strong>Kurir Upload Bukti Pembayaran</strong>
                                            <span>Kurir akan mengirim foto bukti penerimaan uang ke sistem</span>
                                        </div>
                                    </div>
                                    <div class="cod-step">
                                        <div class="cod-step-dot cs-5">5</div>
                                        <div class="cod-step-text">
                                            <strong>Admin Verifikasi & Selesai</strong>
                                            <span>Admin mengecek bukti pembayaran, lalu status pesanan diupdate menjadi selesai</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="cod-warning">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <div>
                                        <strong>Pastikan uang sudah siap</strong> sebelum kurir tiba. Kurir tidak bisa menunggu terlalu lama. Jika ada kendala, hubungi CS kami segera.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Catatan -->
                <div class="sec-card fi-anim d4">
                    <div class="sec-hd"><i class="fa-solid fa-pen-to-square"></i> Catatan Tambahan</div>
                    <div class="sec-bd">
                        <textarea name="catatan" class="fi" rows="2" placeholder="Instruksi khusus, kondisi pakaian, noda yang perlu diperhatikan..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT: SUMMARY -->
            <div class="summary fi-anim d2">
                <div class="sum-card">
                    <div class="sum-hd"><i class="fa-solid fa-receipt"></i> Ringkasan Pesanan</div>
                    <div class="sum-body">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="sum-item">
                            <div class="sum-ic"><i class="fa-solid fa-shirt"></i></div>
                            <div class="sum-info">
                                <div class="sum-name"><?= htmlspecialchars($item['nama_layanan']) ?></div>
                                <div class="sum-detail"><?= $item['qty'] ?> <?= htmlspecialchars($item['satuan']) ?> × <?= formatRupiah($item['harga']) ?></div>
                            </div>
                            <div class="sum-price"><?= formatRupiah($item['subtotal']) ?></div>
                        </div>
                        <?php endforeach; ?>

                        <div class="sum-row">
                            <span>Subtotal</span>
                            <span><?= formatRupiah($total) ?></span>
                        </div>
                        <div class="sum-row free">
                            <span><i class="fa-solid fa-truck-fast" style="color:var(--green)"></i> Ongkos Kirim</span>
                            <span>Gratis 🎉</span>
                        </div>
                        <div class="sum-row total">
                            <span>Total Bayar</span>
                            <span><?= formatRupiah($total) ?></span>
                        </div>

                        <!-- COD INFO DI SUMMARY -->
                        <div class="cod-badge-summ" id="codBadgeSumm">
                            <i class="fa-solid fa-money-bill-wave" style="color:var(--orange);margin-right:4px"></i>
                            <strong>Metode COD:</strong> Bayar <strong><?= formatRupiah($total) ?></strong> langsung ke kurir saat pesanan diantar ke lokasimu.
                        </div>
                    </div>
                    <div class="sum-footer">
                        <button type="submit" class="btn-checkout" id="btnCheckout">
                            <i class="fa-solid fa-shield-halved"></i> Konfirmasi Pesanan
                        </button>
                        <p class="terms-note">
                            Dengan memesan, kamu menyetujui <a href="#">Syarat &amp; Ketentuan</a> CUCI.IN
                        </p>
                    </div>
                </div>

                <!-- Info -->
                <div class="sec-card fi-anim d4" style="border-color:rgba(236,72,153,.12);background:linear-gradient(135deg,rgba(236,72,153,.04),rgba(236,72,153,.01))">
                    <div class="sec-bd" style="display:flex;flex-direction:column;gap:.8rem">
                        <div style="display:flex;align-items:center;gap:10px;font-size:.88rem;color:var(--g600)">
                            <div style="width:36px;height:36px;border-radius:10px;background:var(--pk50);display:flex;align-items:center;justify-content:center;color:var(--pk500);flex-shrink:0">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <strong style="color:var(--g800)">Garansi Uang Kembali</strong>
                                <div style="font-size:.78rem;color:var(--g400)">Tidak puas? Uang dikembalikan 100%</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;font-size:.88rem;color:var(--g600)">
                            <div style="width:36px;height:36px;border-radius:10px;background:var(--green-bg);display:flex;align-items:center;justify-content:center;color:var(--green);flex-shrink:0">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div>
                                <strong style="color:var(--g800)">Pembayaran Aman</strong>
                                <div style="font-size:.78rem;color:var(--g400)">Data kamu terproteksi & terenkripsi</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;font-size:.88rem;color:var(--g600)">
                            <div style="width:36px;height:36px;border-radius:10px;background:var(--blue-bg);display:flex;align-items:center;justify-content:center;color:var(--blue);flex-shrink:0">
                                <i class="fa-solid fa-headset"></i>
                            </div>
                            <div>
                                <strong style="color:var(--g800)">Support 24/7</strong>
                                <div style="font-size:.78rem;color:var(--g400)">Tim kami siap bantu kapan saja</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </form>
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

<div class="toast-ct" id="toastCt"></div>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('show');
}

// Scroll animation observer
const hObs=new IntersectionObserver(e=>{e.forEach(en=>{if(en.isIntersecting)en.target.classList.add('v')})},{threshold:.05});
document.querySelectorAll('.fi-anim').forEach(el=>hObs.observe(el));

function toggleMetode(val){
    var aj=document.getElementById('card-aj');
    var ds=document.getElementById('card-ds');
    var secA=document.getElementById('section-alamat');
    var secO=document.getElementById('section-outlet');
    if(val==='antar-jemput'){
        aj.style.borderColor='var(--pk500)';aj.style.background='var(--pk50)';
        ds.style.borderColor='var(--g200)';ds.style.background='var(--w)';
        secA.style.display='block';secO.style.display='none';
    }else{
        ds.style.borderColor='var(--pk500)';ds.style.background='var(--pk50)';
        aj.style.borderColor='var(--g200)';aj.style.background='var(--w)';
        secA.style.display='none';secO.style.display='block';
    }
}

// ===== PAGIANTIAN PAYMENT METHOD — QRIS & COD =====
function onPaymentChange(val){
    var qrisSection=document.getElementById('qrisSection');
    var codSection=document.getElementById('codSection');
    var codBadge=document.getElementById('codBadgeSumm');
    var btnCheckout=document.getElementById('btnCheckout');

    // Reset semua
    qrisSection.style.display='none';
    codSection.style.display='none';
    codBadge.style.display='none';

    // Reset button ke default
    btnCheckout.innerHTML='<i class="fa-solid fa-shield-halved"></i> Konfirmasi Pesanan';
    btnCheckout.style.background='linear-gradient(135deg,var(--pk500),var(--pk700))';

    if(val==='qris'){
        qrisSection.style.display='block';
        btnCheckout.innerHTML='<i class="fa-solid fa-qrcode"></i> Konfirmasi & Bayar via QRIS';
    } else if(val==='cod'){
        codSection.style.display='block';
        codBadge.style.display='block';
        btnCheckout.innerHTML='<i class="fa-solid fa-money-bill-wave"></i> Konfirmasi Pesanan COD';
        btnCheckout.style.background='linear-gradient(135deg,#ea580c,#c2410c)';
    }

    // Highlight active card
    document.querySelectorAll('.pay-card').forEach(c=>{c.style.borderColor='var(--g200)';c.style.background='var(--w)'});
    var checked=document.querySelector('.pay-opt input:checked');
    if(checked){
        checked.closest('.pay-opt').querySelector('.pay-card').style.borderColor='var(--pk500)';
        checked.closest('.pay-opt').querySelector('.pay-card').style.background='var(--pk50)';
    }
}

// Payment card highlight on load
document.querySelectorAll('.pay-opt input').forEach(r=>{
    r.addEventListener('change',function(){
        document.querySelectorAll('.pay-card').forEach(c=>{c.style.borderColor='var(--g200)';c.style.background='var(--w)'});
        this.closest('.pay-opt').querySelector('.pay-card').style.borderColor='var(--pk500)';
        this.closest('.pay-opt').querySelector('.pay-card').style.background='var(--pk50)';
    });
});

// Init: trigger onPaymentChange for pre-selected value
(function(){
    var checked=document.querySelector('.pay-opt input:checked');
    if(checked) onPaymentChange(checked.value);
})();

// Update min date saat ganti jam
function updateMinDate(){
    var tgl=document.querySelector('input[name="tanggal_jemput"]');
    var jam=document.getElementById('jamSelect').value;
    if(tgl&&jam){
        var d=new Date();
        var jamParts=jam.split(':');
        var h=parseInt(jamParts[0]);
        if(h<20)d.setHours(h+1,0,0,0);
        tgl.min=d.toISOString().split('T')[0];
    }
}
updateMinDate();
</script>
</body>
</html>