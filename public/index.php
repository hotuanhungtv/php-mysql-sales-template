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

<section class="bg-primary text-white py-5">
    <div class="container text-center py-4">
        <h1 class="display-5 fw-bold">Thiết bị điện tử chính hãng</h1>
        <p class="lead mb-4">
            Điện thoại, máy tính và phụ kiện giá tốt, giao hàng tận nơi.
        </p>
        <form action="/products.php" method="get"
              class="row g-2 justify-content-center">
            <div class="col-md-5">
                <input type="text" name="keyword" class="form-control form-control-lg"
                       placeholder="Bạn muốn tìm sản phẩm nào?">
            </div>
            <div class="col-auto">
                <button class="btn btn-warning btn-lg">Tìm kiếm</button>
            </div>
        </form>
    </div>
</section>

<main class="container py-5">

    <h2 class="h4 mb-3">Danh mục sản phẩm</h2>
    <div class="row g-3 mb-5">
        <?php foreach ($categories as $cat): ?>
            <div class="col-md-4">
                <a class="text-decoration-none"
                   href="/products.php?category=<?= (int) $cat['CategoryID'] ?>">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h3 class="h5 text-dark"><?= e($cat['CategoryName']) ?></h3>
                            <p class="text-muted small mb-2">
                                <?= e($cat['Description'] ?? '') ?>
                            </p>
                            <span class="badge bg-primary">
                                <?= (int) $cat['total'] ?> sản phẩm
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Sản phẩm mới</h2>
        <a href="/products.php">Xem tất cả →</a>
    </div>

    <div class="row g-4">
        <?php foreach ($products as $p): ?>
            <div class="col-6 col-md-3">
                <div class="card h-100 shadow-sm">

                    <?php if (!empty($p['ImageFile'])): ?>
                        <img src="/uploads/products/<?= e($p['ImageFile']) ?>"
                             class="card-img-top p-3"
                             style="height: 200px; object-fit: contain;"
                             alt="<?= e($p['ProductName']) ?>">
                    <?php else: ?>
                        <div class="bg-light text-center text-muted py-5">Chưa có ảnh</div>
                    <?php endif; ?>

                    <div class="card-body d-flex flex-column">
                        <h3 class="h6"><?= e($p['ProductName']) ?></h3>
                        <p class="text-danger fw-bold mb-2"><?= money($p['Price']) ?></p>

                        <?php if ((int) $p['StockQuantity'] <= 0): ?>
                            <span class="badge bg-secondary mb-2 align-self-start">
                                Hết hàng
                            </span>
                        <?php endif; ?>

                        <a class="btn btn-outline-primary btn-sm mt-auto"
                           href="/product-detail.php?id=<?= (int) $p['ProductID'] ?>">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$products): ?>
            <p class="text-muted">Chưa có sản phẩm nào.</p>
        <?php endif; ?>
    </div>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();
