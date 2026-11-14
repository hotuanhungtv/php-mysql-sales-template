<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Trang chủ';

/* Danh mục + số sản phẩm */
$categories = $conn->query("
    SELECT c.CategoryID, c.CategoryName, c.Description,
           COUNT(p.ProductID) AS total
    FROM categories c
    LEFT JOIN products p ON p.CategoryID = c.CategoryID AND p.IsActive = 1
    GROUP BY c.CategoryID, c.CategoryName, c.Description
    ORDER BY c.CategoryName
")->fetch_all(MYSQLI_ASSOC);

/* Sản phẩm mới nhất */
$products = $conn->query("
    SELECT p.ProductID, p.ProductName, p.Price, p.StockQuantity,
           (SELECT pi.ImageFile FROM product_images pi
            WHERE pi.ProductID = p.ProductID AND pi.IsPrimary = 1
            LIMIT 1) AS ImageFile
    FROM products p
    WHERE p.IsActive = 1
    ORDER BY p.ProductID DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>
<div class="container pt-4 pt-lg-5">
    <section class="hero mb-5">
        <span class="badge rounded-pill bg-light text-dark mb-3 px-3 py-2">
            <i class="bi bi-lightning-charge-fill text-warning"></i> Ưu đãi công nghệ mỗi ngày
        </span>
        <h1 class="mb-3">Thiết bị điện tử chính hãng,<br>giá tốt, giao tận nơi</h1>
        <p class="mb-4">
            Điện thoại, máy tính và phụ kiện giá tốt, giao hàng tận nơi.
        </p>
        <form action="/products.php" method="get" class="hero-search d-flex flex-wrap gap-2">
            <input type="text" name="keyword" class="form-control flex-grow-1"
                   placeholder="Bạn muốn tìm sản phẩm nào?">
            <button class="btn btn-light btn-lg"><i class="bi bi-search"></i> Tìm kiếm</button>
        </form>
    </section>
</div>

<main class="container pb-4">

    <section class="row g-3 mb-5">
        <div class="col-6 col-lg-3">
            <div class="feature"><div class="feature-icon"><i class="bi bi-truck"></i></div>
                <div><h6>Giao hàng nhanh</h6><small>Toàn quốc</small></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="feature"><div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                <div><h6>Chính hãng</h6><small>Bảo hành đầy đủ</small></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="feature"><div class="feature-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div><h6>Đổi trả dễ dàng</h6><small>Trong 7 ngày</small></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="feature"><div class="feature-icon"><i class="bi bi-headset"></i></div>
                <div><h6>Hỗ trợ 24/7</h6><small>Luôn sẵn sàng</small></div></div>
        </div>
    </section>

    <?php if ($categories): ?>
        <section class="mb-5">
            <div class="section-title"><h2>Danh mục sản phẩm</h2></div>
            <div class="row g-3">
                <?php foreach ($categories as $cat): ?>
                    <div class="col-6 col-md-4">
                        <a class="cat-tile" href="/products.php?category=<?= (int) $cat['CategoryID'] ?>">
                            <i class="bi bi-box-seam"></i>
                            <span><?= e($cat['CategoryName']) ?></span>
                            <?php if (!empty($cat['Description'])): ?>
                                <div class="text-muted small mt-1"><?= e($cat['Description']) ?></div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-primary"><?= (int) $cat['total'] ?> sản phẩm</span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section>
        <div class="section-title">
            <h2>Sản phẩm mới</h2>
            <a href="/products.php" class="text-decoration-none fw-semibold">
                Xem tất cả <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <div class="col-6 col-lg-3">
                    <div class="card product-card h-100">
                        <?php if ((int) $p['StockQuantity'] <= 0): ?>
                            <span class="stock-out">Hết hàng</span>
                        <?php endif; ?>

                        <a href="/product-detail.php?id=<?= (int) $p['ProductID'] ?>" class="product-thumb">
                            <?php if (!empty($p['ImageFile'])): ?>
                                <img src="/uploads/products/<?= e($p['ImageFile']) ?>"
                                     alt="<?= e($p['ProductName']) ?>">
                            <?php else: ?>
                                <i class="bi bi-image text-muted fs-1"></i>
                            <?php endif; ?>
                        </a>

                        <div class="card-body d-flex flex-column">
                            <h3 class="product-title"><?= e($p['ProductName']) ?></h3>
                            <div class="product-price mb-3"><?= money($p['Price']) ?></div>
                            <a class="btn btn-outline-primary btn-sm mt-auto"
                               href="/product-detail.php?id=<?= (int) $p['ProductID'] ?>">
                                <i class="bi bi-eye"></i> Xem chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$products): ?>
                <p class="text-muted">Chưa có sản phẩm nào.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();
