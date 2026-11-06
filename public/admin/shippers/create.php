<?php
require_once '/var/www/src/config/auth.php';
require_admin();

require_once __DIR__ . '/../../../src/config/database.php';

$errors = [];
$shipperName = '';
$phone = '';

// Kiểm tra kết nối cơ sở dữ liệu
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Lỗi: Kết nối cơ sở dữ liệu chưa được khởi tạo.');
}

// Xử lý biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperName = trim($_POST['ShipperName'] ?? '');
    $phone = trim($_POST['Phone'] ?? '');

    // Kiểm tra tên đơn vị vận chuyển
    if ($shipperName === '') {
        $errors[] = 'Tên đơn vị vận chuyển không được để trống.';
    } elseif (mb_strlen($shipperName) > 100) {
        $errors[] = 'Tên đơn vị vận chuyển không được vượt quá 100 ký tự.';
    }

    // Kiểm tra số điện thoại
    if (mb_strlen($phone) > 20) {
        $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
    }

    // Lưu dữ liệu
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO shippers (ShipperName, Phone)
                    VALUES (?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $shipperName, $phone);
            $stmt->execute();
            $stmt->close();

            header('Location: /admin/shippers/index.php?msg=created');
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log('Lỗi thêm shipper: ' . $e->getMessage());
            $errors[] = 'Không thể thêm đơn vị vận chuyển. Vui lòng thử lại.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Thêm đơn vị vận chuyển</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 650px;">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Thêm người giao hàng</h4>
        </div>

        <div class="card-body">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li>
                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
<?= csrf_field() ?>
                <div class="mb-3">
                    <label for="ShipperName" class="form-label">
                        Tên người giao hàng
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="ShipperName"
                        name="ShipperName"
                        maxlength="100"
                        value="<?= htmlspecialchars(
                            $shipperName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Nhập tên người giao hàng"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="Phone" class="form-label">
                        Số điện thoại
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="Phone"
                        name="Phone"
                        maxlength="20"
                        value="<?= htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Nhập số điện thoại"
                    >
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        Lưu
                    </button>

                    <a
                        href="/admin/shippers/index.php"
                        class="btn btn-secondary"
                    >
                        Quay lại
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>
</body>
</html>