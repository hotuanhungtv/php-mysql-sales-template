<?php

require_once '/var/www/src/config/auth.php';
require_staff();

require_once '/var/www/src/config/database.php';

$pageTitle = 'Quản lý đơn hàng';

$validStatuses = ['Pending', 'Confirmed', 'Shipping', 'Completed', 'Cancelled'];

$status  = $_GET['status'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');
$date    = $_GET['date'] ?? '';

$where  = [];
$types  = '';
$params = [];

if (in_array($status, $validStatuses, true)) {
    $where[] = 'o.Status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($keyword !== '') {
    $where[] = '(c.CustomerName LIKE ? OR c.Phone LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'DATE(o.OrderDate) = ?';
    $types .= 's';
    $params[] = $date;
}

$sql = "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.Status,
           c.CustomerName, c.Phone
    FROM orders o
    JOIN customers c ON c.CustomerID = o.CustomerID
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY o.OrderID DESC';

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">

    <h2 class="mb-3">Quản lý đơn hàng</h2>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($validStatuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>>
                        <?= e(order_status_label($st)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
        </div>
        <div class="col-md-4">
            <input type="text" name="keyword" class="form-control"
                   placeholder="Tên khách hàng hoặc điện thoại"
                   value="<?= e($keyword) ?>">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary">Lọc</button>
            <a href="/admin/orders/" class="btn btn-outline-secondary">Xóa lọc</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Mã đơn</th><th>Ngày đặt</th><th>Khách hàng</th>
                    <th>Điện thoại</th><th class="text-end">Tổng tiền</th>
                    <th>Trạng thái</th><th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($order = $result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= (int) $order['OrderID'] ?></td>
                    <td><?= e($order['OrderDate']) ?></td>
                    <td><?= e($order['CustomerName']) ?></td>
                    <td><?= e($order['Phone'] ?? '') ?></td>
                    <td class="text-end"><?= money($order['TotalAmount']) ?></td>
                    <td>
                        <span class="badge <?= order_status_badge($order['Status']) ?>">
                            <?= e(order_status_label($order['Status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a href="/admin/orders/detail.php?id=<?= (int) $order['OrderID'] ?>"
                           class="btn btn-sm btn-primary">Xem</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$stmt->close();
$conn->close();
