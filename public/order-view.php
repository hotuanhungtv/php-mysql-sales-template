<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Chi tiết đơn hàng';

if (!isset($_SESSION['customer_id'])) {
    header('Location: /login.php');
    exit;
}

$customerID = (int) $_SESSION['customer_id'];
$orderID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* Hủy đơn: chỉ khi đơn đang "Chờ xác nhận", hoàn lại tồn kho */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {

    $orderID = (int) ($_POST['order_id'] ?? 0);

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            'SELECT Status FROM orders
             WHERE OrderID = ? AND CustomerID = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $orderID, $customerID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('Không tìm thấy đơn hàng.');
        }

        if ($row['Status'] !== 'Pending') {
            throw new Exception(
                'Chỉ hủy được đơn đang ở trạng thái "Chờ xác nhận".'
            );
        }

        $stmt = $conn->prepare(
            'SELECT ProductID, Quantity FROM orderdetail WHERE OrderID = ?'
        );
        $stmt->bind_param('i', $orderID);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $restore = $conn->prepare(
            'UPDATE products SET StockQuantity = StockQuantity + ?
             WHERE ProductID = ?'
        );

        foreach ($items as $item) {
            $qty = (int) $item['Quantity'];
            $pid = (int) $item['ProductID'];
            $restore->bind_param('ii', $qty, $pid);
            $restore->execute();
        }

        $restore->close();

        $stmt = $conn->prepare(
            "UPDATE orders SET Status = 'Cancelled' WHERE OrderID = ?"
        );
        $stmt->bind_param('i', $orderID);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        flash_set('success', 'Đã hủy đơn hàng #' . $orderID . '.');

    } catch (Throwable $e) {
        $conn->rollback();
        flash_set('danger', $e->getMessage());
    }

    header('Location: /order-view.php?id=' . $orderID);
    exit;
}

/* Chỉ xem được đơn của chính mình */
$stmt = $conn->prepare(
    'SELECT OrderID, OrderDate, TotalAmount, Status
     FROM orders WHERE OrderID = ? AND CustomerID = ?'
);
$stmt->bind_param('ii', $orderID, $customerID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    flash_set('danger', 'Không tìm thấy đơn hàng.');
    header('Location: /orders.php');
    exit;
}

$stmt = $conn->prepare(
    'SELECT od.Quantity, od.UnitPrice, p.ProductID, p.ProductCode, p.ProductName
     FROM orderdetail od
     JOIN products p ON p.ProductID = od.ProductID
     WHERE od.OrderID = ?
     ORDER BY od.OrderDetailID'
);
$stmt->bind_param('i', $orderID);
$stmt->execute();
$items = $stmt->get_result();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>
<div class="container py-4 py-lg-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h2 mb-0">Đơn hàng #<?= (int) $order['OrderID'] ?></h1>
        <a href="/orders.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>

    <div class="panel mb-4">
        <ul class="info-list">
            <li>
                <span>Ngày đặt</span>
                <span><?= e($order['OrderDate']) ?></span>
            </li>
            <li>
                <span>Trạng thái</span>
                <span>
                    <span class="badge <?= order_status_badge($order['Status']) ?>">
                        <?= e(order_status_label($order['Status'])) ?>
                    </span>
                </span>
            </li>
            <li>
                <span>Tổng tiền</span>
                <span class="product-price"><?= money($order['TotalAmount']) ?></span>
            </li>
        </ul>
    </div>

    <div class="table-responsive mb-4">
        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mã SP</th>
                    <th>Sản phẩm</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Số lượng</th>
                    <th class="text-end">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= e($item['ProductCode']) ?></td>
                    <td>
                        <a class="text-decoration-none fw-semibold"
                           href="/product-detail.php?id=<?= (int) $item['ProductID'] ?>">
                            <?= e($item['ProductName']) ?>
                        </a>
                    </td>
                    <td class="text-end"><?= money($item['UnitPrice']) ?></td>
                    <td class="text-end"><?= (int) $item['Quantity'] ?></td>
                    <td class="text-end fw-bold">
                        <?= money($item['UnitPrice'] * $item['Quantity']) ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($order['Status'] === 'Pending'): ?>
        <form method="post"
              onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?');">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int) $order['OrderID'] ?>">
            <button class="btn btn-danger" name="cancel_order">
                <i class="bi bi-x-circle"></i> Hủy đơn hàng
            </button>
        </form>
    <?php endif; ?>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$stmt->close();
$conn->close();
