<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng ký tài khoản';

$customerName = '';
$phone = '';
$email = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerName =
        trim($_POST['customer_name'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';

    if ($customerName === ''
        || $email === ''
        || $password === ''
        || $confirmPassword === '') {

        $errorMessage =
            'Vui lòng nhập đầy đủ thông tin bắt buộc.';

    } elseif (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {

        $errorMessage =
            'Email không hợp lệ.';

    } elseif (strlen($password) < 6) {

        $errorMessage =
            'Mật khẩu phải có ít nhất 6 ký tự.';

    } elseif ($password !== $confirmPassword) {

        $errorMessage =
            'Mật khẩu xác nhận không khớp.';

    } else {

        $sqlCheck = "
            SELECT CustomerID
            FROM customers
            WHERE Email = ?
        ";

        $stmtCheck =
            $conn->prepare($sqlCheck);

        $stmtCheck->bind_param(
            's',
            $email
        );

        $stmtCheck->execute();

        $resultCheck =
            $stmtCheck->get_result();

        $existingCustomer =
            $resultCheck->fetch_assoc();

        $resultCheck->free();
        $stmtCheck->close();

        if ($existingCustomer) {

            $errorMessage =
                'Email này đã được sử dụng.';

        } else {

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $sqlInsert = "
                INSERT INTO customers (
                    CustomerName,
                    Phone,
                    Email,
                    PasswordHash
                )
                VALUES (?, ?, ?, ?)
            ";

            $stmtInsert =
                $conn->prepare($sqlInsert);

            $stmtInsert->bind_param(
                'ssss',
                $customerName,
                $phone,
                $email,
                $passwordHash
            );

            $stmtInsert->execute();
            $stmtInsert->close();

            header(
                'Location: /login.php?registered=1'
            );
            exit;
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>
<div class="container py-4">
    <div class="auth-wrap">
        <div class="row justify-content-center w-100 mx-0">
            <div class="col-md-8 col-lg-6">
                <div class="auth-card">

                    <div class="auth-icon"><i class="bi bi-person-plus-fill"></i></div>
                    <h1>Tạo tài khoản</h1>
                    <p class="sub">Chỉ mất một phút để bắt đầu mua sắm</p>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Họ và tên</label>
                            <div class="input-icon">
                                <i class="bi bi-person"></i>
                                <input type="text" class="form-control" id="customer_name"
                                       name="customer_name"
                                       value="<?= htmlspecialchars($customerName) ?>"
                                       placeholder="Nguyễn Văn A" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <div class="input-icon">
                                    <i class="bi bi-telephone"></i>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                           value="<?= htmlspecialchars($phone) ?>"
                                           placeholder="09xx xxx xxx">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-icon">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= htmlspecialchars($email) ?>"
                                           placeholder="ban@example.com" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="password" class="form-label">Mật khẩu</label>
                                <div class="input-icon">
                                    <i class="bi bi-lock"></i>
                                    <input type="password" class="form-control" id="password"
                                           name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                                <div class="input-icon">
                                    <i class="bi bi-shield-lock"></i>
                                    <input type="password" class="form-control" id="confirm_password"
                                           name="confirm_password" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg">Đăng ký</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        Đã có tài khoản?
                        <a href="/login.php" class="fw-bold text-decoration-none">Đăng nhập</a>
                    </p>

                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();