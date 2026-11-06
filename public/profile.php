<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Thông tin cá nhân';

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['after_login'] = '/profile.php';
    header('Location: /login.php');
    exit;
}

$customerID = (int) $_SESSION['customer_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profile'])) {

        $name    = trim($_POST['customer_name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            $errors[] = 'Họ và tên không được để trống.';
        } elseif (!preg_match('/^[0-9+\s.-]{0,20}$/', $phone)) {
            $errors[] = 'Số điện thoại không hợp lệ.';
        } else {
            $stmt = $conn->prepare(
                'UPDATE customers SET CustomerName = ?, Phone = ?, Address = ?
                 WHERE CustomerID = ?'
            );
            $stmt->bind_param('sssi', $name, $phone, $address, $customerID);
            $stmt->execute();
            $stmt->close();

            $_SESSION['customer_name'] = $name;
            flash_set('success', 'Đã cập nhật thông tin cá nhân.');
            header('Location: /profile.php');
            exit;
        }
    }

    if (isset($_POST['change_password'])) {

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare(
            'SELECT PasswordHash FROM customers WHERE CustomerID = ?'
        );
        $stmt->bind_param('i', $customerID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['PasswordHash'] ?? '')) {
            $errors[] = 'Mật khẩu hiện tại không đúng.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        } elseif ($new !== $confirm) {
            $errors[] = 'Xác nhận mật khẩu mới không khớp.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'UPDATE customers SET PasswordHash = ? WHERE CustomerID = ?'
            );
            $stmt->bind_param('si', $hash, $customerID);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'Đã đổi mật khẩu.');
            header('Location: /profile.php');
            exit;
        }
    }
}

$stmt = $conn->prepare(
    'SELECT CustomerName, Email, Phone, Address FROM customers WHERE CustomerID = ?'
);
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    header('Location: /logout.php');
    exit;
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container py-4">

    <h1 class="h3 mb-4">Thông tin cá nhân</h1>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="row g-4">

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Thông tin liên hệ</strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label">Email (không đổi được)</label>
                            <input class="form-control" disabled
                                   value="<?= e($customer['Email']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Họ và tên</label>
                            <input class="form-control" name="customer_name" required
                                   value="<?= e($customer['CustomerName']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input class="form-control" name="phone"
                                   value="<?= e($customer['Phone'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Địa chỉ</label>
                            <textarea class="form-control" name="address" rows="2"><?= e($customer['Address'] ?? '') ?></textarea>
                        </div>

                        <button class="btn btn-primary" name="update_profile">
                            Lưu thay đổi
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><strong>Đổi mật khẩu</strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu hiện tại</label>
                            <input type="password" class="form-control"
                                   name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới (≥ 8 ký tự)</label>
                            <input type="password" class="form-control"
                                   name="new_password" required minlength="8">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nhập lại mật khẩu mới</label>
                            <input type="password" class="form-control"
                                   name="confirm_password" required>
                        </div>

                        <button class="btn btn-warning" name="change_password">
                            Đổi mật khẩu
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();
