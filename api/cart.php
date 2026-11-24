<?php
// CUCI.IN/api/cart.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

session_start();

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login kembali.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

 $rawInput = file_get_contents('php://input');
 $data = json_decode($rawInput, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Format request tidak valid']);
    exit;
}

 $userId = (int)$_SESSION['user_id'];
 $action = $data['action'];

// ==========================================
// LOGIKA: TAMBAH KE KERANJANG
// ==========================================
if ($action === 'add') {
    $idLayanan = (int)($data['id_layanan'] ?? 0);
    $qty = max(1, (float)($data['qty'] ?? 1));

    if ($idLayanan === 0) {
        echo json_encode(['success' => false, 'message' => 'ID Layanan tidak valid']);
        exit;
    }

    // Cek layanan aktif
    $layanan = $db->query("SELECT id_layanan, nama_layanan FROM layanan WHERE id_layanan = $idLayanan AND is_active = 1")->fetch_assoc();
    
    if (!$layanan) {
        echo json_encode(['success' => false, 'message' => 'Layanan tidak ditemukan atau tidak aktif']);
        exit;
    }

    // Cek apakah sudah ada di cart
    $cek = $db->query("SELECT id_cart, qty FROM cart WHERE id_user = $userId AND id_layanan = $idLayanan")->fetch_assoc();

    if ($cek) {
        // Jika sudah ada, tambahkan qty-nya
        $newQty = $cek['qty'] + $qty;
        $db->query("UPDATE cart SET qty = $newQty WHERE id_cart = " . (int)$cek['id_cart']);
    } else {
        // Jika belum ada, insert baru (SESUAI STRUKTUR DB ANDA)
        $db->query("INSERT INTO cart (id_user, id_layanan, qty) VALUES ($userId, $idLayanan, $qty)");
    }

    echo json_encode(['success' => true, 'message' => $layanan['nama_layanan'] . ' ditambahkan']);
    exit;
}

// ==========================================
// LOGIKA: UPDATE QTY
// ==========================================
if ($action === 'update') {
    $idCart = (int)($data['id_cart'] ?? 0); // Menggunakan id_cart
    $qty = max(1, (float)($data['qty'] ?? 1));

    if ($idCart === 0) {
        echo json_encode(['success' => false, 'message' => 'ID Cart tidak valid']);
        exit;
    }

    // Pastikan cart milik user yang login
    $cek = $db->query("SELECT id_cart FROM cart WHERE id_cart = $idCart AND id_user = $userId")->fetch_assoc();
    if ($cek) {
        $db->query("UPDATE cart SET qty = $qty WHERE id_cart = $idCart");
        echo json_encode(['success' => true, 'message' => 'Jumlah diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan']);
    }
    exit;
}

// ==========================================
// LOGIKA: HAPUS DARI KERANJANG
// ==========================================
if ($action === 'delete' || $action === 'remove' || $action === 'hapus' || $action === 'delete_item') {
    // Super fleksibel menangkap ID (bisa id_cart, id_keranjang, id, atau cart_id)
    $idCart = (int)($data['id_cart'] ?? $data['id_keranjang'] ?? $data['id'] ?? $data['cart_id'] ?? 0);

    if ($idCart === 0) {
        echo json_encode(['success' => false, 'message' => 'ID Cart tidak valid']);
        exit;
    }

    $db->query("DELETE FROM cart WHERE id_cart = $idCart AND id_user = $userId");
    
    if ($db->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Item dihapus dari keranjang']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan atau sudah dihapus']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali: ' . $action]);