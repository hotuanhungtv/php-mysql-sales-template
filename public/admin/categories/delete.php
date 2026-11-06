<?php

require_once '/var/www/src/config/auth.php';
require_admin();

require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/categories/');
    exit;
}

$categoryID = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($categoryID > 0) {
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM products WHERE CategoryID = ?'
    );
    $stmt->bind_param('i', $categoryID);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($count > 0) {
        flash_set('danger',
            'Không thể xóa danh mục đang có ' . $count . ' sản phẩm.');
    } else {
        $stmt = $conn->prepare('DELETE FROM categories WHERE CategoryID = ?');
        $stmt->bind_param('i', $categoryID);
        $stmt->execute();
        $stmt->close();

        flash_set('success', 'Đã xóa danh mục.');
    }
}

$conn->close();
header('Location: /admin/categories/');
exit;
