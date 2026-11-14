<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng thành công';

$orderID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($orderID <= 0) {
    header('Location: /');
    exit;
}

$sqlOrder = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName,
        c.Phone,
        c.Address
    FROM orders o, customers c
    WHERE o.CustomerID = c.CustomerID
      AND o.OrderID = ?
";

$stmtOrder = $conn->prepare($sqlOrder);
$stmtOrder->bind_param('i', $orderID);
$stmtOrder->execute();
$orderResult = $stmtOrder->get_result();
$order = $orderResult->fetch_assoc();
$orderResult->free();
$stmtOrder->close();

if (!$order) {
    header('Location: /');
    exit;
}

$sqlDetail = "
    SELECT
        od.Quantity,
        od.UnitPrice,
        p.ProductCode,
        p.ProductName
    FROM orderdetail od, products p
    WHERE od.ProductID = p.ProductID
      AND od.OrderID = ?
    ORDER BY od.OrderDetailID
";

$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param('i', $orderID);
$stmtDetail->execute();
$detailResult = $stmtDetail->get_result();

$orderItems = [];

while ($row = $detailResult->fetch_assoc()) {
    $row['Subtotal'] =
        (float) $row['UnitPrice']
        * (int) $row['Quantity'];

    $orderItems[] = $row;
}

$detailResult->free();
$stmtDetail->close();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>
<div class="container py-4 py-lg-5">

    <div class="success-hero">
        <div class="success-check"><i class="bi bi-check-lg"></i></div>
        <h1 class="h2 mb-2">Đặt hàng thành công!</h1>
        <p class="text-muted mb-3">Cảm ơn bạn đã mua sắm. Chúng tôi sẽ sớm liên hệ để giao hàng.</p>
        <span class="order-code">Mã đơn hàng #<?= (int) $order['OrderID'] ?></span>
    </div>

    <div class="row g-4">

        <div class="col-lg-4">
            <div class="panel h-100">
                <h2 class="panel-title"><i class="bi bi-info-circle"></i> Thông tin đơn hàng</h2>
                <ul class="info-list">
                    <li><span>Khách hàng</span><span><?= htmlspecialchars($order['CustomerName']) ?></span></li>
                    <li><span>Số điện thoại</span><span><?= htmlspecialchars($order['Phone']) ?></span></li>
                    <li><span>Địa chỉ</span><span><?= htmlspecialchars($order['Address']) ?></span></li>
                    <li><span>Ngày đặt</span><span><?= htmlspecialchars($order['OrderDate']) ?></span></li>
                    <li><span>Trạng thái</span><span><?= htmlspecialchars($order['Status']) ?></span></li>
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel h-100">
                <h2 class="panel-title"><i class="bi bi-box-seam"></i> Chi tiết sản phẩm</h2>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Mã SP</th>
                                <th>Sản phẩm</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-center">SL</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['ProductCode']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($item['ProductName']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format((float) $item['UnitPrice'], 0, ',', '.') ?> đ
                                    </td>
                                    <td class="text-center"><?= (int) $item['Quantity'] ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format((float) $item['Subtotal'], 0, ',', '.') ?> đ
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Tổng cộng</th>
                                <th class="text-end text-nowrap product-price">
                                    <?= number_format((float) $order['TotalAmount'], 0, ',', '.') ?> đ
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="/products.php" class="btn btn-primary">
                        <i class="bi bi-bag"></i> Tiếp tục mua hàng
                    </a>
                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <a href="/orders.php" class="btn btn-outline-secondary">
                            <i class="bi bi-receipt"></i> Xem đơn hàng của tôi
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
