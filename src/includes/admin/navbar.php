<?php
$staffName = $_SESSION['staff_name'] ?? '';
$staffRole = $_SESSION['staff_role'] ?? '';
$isAdminUser = $staffRole === 'admin';
?>
<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="/admin/">Sales Management</a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/admin/">Tổng quan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/orders/">Đơn hàng</a>
                </li>

                <?php if ($isAdminUser): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/products/">Sản phẩm</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/categories/">Danh mục</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/customers/">Khách hàng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/employees/">Nhân viên</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/shippers/">Shippers</a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <span class="text-light small">
                    <?= e($staffName) ?>
                    (<?= $isAdminUser ? 'Quản trị viên' : 'Nhân viên' ?>)
                </span>
                <a class="btn btn-outline-light btn-sm" href="/">Xem website</a>
                <a class="btn btn-outline-light btn-sm" href="/admin/logout.php">
                    Đăng xuất
                </a>
            </div>
        </div>
    </div>
</nav>
<?php flash_render(); ?>
