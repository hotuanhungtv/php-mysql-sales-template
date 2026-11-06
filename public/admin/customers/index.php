<?php

require_once '/var/www/src/config/auth.php';
require_admin();

require_once '/var/www/src/config/database.php';

$pageTitle = 'Quản lý khách hàng';

/* Khóa / mở khóa tài khoản */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {

    $id = (int) $_POST['toggle_id'];

    $stmt = $conn->prepare(
        'UPDATE customers SET IsActive = NOT IsActive
         WHERE CustomerID = ? AND Email IS NOT NULL'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Đã cập nhật trạng thái tài khoản.');
    header('Location: /admin/customers/');
    exit;
}

$keyword = trim($_GET['keyword'] ?? '');
$like = '%' . $keyword . '%';

$stmt = $conn->prepare("
    SELECT
        c.CustomerID, c.CustomerName, c.Email, c.Phone, c.IsActive,
        COUNT(o.OrderID) AS order_count,
        COALESCE(SUM(CASE WHEN o.Status <> 'Cancelled'
                          THEN o.TotalAmount END), 0) AS total_spent
    FROM customers c
    LEFT JOIN orders o ON o.CustomerID = c.CustomerID
    WHERE c.CustomerName LIKE ? OR c.Email LIKE ? OR c.Phone LIKE ?
    GROUP BY c.CustomerID, c.CustomerName, c.Email, c.Phone, c.IsActive
    ORDER BY c.CustomerID DESC
");
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$customers = $stmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Quản lý khách hàng</h2>

        <form method="get" class="d-flex gap-2">
            <input type="text" name="keyword" class="form-control"
                   placeholder="Tên, email, điện thoại"
                   value="<?= e($keyword) ?>">
            <button class="btn btn-outline-primary">Tìm</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Mã</th><th>Khách hàng</th><th>Email</th><th>Điện thoại</th>
                    <th class="text-end">Số đơn</th>
                    <th class="text-end">Tổng đã mua</th>
                    <th>Tài khoản</th><th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($c = $customers->fetch_assoc()): ?>
                <tr>
                    <td><?= (int) $c['CustomerID'] ?></td>
                    <td><?= e($c['CustomerName']) ?></td>
                    <td><?= e($c['Email'] ?? '(khách vãng lai)') ?></td>
                    <td><?= e($c['Phone'] ?? '') ?></td>
                    <td class="text-end"><?= (int) $c['order_count'] ?></td>
                    <td class="text-end"><?= money($c['total_spent']) ?></td>
                    <td>
                        <?php if ($c['Email'] === null): ?>
                            <span class="text-muted">—</span>
                        <?php elseif ($c['IsActive']): ?>
                            <span class="badge bg-success">Hoạt động</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Đã khóa</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-primary"
                           href="/admin/customers/detail.php?id=<?= (int) $c['CustomerID'] ?>">
                            Lịch sử mua
                        </a>

                        <?php if ($c['Email'] !== null): ?>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Đổi trạng thái tài khoản này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="toggle_id"
                                       value="<?= (int) $c['CustomerID'] ?>">
                                <button class="btn btn-sm
                                    <?= $c['IsActive'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= $c['IsActive'] ? 'Khóa' : 'Mở khóa' ?>
                                </button>
                            </form>
                        <?php endif; ?>
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
