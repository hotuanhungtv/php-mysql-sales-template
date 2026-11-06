<?php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/helpers.php';

/* Tự đăng xuất sau 30 phút không hoạt động */
const SESSION_TIMEOUT = 1800;

if (isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {

    unset(
        $_SESSION['customer_id'],
        $_SESSION['customer_name'],
        $_SESSION['staff_id'],
        $_SESSION['staff_name'],
        $_SESSION['staff_role']
    );
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

csrf_guard();
