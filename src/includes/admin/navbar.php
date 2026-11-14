<?php
$staffName   = $_SESSION['staff_name'] ?? '';
$staffRole   = $_SESSION['staff_role'] ?? '';
$isAdminUser = $staffRole === 'admin';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

/* [đường dẫn, nhãn, icon, chỉ admin?, khớp chính xác?] */
$adminMenu = [
    ['/admin/',            'Tổng quan', 'bi-speedometer2', false, true],
    ['/admin/orders/',     'Đơn hàng',  'bi-receipt',      false, false],
    ['/admin/products/',   'Sản phẩm',  'bi-box-seam',     true,  false],
    ['/admin/categories/', 'Danh mục',  'bi-tags',         true,  false],
    ['/admin/customers/',  'Khách hàng', 'bi-people',      true,  false],
    ['/admin/employees/',  'Nhân viên', 'bi-person-badge', true,  false],
    ['/admin/shippers/',   'Shippers',  'bi-truck',        true,  false],
];

function adminIsActive(string $href, bool $exact, string $path): bool
{
    if ($exact) {
        return in_array($path, ['/admin', '/admin/', '/admin/index.php'], true);
    }
    return str_starts_with($path, $href);
}
?>
<nav class="navbar navbar-expand-lg admin-nav">
    <div class="container-fluid px-4">

        <a class="navbar-brand" href="/admin/">
            <span class="brand-icon"><i class="bi bi-shop"></i></span>
            Quản trị bán hàng
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mt-3 mt-lg-0 ms-lg-3 gap-lg-1">
                <?php foreach ($adminMenu as [$href, $label, $icon, $adminOnly, $exact]): ?>
                    <?php if ($adminOnly && !$isAdminUser) { continue; } ?>
                    <li class="nav-item">
                        <a class="nav-link <?= adminIsActive($href, $exact, $currentPath) ? 'active' : '' ?>"
                           href="<?= $href ?>">
                            <i class="bi <?= $icon ?>"></i> <?= $label ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mt-lg-0">
                <span class="admin-user">
                    <i class="bi bi-person-circle"></i> <?= e($staffName) ?>
                    <span class="role-pill"><?= $isAdminUser ? 'Quản trị viên' : 'Nhân viên' ?></span>
                </span>
                <a class="btn btn-view btn-sm" href="/" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> Xem website
                </a>
                <a class="btn btn-view btn-sm" href="/admin/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Đăng xuất
                </a>
            </div>
        </div>
    </div>
</nav>
<?php flash_render(); ?>
