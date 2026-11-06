<?php

require_once '/var/www/src/config/auth.php';
require_admin();

require_once '/var/www/src/config/database.php';

$pageTitle = 'Quản lý nhân viên';

$selfID = (int) $_SESSION['staff_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        if ($action === 'create') {

            $lastName  = trim($_POST['last_name'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $password  = $_POST['password'] ?? '';
            $role      = $_POST['role'] ?? 'staff';

            if ($lastName === '' || $firstName === '') {
                throw new Exception('Vui lòng nhập họ và tên.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email không hợp lệ.');
            }
            if (strlen($password) < 8) {
                throw new Exception('Mật khẩu phải có ít nhất 8 ký tự.');
            }
            if (!in_array($role, ['admin', 'staff'], true)) {
                throw new Exception('Vai trò không hợp lệ.');
            }

            $stmt = $conn->prepare('SELECT 1 FROM employees WHERE Email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                throw new Exception('Email này đã được sử dụng.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'INSERT INTO employees
                    (LastName, FirstName, Email, PasswordHash, Role)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $lastName, $firstName, $email, $hash, $role);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'Đã thêm nhân viên.');

        } elseif ($action === 'role') {

            $id   = (int) ($_POST['id'] ?? 0);
            $role = $_POST['role'] ?? '';

            if ($id === $selfID) {
                throw new Exception('Không thể đổi vai trò của chính bạn.');
            }
            if (!in_array($role, ['admin', 'staff'], true)) {
                throw new Exception('Vai trò không hợp lệ.');
            }

            $stmt = $conn->prepare('UPDATE employees SET Role = ? WHERE EmployeeID = ?');
            $stmt->bind_param('si', $role, $id);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'Đã cập nhật vai trò.');

        } elseif ($action === 'delete') {

            $id = (int) ($_POST['id'] ?? 0);

            if ($id === $selfID) {
                throw new Exception('Không thể xóa chính tài khoản của bạn.');
            }

            $stmt = $conn->prepare(
                'SELECT COUNT(*) AS total FROM orders WHERE EmployeeID = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $used = (int) $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            if ($used > 0) {
                throw new Exception(
                    'Nhân viên này đã xử lý ' . $used . ' đơn hàng, không thể xóa.'
                );
            }

            $stmt = $conn->prepare('DELETE FROM employees WHERE EmployeeID = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'Đã xóa nhân viên.');
        }

    } catch (Throwable $e) {
        flash_set('danger', $e->getMessage());
    }

    header('Location: /admin/employees/');
    exit;
}

$employees = $conn->query(
    'SELECT EmployeeID, LastName, FirstName, Email, Role
     FROM employees ORDER BY EmployeeID'
);

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">

    <h2 class="mb-3">Quản lý nhân viên</h2>

    <div class="card mb-4">
        <div class="card-header"><strong>Thêm nhân viên</strong></div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="col-md-2">
                    <input class="form-control" name="last_name"
                           placeholder="Họ" required>
                </div>
                <div class="col-md-2">
                    <input class="form-control" name="first_name"
                           placeholder="Tên" required>
                </div>
                <div class="col-md-3">
                    <input type="email" class="form-control" name="email"
                           placeholder="Email đăng nhập" required>
                </div>
                <div class="col-md-2">
                    <input type="password" class="form-control" name="password"
                           placeholder="Mật khẩu (≥ 8 ký tự)" required>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="role">
                        <option value="staff">Nhân viên</option>
                        <option value="admin">Quản trị viên</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100">Thêm</button>
                </div>
            </form>
        </div>
    </div>

    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr><th>Mã</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Thao tác</th></tr>
        </thead>
        <tbody>
        <?php while ($emp = $employees->fetch_assoc()): ?>
            <?php $isSelf = (int) $emp['EmployeeID'] === $selfID; ?>
            <tr>
                <td><?= (int) $emp['EmployeeID'] ?></td>
                <td>
                    <?= e($emp['LastName'] . ' ' . $emp['FirstName']) ?>
                    <?= $isSelf ? '<span class="badge bg-info text-dark">Bạn</span>' : '' ?>
                </td>
                <td><?= e($emp['Email'] ?? '(chưa có tài khoản)') ?></td>
                <td>
                    <span class="badge <?= $emp['Role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                        <?= $emp['Role'] === 'admin' ? 'Quản trị viên' : 'Nhân viên' ?>
                    </span>
                </td>
                <td class="text-nowrap">
                <?php if (!$isSelf): ?>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="id" value="<?= (int) $emp['EmployeeID'] ?>">
                        <input type="hidden" name="role"
                               value="<?= $emp['Role'] === 'admin' ? 'staff' : 'admin' ?>">
                        <button class="btn btn-sm btn-outline-primary">
                            <?= $emp['Role'] === 'admin' ? 'Hạ xuống nhân viên' : 'Nâng lên admin' ?>
                        </button>
                    </form>

                    <form method="post" class="d-inline"
                          onsubmit="return confirm('Xóa nhân viên này?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $emp['EmployeeID'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Xóa</button>
                    </form>
                <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
