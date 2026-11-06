<?php

require_once '/var/www/src/config/auth.php';
require_admin();

require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products/');
    exit;
}

$productID = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($productID <= 0) {
    header('Location: /admin/products/');
    exit;
}

try {
    // Sản phẩm đã có trong đơn hàng thì chỉ ngừng bán để giữ lịch sử đơn
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM orderdetail WHERE ProductID = ?'
    );
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $used = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($used > 0) {
        $stmt = $conn->prepare(
            'UPDATE products SET IsActive = 0 WHERE ProductID = ?'
        );
        $stmt->bind_param('i', $productID);
        $stmt->execute();
        $stmt->close();

        flash_set('warning',
            'Sản phẩm đã có trong đơn hàng nên được chuyển sang ngừng bán '
            . '(không xóa hẳn để giữ lịch sử đơn).');
    } else {
        // Lấy tên file ảnh để xóa khỏi ổ đĩa
        $stmt = $conn->prepare(
            'SELECT ImageFile FROM product_images WHERE ProductID = ?'
        );
        $stmt->bind_param('i', $productID);
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Ảnh trong DB tự xóa theo (ON DELETE CASCADE)
        $stmt = $conn->prepare('DELETE FROM products WHERE ProductID = ?');
        $stmt->bind_param('i', $productID);
        $stmt->execute();
        $stmt->close();

        foreach ($images as $img) {
            $path = '/var/www/html/uploads/products/' . basename($img['ImageFile']);

            // Chỉ xóa file nếu không còn sản phẩm nào khác dùng chung
            $check = $conn->prepare(
                'SELECT COUNT(*) AS total FROM product_images WHERE ImageFile = ?'
            );
            $check->bind_param('s', $img['ImageFile']);
            $check->execute();
            $shared = (int) $check->get_result()->fetch_assoc()['total'];
            $check->close();

            if ($shared === 0 && is_file($path)) {
                @unlink($path);
            }
        }

        flash_set('success', 'Đã xóa sản phẩm.');
    }
} catch (Throwable $e) {
    error_log('Lỗi xóa sản phẩm: ' . $e->getMessage());
    flash_set('danger', 'Không thể xóa sản phẩm này.');
}

$conn->close();
header('Location: /admin/products/');
exit;
