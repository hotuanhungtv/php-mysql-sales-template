<?php
require_once '/var/www/src/config/auth.php';
require_admin();

require_once __DIR__ . '/../../../src/config/database.php';

$errors = [];

// Lấy và kiểm tra mã đơn vị vận chuyển
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: /admin/shippers/index.php');
    exit;
}

// Lấy thông tin đơn vị vận chuyển
try {
    $stmt = $conn->prepare(
        'SELECT ShipperID, ShipperName, Phone
         FROM shippers
         WHERE ShipperID = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $shipper = $result->fetch_assoc();

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Lỗi lấy thông tin shipper: ' . $e->getMessage());
    die('Không thể tải thông tin đơn vị vận chuyển.');
}

// Không tìm thấy đơn vị vận chuyển
if (!$shipper) {
    header('Location: /admin/shippers/index.php?msg=not_found');
    exit;
}

$shipperName = $shipper['ShipperName'];
$phone = $shipper['Phone'] ?? '';

// Xử lý cập nhật
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

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare(
                'UPDATE shippers
                 SET ShipperName = ?, Phone = ?
                 WHERE ShipperID = ?'
            );

            $stmt->bind_param('ssi', $shipperName, $phone, $id);
            $stmt->execute();
            $stmt->close();

            header('Location: /admin/shippers/index.php?msg=updated');
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log('Lỗi cập nhật shipper: ' . $e->getMessage());
            $errors[] = 'Không thể cập nhật đơn vị vận chuyển. Vui lòng thử lại.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Chỉnh sửa đơn vị vận chuyển</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 650px;">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Chỉnh sửa thông tin gnuwoif giao hàng</h4>
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
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    Cập nhật
                </button>

                <a
                    href="/admin/shippers/index.php"
                    class="btn btn-secondary"
                >
                    Hủy
                </a>
            </form>

        </div>
    </div>
</div>
</body>
</html>