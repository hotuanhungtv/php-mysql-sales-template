<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đơn hàng của tôi';

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['after_login'] = '/orders.php';
    header('Location: /login.php');
    exit;
}

$customerID = (int) $_SESSION['customer_id'];

$stmt = $conn->prepare(
    'SELECT OrderID, OrderDate, TotalAmount, Status
     FROM orders WHERE CustomerID = ? ORDER BY OrderID DESC'
);
$stmt->bind_param('i', $customerID);
$stmt->execute();
$orders = $stmt->get_result();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container py-4">

    <h1 class="h3 mb-4">Đơn hàng của tôi</h1>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mã đơn</th><th>Ngày đặt</th>
                    <th class="text-end">Tổng tiền</th>
                    <th>Trạng thái</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php $count = 0; while ($o = $orders->fetch_assoc()): $count++; ?>
                <tr>
                    <td>#<?= (int) $o['OrderID'] ?></td>
                    <td><?= e($o['OrderDate']) ?></td>
                    <td class="text-end"><?= money($o['TotalAmount']) ?></td>
                    <td>
                        <span class="badge <?= order_status_badge($o['Status']) ?>">
                            <?= e(order_status_label($o['Status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-primary"
                           href="/order-view.php?id=<?= (int) $o['OrderID'] ?>">
                            Chi tiết
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>

            <?php if ($count === 0): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        Bạn chưa có đơn hàng nào.
                        <a href="/products.php">Mua sắm ngay</a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$stmt->close();
$conn->close();
