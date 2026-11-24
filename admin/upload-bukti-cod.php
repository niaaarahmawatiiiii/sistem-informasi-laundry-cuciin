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
if (!$payId) {
    echo json_encode(['status'=>'error','message'=>'ID pembayaran tidak valid']);
    exit;
}

// Cek apakah pembayaran ini memang COD & menunggu
 $pay = $db->query("SELECT * FROM pembayaran WHERE id_pembayaran=$payId")->fetch_assoc();
if (!$pay || $pay['metode_pembayaran'] !== 'cod') {
    echo json_encode(['status'=>'error','message'=>'Bukan pesanan COD']);
    exit;
}
if (!in_array($pay['status_pembayaran'], ['cod_menunggu','pending'])) {
    echo json_encode(['status'=>'error','message'=>'Status pembayaran tidak memungkinkan upload bukti']);
    exit;
}

// Handle upload
if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status'=>'error','message'=>'File bukti wajib diupload']);
    exit;
}

 $allowed = ['image/jpeg','image/png','image/webp','image/jpg'];
 $fType = $_FILES['bukti']['type'];
if (!in_array($fType, $allowed)) {
    echo json_encode(['status'=>'error','message'=>'Format file harus JPG/PNG/WebP']);
    exit;
}
if ($_FILES['bukti']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['status'=>'error','message'=>'Ukuran file maksimal 5MB']);
    exit;
}

 $ext = pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION);
 $fileName = 'cod_' . $payId . '_' . time() . '.' . $ext;
 $uploadDir = __DIR__ . '/../uploads/bukti_cod/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
 $destPath = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['bukti']['tmp_name'], $destPath)) {
    echo json_encode(['status'=>'error','message'=>'Gagal mengupload file']);
    exit;
}

// Update database: simpan path & ubah status jadi cod_bukti
 $buktiUrl = APP_URL . '/uploads/bukti_cod/' . $fileName;
try {
    $db->query("UPDATE pembayaran SET status_pembayaran='cod_bukti', bukti_pembayaran='$buktiUrl' WHERE id_pembayaran=$payId");
    
    // Tambah tracking
    $orderId = (int)$pay['id_order'];
    $trkCols = $db->query("SHOW COLUMNS FROM tracking")->fetch_all(MYSQLI_ASSOC);
    $trkColNames = array_column($trkCols, 'Field');
    $trkKet = in_array('keterangan', $trkColNames) ? 'keterangan' : 'status';
    $trkBy = in_array('updated_by', $trkColNames) ? 'updated_by' : 'updated_by';
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $ket = 'Kurir telah mengupload bukti pembayaran COD. Menunggu verifikasi admin.';
    $db->query("INSERT INTO tracking (id_order, status, $trkKet, $trkBy) VALUES ($orderId, 'Bukti COD diterima', '$ket', $adminId)");
    
    echo json_encode(['status'=>'success','message'=>'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.','bukti_url'=>$buktiUrl]);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Database error: ' . $e->getMessage()]);
}