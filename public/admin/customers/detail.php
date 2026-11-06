<?php

require_once '/var/www/src/config/auth.php';
require_admin();

require_once '/var/www/src/config/database.php';

$pageTitle = 'Chi tiết khách hàng';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare('SELECT * FROM customers WHERE CustomerID = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    header('Location: /admin/customers/');
    exit;
}

$stmt = $conn->prepare(
    'SELECT OrderID, OrderDate, TotalAmount, Status
     FROM orders WHERE CustomerID = ? ORDER BY OrderID DESC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$orders = $stmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= e($customer['CustomerName']) ?></h2>
        <a href="/admin/customers/" class="btn btn-outline-secondary">Quay lại</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1"><strong>Email:</strong> <?= e($customer['Email'] ?? '—') ?></p>
            <p class="mb-1"><strong>Điện thoại:</strong> <?= e($customer['Phone'] ?? '—') ?></p>
            <p class="mb-0"><strong>Địa chỉ:</strong> <?= e($customer['Address'] ?? '—') ?></p>
        </div>
    </div>

    <h4>Lịch sử mua hàng</h4>

    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr><th>Mã đơn</th><th>Ngày đặt</th>
                <th class="text-end">Tổng tiền</th><th>Trạng thái</th><th></th></tr>
        </thead>
        <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
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
                       href="/admin/orders/detail.php?id=<?= (int) $o['OrderID'] ?>">Xem</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$stmt->close();
$conn->close();
