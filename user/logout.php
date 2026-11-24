<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hancurkan sesi
session_unset();
session_destroy();

// Ambil config
require_once __DIR__ . '/../config/database.php';

// Redirect ke website online
header('Location: ' . APP_URL . '/index.php');
exit;