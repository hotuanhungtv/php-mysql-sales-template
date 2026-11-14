<?php
$cartCount    = array_sum($_SESSION['cart'] ?? []);
$isLoggedIn   = isset($_SESSION['customer_id']);
$customerName = $_SESSION['customer_name'] ?? '';
?>

<nav class="navbar navbar-expand-lg site-nav">
    <div class="container">

        <a class="navbar-brand" href="/">
            <span class="brand-icon"><i class="bi bi-bag-heart-fill"></i></span>
            Sales Shop
        </a>

        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#frontendNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="frontendNavbar">

            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/"><i class="bi bi-house-door"></i> Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/products.php"><i class="bi bi-grid"></i> Sản phẩm</a>
                </li>
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/orders.php">
                            <i class="bi bi-receipt"></i> Đơn hàng của tôi
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mt-lg-0">

                <a class="btn btn-outline-secondary btn-sm cart-btn" href="/cart.php">
                    <i class="bi bi-cart3"></i> Giỏ hàng
                    <?php if ((int) $cartCount > 0): ?>
                        <span class="cart-badge"><?= (int) $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <?php if (!empty($_SESSION['staff_id'])): ?>
                    <a class="btn btn-warning btn-sm" href="/admin/">
                        <i class="bi bi-gear"></i> Quản trị
                    </a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <a class="user-chip text-decoration-none" href="/profile.php">
                        <i class="bi bi-person-circle"></i> <?= e($customerName) ?>
                    </a>
                    <a class="btn btn-primary btn-sm" href="/logout.php">
                        Đăng xuất
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-secondary btn-sm" href="/register.php">
                        Đăng ký
                    </a>
                    <a class="btn btn-primary btn-sm" href="/login.php">
                        Đăng nhập
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</nav>
<?php flash_render(); ?>
