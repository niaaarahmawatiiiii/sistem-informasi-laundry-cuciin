<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Method tidak diizinkan']);
    exit;
}

 $payId = (int)($_POST['id_pembayaran'] ?? 0);
 $action = $_POST['action'] ?? ''; // 'approve' atau 'reject'

if (!$payId || !in_array($action, ['approve','reject'])) {
    echo json_encode(['status'=>'error','message'=>'Parameter tidak valid']);
    exit;
}

 $pay = $db->query("SELECT * FROM pembayaran WHERE id_pembayaran=$payId")->fetch_assoc();
if (!$pay || $pay['status_pembayaran'] !== 'cod_bukti') {
    echo json_encode(['status'=>'error','message'=>'Pembayaran tidak dalam status menunggu verifikasi']);
    exit;
}

 $orderId = (int)$pay['id_order'];
 $adminId = (int)($_SESSION['user_id'] ?? 0);

 $trkCols = $db->query("SHOW COLUMNS FROM tracking")->fetch_all(MYSQLI_ASSOC);
 $trkColNames = array_column($trkCols, 'Field');
 $trkKet = in_array('keterangan', $trkColNames) ? 'keterangan' : 'status';
 $trkBy = in_array('updated_by', $trkColNames) ? 'updated_by' : 'updated_by';

 $db->begin_transaction();
try {
    if ($action === 'approve') {
        $db->query("UPDATE pembayaran SET status_pembayaran='paid' WHERE id_pembayaran=$payId");
        $ket = 'Admin telah memverifikasi bukti pembayaran COD. Pembayaran dinyatakan VALID.';
        $db->query("INSERT INTO tracking (id_order, status, $trkKet, $trkBy) VALUES ($orderId, 'Pembayaran COD diverifikasi', '$ket', $adminId)");
        echo json_encode(['status'=>'success','message'=>'Bukti pembayaran COD <b>diverifikasi</b>. Status pembayaran diubah menjadi <b>PAID</b>.']);
    } else {
        $db->query("UPDATE pembayaran SET status_pembayaran='cod_menunggu', bukti_pembayaran=NULL WHERE id_pembayaran=$payId");
        $ket = 'Admin menolak bukti pembayaran COD. Kurir diminta upload ulang bukti.';
        $db->query("INSERT INTO tracking (id_order, status, $trkKet, $trkBy) VALUES ($orderId, 'Bukti COD ditolak', '$ket', $adminId)");
        echo json_encode(['status'=>'success','message'=>'Bukti pembayaran COD <b>ditolak</b>. Kurir perlu upload ulang bukti.']);
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['status'=>'error','message'=>'Database error: ' . $e->getMessage()]);
}