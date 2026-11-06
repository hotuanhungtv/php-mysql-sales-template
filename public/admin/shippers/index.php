<?php
require_once '/var/www/src/config/auth.php';
require_admin();

require_once __DIR__ . '/../../../src/config/database.php';

// Kiểm tra kết nối MySQLi
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Lỗi: Không thể kết nối cơ sở dữ liệu.');
}

$shippers = [];
$error = '';

// Lấy danh sách đơn vị vận chuyển
try {
    $sql = "SELECT ShipperID, ShipperName, Phone
            FROM shippers
            ORDER BY ShipperID DESC";

    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $shippers[] = $row;
    }

    $result->free();
} catch (mysqli_sql_exception $e) {
    error_log('Lỗi lấy danh sách shipper: ' . $e->getMessage());
    $error = 'Không thể tải danh sách đơn vị vận chuyển.';
}

// Thông báo kết quả thao tác
$messages = [
    'created' => 'Thêm đơn vị vận chuyển thành công.',
    'updated' => 'Cập nhật đơn vị vận chuyển thành công.',
    'deleted' => 'Xóa đơn vị vận chuyển thành công.',
    'not_found' => 'Không tìm thấy đơn vị vận chuyển.',
    'invalid_id' => 'Mã đơn vị vận chuyển không hợp lệ.',
    'delete_error' => 'Không thể xóa đơn vị vận chuyển.'
];

$msg = $_GET['msg'] ?? '';
$notice = $messages[$msg] ?? '';
$noticeType = in_array($msg, ['not_found', 'invalid_id', 'delete_error'], true)
    ? 'warning'
    : 'success';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Quản lý giao hàng</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h2 class="mb-0">Quản lý giao hàng</h2>

        <a href="/admin/shippers/create.php" class="btn btn-primary">
            + Thêm người giao hàng
        </a>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="alert alert-<?= $noticeType ?> alert-dismissible fade show">
            <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php else: ?>

        <div class="card shadow-sm">
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0">

                        <thead class="table-dark">
                            <tr>
                                <th style="width: 90px;">Mã số</th>
                                <th>Tên người giao hàng</th>
                                <th>Số điện thoại</th>
                                <th style="width: 180px;">Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($shippers)): ?>

                                <?php foreach ($shippers as $shipper): ?>
                                    <tr>
                                        <td>
                                            <?= (int) $shipper['ShipperID'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $shipper['ShipperName'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $shipper['Phone'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <div class="d-flex gap-2">
                                                <a
                                                    href="/admin/shippers/edit.php?id=<?= (int) $shipper['ShipperID'] ?>"
                                                    class="btn btn-sm btn-warning"
                                                >
                                                    Sửa
                                                </a>

                                                <form
                                                    action="/admin/shippers/delete.php"
                                                    method="POST"
                                                    onsubmit="return confirm('Bạn có chắc chắn muốn xóa đơn vị vận chuyển này?');"
                                                >
<?= csrf_field() ?>
                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $shipper['ShipperID'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-danger"
                                                    >
                                                        Xóa
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        Chưa có đơn vị vận chuyển nào.
                                    </td>
                                </tr>

                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

    <?php endif; ?>

    <a href="/admin/" class="btn btn-secondary mt-3">
        Quay lại trang quản trị
    </a>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>