<?php

require_once '/var/www/src/config/auth.php';
require_staff();

require_once '/var/www/src/config/database.php';

$pageTitle = 'Tổng quan';

/* Số đơn theo trạng thái (nhân viên và admin đều xem được) */
$statusCounts = [
    'Pending' => 0, 'Confirmed' => 0, 'Shipping' => 0,
    'Completed' => 0, 'Cancelled' => 0,
];

$result = $conn->query('SELECT Status, COUNT(*) AS total FROM orders GROUP BY Status');
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['Status']] = (int) $row['total'];
}
$result->free();

/* Phần thống kê doanh thu chỉ dành cho admin */
$revenueToday = $revenueMonth = $revenueYear = 0;
$monthly = [];
$bestSellers = [];
$lowStock = [];
$customerCount = 0;

if (is_admin()) {

    $result = $conn->query("
        SELECT
            COALESCE(SUM(CASE WHEN DATE(OrderDate) = CURDATE()
                              THEN TotalAmount END), 0) AS today,
            COALESCE(SUM(CASE WHEN YEAR(OrderDate) = YEAR(CURDATE())
                               AND MONTH(OrderDate) = MONTH(CURDATE())
                              THEN TotalAmount END), 0) AS month,
            COALESCE(SUM(CASE WHEN YEAR(OrderDate) = YEAR(CURDATE())
                              THEN TotalAmount END), 0) AS year
        FROM orders
        WHERE Status = 'Completed'
    ");
    $row = $result->fetch_assoc();
    $result->free();

    $revenueToday = (float) $row['today'];
    $revenueMonth = (float) $row['month'];
    $revenueYear  = (float) $row['year'];

    $result = $conn->query("
        SELECT MONTH(OrderDate) AS m, COUNT(*) AS orders_count,
               SUM(TotalAmount) AS revenue
        FROM orders
        WHERE Status = 'Completed' AND YEAR(OrderDate) = YEAR(CURDATE())
        GROUP BY MONTH(OrderDate)
        ORDER BY m
    ");
    while ($row = $result->fetch_assoc()) {
        $monthly[(int) $row['m']] = $row;
    }
    $result->free();

    $result = $conn->query("
        SELECT p.ProductCode, p.ProductName, SUM(od.Quantity) AS sold
        FROM orderdetail od
        JOIN orders o ON o.OrderID = od.OrderID
        JOIN products p ON p.ProductID = od.ProductID
        WHERE o.Status <> 'Cancelled'
        GROUP BY p.ProductID, p.ProductCode, p.ProductName
        ORDER BY sold DESC
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $bestSellers[] = $row;
    }
    $result->free();

    $result = $conn->query("
        SELECT ProductCode, ProductName, StockQuantity
        FROM products
        WHERE IsActive = 1 AND StockQuantity <= 5
        ORDER BY StockQuantity ASC
        LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        $lowStock[] = $row;
    }
    $result->free();

    $result = $conn->query(
        'SELECT COUNT(*) AS total FROM customers WHERE Email IS NOT NULL'
    );
    $customerCount = (int) $result->fetch_assoc()['total'];
    $result->free();
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">

    <h1 class="h3 mb-4">Tổng quan hệ thống</h1>

    <h2 class="h5">Đơn hàng theo trạng thái</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($statusCounts as $status => $total): ?>
            <div class="col-6 col-md">
                <a class="text-decoration-none"
                   href="/admin/orders/?status=<?= e($status) ?>">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <div class="fs-2 fw-bold"><?= $total ?></div>
                            <span class="badge <?= order_status_badge($status) ?>">
                                <?= e(order_status_label($status)) ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

<?php if (is_admin()): ?>

    <h2 class="h5">Doanh thu (đơn hoàn thành)</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Hôm nay</div>
                <div class="fs-4 fw-bold"><?= money($revenueToday) ?></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Tháng này</div>
                <div class="fs-4 fw-bold"><?= money($revenueMonth) ?></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Năm nay</div>
                <div class="fs-4 fw-bold"><?= money($revenueYear) ?></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Khách hàng đã đăng ký</div>
                <div class="fs-4 fw-bold"><?= $customerCount ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Doanh thu theo tháng (năm nay)</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Tháng</th><th class="text-end">Số đơn</th>
                                <th class="text-end">Doanh thu</th></tr>
                        </thead>
                        <tbody>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <tr>
                                <td>Tháng <?= $m ?></td>
                                <td class="text-end">
                                    <?= (int) ($monthly[$m]['orders_count'] ?? 0) ?>
                                </td>
                                <td class="text-end">
                                    <?= money($monthly[$m]['revenue'] ?? 0) ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">

            <div class="card mb-4">
                <div class="card-header"><strong>Sản phẩm bán chạy</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Mã</th><th>Tên sản phẩm</th>
                                <th class="text-end">Đã bán</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bestSellers as $item): ?>
                            <tr>
                                <td><?= e($item['ProductCode']) ?></td>
                                <td><?= e($item['ProductName']) ?></td>
                                <td class="text-end"><?= (int) $item['sold'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bestSellers): ?>
                            <tr><td colspan="3" class="text-muted text-center">
                                Chưa có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Sản phẩm sắp hết hàng (≤ 5)</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Mã</th><th>Tên sản phẩm</th>
                                <th class="text-end">Tồn kho</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStock as $item): ?>
                            <tr>
                                <td><?= e($item['ProductCode']) ?></td>
                                <td><?= e($item['ProductName']) ?></td>
                                <td class="text-end text-danger fw-bold">
                                    <?= (int) $item['StockQuantity'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$lowStock): ?>
                            <tr><td colspan="3" class="text-muted text-center">
                                Không có sản phẩm nào sắp hết</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

<?php else: ?>

    <div class="alert alert-info">
        Bạn đăng nhập với vai trò nhân viên: có thể xem, xác nhận và cập nhật
        đơn hàng. Thống kê doanh thu chỉ dành cho quản trị viên.
    </div>

<?php endif; ?>

</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
