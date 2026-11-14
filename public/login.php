<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng nhập';

if (isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

$email = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email =
        trim($_POST['email'] ?? '');

    $password =
        $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $errorMessage =
            'Vui lòng nhập email và mật khẩu.';

    } elseif (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {

        $errorMessage =
            'Email không hợp lệ.';

    } else {

        $sql = "
            SELECT
                CustomerID,
                CustomerName,
                Email,
                PasswordHash,
                IsActive
            FROM customers
            WHERE Email = ?
        ";

        $stmt =
            $conn->prepare($sql);

        $stmt->bind_param(
            's',
            $email
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $customer =
            $result->fetch_assoc();

        $result->free();
        $stmt->close();

        if ($customer && !(int) $customer['IsActive']) {

            $errorMessage =
                'Tài khoản đã bị khóa. Vui lòng liên hệ cửa hàng.';

        } elseif ($customer
            && !empty($customer['PasswordHash'])
            && password_verify(
                $password,
                $customer['PasswordHash']
            )) {

            session_regenerate_id(true);

            $_SESSION['customer_id'] =
                (int) $customer['CustomerID'];

            $_SESSION['customer_name'] =
                $customer['CustomerName'];

            $next = $_SESSION['after_login'] ?? '/';
            unset($_SESSION['after_login']);

            header('Location: ' . $next);
            exit;

        } else {

            $errorMessage =
                'Email hoặc mật khẩu không đúng.';
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>
<div class="container py-4">
    <div class="auth-wrap">
        <div class="row justify-content-center w-100 mx-0">
            <div class="col-md-8 col-lg-5">
                <div class="auth-card">

                    <div class="auth-icon"><i class="bi bi-person-fill"></i></div>
                    <h1>Chào mừng trở lại</h1>
                    <p class="sub">Đăng nhập để tiếp tục mua sắm</p>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            Đăng ký thành công. Bạn có thể đăng nhập.
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-icon">
                                <i class="bi bi-envelope"></i>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($email) ?>"
                                       placeholder="ban@example.com" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <div class="input-icon">
                                <i class="bi bi-lock"></i>
                                <input type="password" class="form-control" id="password"
                                       name="password" placeholder="Nhập mật khẩu" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg">Đăng nhập</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        Chưa có tài khoản?
                        <a href="/register.php" class="fw-bold text-decoration-none">Đăng ký ngay</a>
                    </p>

                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();