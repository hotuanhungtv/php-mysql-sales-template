<?php
require_once '/var/www/src/config/auth.php';
require_admin();

require_once __DIR__ . '/../../../src/config/database.php';

// Chỉ xử lý yêu cầu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/shippers/index.php');
    exit;
}

// Lấy mã đơn vị vận chuyển
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: /admin/shippers/index.php?msg=invalid_id');
    exit;
}

try {
    // Kiểm tra kết nối MySQLi
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Kết nối cơ sở dữ liệu không hợp lệ.');
    }

    // Xóa đơn vị vận chuyển
    $stmt = $conn->prepare(
        'DELETE FROM shippers WHERE ShipperID = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt->close();

        header('Location: /admin/shippers/index.php?msg=deleted');
        exit;
    }

    $stmt->close();

    header('Location: /admin/shippers/index.php?msg=not_found');
    exit;

} catch (Throwable $e) {
    error_log('Lỗi xóa shipper: ' . $e->getMessage());

    header('Location: /admin/shippers/index.php?msg=delete_error');
    exit;
}