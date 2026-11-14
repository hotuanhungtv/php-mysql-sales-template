<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đăng nhập quản trị';

if (!empty($_SESSION['staff_id'])) {
    header('Location: /admin/');
    exit;
}

$email = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errorMessage = 'Vui lòng nhập email và mật khẩu.';
    } else {
        $stmt = $conn->prepare(
            'SELECT EmployeeID, LastName, FirstName, PasswordHash, Role
             FROM employees
             WHERE Email = ?'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($employee
            && !empty($employee['PasswordHash'])
            && password_verify($password, $employee['PasswordHash'])) {

            session_regenerate_id(true);

            $_SESSION['staff_id'] = (int) $employee['EmployeeID'];
            $_SESSION['staff_name'] =
                trim($employee['LastName'] . ' ' . $employee['FirstName']);
            $_SESSION['staff_role'] = $employee['Role'];

            header('Location: /admin/');
            exit;
        }

        $errorMessage = 'Email hoặc mật khẩu không đúng.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body admin-login">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5 col-xl-4">
            <div class="admin-login-card">

                <div class="auth-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <h1 class="h4 text-center mb-1">Đăng nhập quản trị</h1>
                <p class="text-muted small text-center mb-4">Dành cho nhân viên và quản trị viên</p>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= e($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= e($email) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" class="form-control" id="password"
                                   name="password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg">Đăng nhập</button>
                </form>

                <div class="text-center mt-4">
                    <a href="/" class="small text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Về trang bán hàng
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>
