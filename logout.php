<?php
// user/logout.php

session_start();
session_destroy();

require_once __DIR__ . '/../config/database.php';

header('Location: ' . APP_URL . '/index.php');
exit;