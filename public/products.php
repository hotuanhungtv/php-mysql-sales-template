<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

// 1. Lấy danh sách danh mục cho bộ lọc
$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categoryResult =$conn->query($sqlCategories);
$categories = [];
while ($category =$categoryResult->fetch_assoc()) {
    $categories[] =$category;
}
$categoryResult->free();

// 2. Khoảng giá của toàn bộ sản phẩm đang bán (để dựng thanh trượt)
$boundsResult = $conn->query("SELECT COALESCE(MAX(Price), 0) AS MaxPrice FROM products WHERE IsActive = 1");
$highestPrice = (int) ceil((float) $boundsResult->fetch_assoc()['MaxPrice']);
$boundsResult->free();

/* Bước nhảy của thanh trượt: ~100 nấc, làm tròn theo bậc 10 */
$priceStep = max(10000, (int) pow(10, max(0, (int) floor(log10(max(1, $highestPrice))) - 2)));
$priceCeiling = max($priceStep, (int) (ceil($highestPrice / $priceStep) * $priceStep));

// 3. Lọc và phân trang từ GET
$categoryID = isset($_GET['category'])
    ? (int)$_GET['category']
    : 0;
$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$minPrice = isset($_GET['min_price']) ? max(0, (int) $_GET['min_price']) : 0;
$maxPrice = isset($_GET['max_price']) ? (int) $_GET['max_price'] : $priceCeiling;
$maxPrice = min($maxPrice, $priceCeiling);
if ($maxPrice <= 0) {
    $maxPrice = $priceCeiling;
}
if ($minPrice > $maxPrice) {
    [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
}
$priceFiltered = $minPrice > 0 || $maxPrice < $priceCeiling;

$itemsPerPage = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {$page = 1;
}

// Điều kiện lọc dùng chung cho đếm và liệt kê
$where = ['p.CategoryID = c.CategoryID', 'p.IsActive = 1'];
$types = '';
$params = [];

if ($categoryID > 0) {
    $where[] = 'p.CategoryID = ?';
    $types .= 'i';
    $params[] = $categoryID;
}

if ($keyword !== '') {
    $where[] = '(p.ProductName LIKE ? OR p.ProductCode LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}

if ($priceFiltered) {
    $where[] = 'p.Price BETWEEN ? AND ?';
    $types .= 'ii';
    $params[] = $minPrice;
    $params[] = $maxPrice;
}

$whereSql = implode(' AND ', $where);

// 4. Đếm tổng số sản phẩm
$stmtCount = $conn->prepare("
    SELECT COUNT(*) AS TotalProducts
    FROM products p, categories c
    WHERE $whereSql
");

if ($params) {
    $stmtCount->bind_param($types, ...$params);
}

$stmtCount->execute();
$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();
$totalProducts = (int)$countRow['TotalProducts'];

$countResult->free();
$stmtCount->close();

$totalPages = (int) ceil($totalProducts / $itemsPerPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

// 5. Truy vấn danh sách sản phẩm phân trang (kèm điểm đánh giá trung bình)
require_once '/var/www/src/config/reviews.php';
reviews_ensure_table($conn);

$stmt = $conn->prepare("
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile,
        (SELECT COALESCE(AVG(r.Rating), 0) FROM reviews r
          WHERE r.ProductID = p.ProductID AND r.IsVisible = 1) AS AvgRating,
        (SELECT COUNT(*) FROM reviews r
          WHERE r.ProductID = p.ProductID AND r.IsVisible = 1) AS ReviewCount
    FROM products p, categories c
    WHERE $whereSql
    ORDER BY p.ProductID DESC
    LIMIT ? OFFSET ?
");

$listTypes = $types . 'ii';
$listParams = array_merge($params, [$itemsPerPage, $offset]);
$stmt->bind_param($listTypes, ...$listParams);

$stmt->execute();
$result = $stmt->get_result();

// Tham số giữ lại khi chuyển trang
$baseQuery = ['keyword' => $keyword, 'category' => $categoryID];
if ($priceFiltered) {
    $baseQuery['min_price'] = $minPrice;
    $baseQuery['max_price'] = $maxPrice;
}

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-4 py-lg-5">
    <div class="section-title">
        <h2>Sản phẩm</h2>
        <span class="text-muted">
            Tìm thấy <strong class="text-dark"><?= $totalProducts ?></strong> sản phẩm
        </span>
    </div>

    <form method="get" action="/products.php" class="filter-bar row g-3 mb-4">
        <div class="col-md-6 col-lg-6">
            <label for="keyword" class="form-label">Tìm sản phẩm</label>
            <input type="text" name="keyword" id="keyword" class="form-control"
                   value="<?= htmlspecialchars($keyword) ?>"
                   placeholder="Nhập tên hoặc mã sản phẩm">
        </div>

        <div class="col-md-6 col-lg-6">
            <label for="category" class="form-label">Danh mục</label>
            <select name="category" id="category" class="form-select">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['CategoryID'] ?>"
                        <?= $categoryID === (int) $category['CategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-lg-6">
            <div class="price-filter" id="priceFilter"
                 data-max="<?= $priceCeiling ?>" data-step="<?= $priceStep ?>">
                <label class="form-label">Khoảng giá</label>
                <div class="price-inputs">
                    <input type="text" inputmode="numeric" id="priceMinText" class="form-control"
                           aria-label="Giá thấp nhất" autocomplete="off">
                    <span class="price-dash"></span>
                    <input type="text" inputmode="numeric" id="priceMaxText" class="form-control"
                           aria-label="Giá cao nhất" autocomplete="off">
                </div>
                <div class="price-slider">
                    <div class="price-track"><div class="price-range" id="priceRange"></div></div>
                    <input type="range" id="priceMinRange" min="0" max="<?= $priceCeiling ?>"
                           step="<?= $priceStep ?>" value="<?= $minPrice ?>" aria-label="Kéo chọn giá thấp nhất">
                    <input type="range" id="priceMaxRange" min="0" max="<?= $priceCeiling ?>"
                           step="<?= $priceStep ?>" value="<?= $maxPrice ?>" aria-label="Kéo chọn giá cao nhất">
                </div>
                <input type="hidden" name="min_price" id="priceMinHidden" value="<?= $minPrice ?>">
                <input type="hidden" name="max_price" id="priceMaxHidden" value="<?= $maxPrice ?>">
            </div>
        </div>

        <div class="col-lg-6 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i> Tìm kiếm
            </button>
            <a href="/products.php" class="btn btn-outline-secondary">Xóa bộ lọc</a>
        </div>
    </form>

    <?php if ($result->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card product-card h-100">
                        <span class="product-cat"><?= htmlspecialchars($product['CategoryName']) ?></span>

                        <a href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>" class="product-thumb">
                            <?php if (!empty($product['ImageFile'])): ?>
                                <img src="/uploads/products/<?= htmlspecialchars($product['ImageFile']) ?>"
                                     alt="<?= htmlspecialchars($product['ProductName']) ?>">
                            <?php else: ?>
                                <i class="bi bi-image text-muted fs-1"></i>
                            <?php endif; ?>
                        </a>

                        <div class="card-body d-flex flex-column">
                            <h5 class="product-title"><?= htmlspecialchars($product['ProductName']) ?></h5>
                            <p class="text-muted small mb-2">
                                Mã: <?= htmlspecialchars($product['ProductCode']) ?>
                            </p>
                            <?php if ((int) $product['ReviewCount'] > 0): ?>
                                <div class="mb-2 d-flex align-items-center gap-1">
                                    <?= review_stars((float) $product['AvgRating']) ?>
                                    <small class="text-muted">
                                        <?= number_format((float) $product['AvgRating'], 1) ?>
                                        (<?= (int) $product['ReviewCount'] ?>)
                                    </small>
                                </div>
                            <?php endif; ?>
                            <div class="product-price mb-3">
                                <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                            </div>
                            <a href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                               class="btn btn-primary mt-auto">
                                <i class="bi bi-eye"></i> Xem chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Hiện chưa có sản phẩm nào để hiển thị.
        </div>
    <?php endif; ?>
</main>

<!-- Phân trang -->
<?php if ($totalPages > 1): ?>
    <nav class="mt-5" aria-label="Phân trang sản phẩm">
        <ul class="pagination justify-content-center flex-wrap">
            
            <?php
            $previousQuery = http_build_query($baseQuery + ['page' => max(1, $page - 1)]);
            ?>

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="/products.php?<?= $previousQuery ?>">
                    &laquo; Trước
                </a>
            </li>

            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                <?php
                $query = http_build_query($baseQuery + ['page' => $pageNumber]);
                ?>

                <li class="page-item <?= $pageNumber ===$page ? 'active' : '' ?>">
                    <a class="page-link" href="/products.php?<?= $query ?>">
                        <?= $pageNumber ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php
            $nextQuery = http_build_query($baseQuery + ['page' => min($totalPages, $page + 1)]);
            ?>

            <li class="page-item <?= $page >=$totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="/products.php?<?= $nextQuery ?>">
                    Sau &raquo;
                </a>
            </li>

        </ul>
    </nav>
<?php endif; ?>

<script>
(function () {
    var box = document.getElementById('priceFilter');
    if (!box) { return; }

    var MAX = parseInt(box.dataset.max, 10);
    var STEP = parseInt(box.dataset.step, 10);
    var $ = function (id) { return document.getElementById(id); };
    var minR = $('priceMinRange'), maxR = $('priceMaxRange');
    var minT = $('priceMinText'), maxT = $('priceMaxText');
    var minH = $('priceMinHidden'), maxH = $('priceMaxHidden');
    var range = $('priceRange');

    function fmt(n) { return Number(n).toLocaleString('vi-VN') + 'đ'; }
    function digits(t) { return parseInt(String(t).replace(/\D/g, ''), 10) || 0; }
    function clamp(n, lo, hi) { return Math.min(hi, Math.max(lo, n)); }

    function paint() {
        var lo = parseInt(minR.value, 10), hi = parseInt(maxR.value, 10);
        range.style.left = (lo / MAX * 100) + '%';
        range.style.right = (100 - hi / MAX * 100) + '%';
        /* Tay kéo nào đang chồng nhau thì đưa tay "thấp" lên trên để vẫn kéo được */
        minR.style.zIndex = lo >= MAX - STEP ? 5 : 3;
    }

    function sync(fromText) {
        var lo, hi;
        if (fromText) {
            lo = clamp(digits(minT.value), 0, MAX);
            hi = clamp(digits(maxT.value) || MAX, 0, MAX);
        } else {
            lo = parseInt(minR.value, 10);
            hi = parseInt(maxR.value, 10);
        }
        if (lo > hi) { var t = lo; lo = hi; hi = t; }
        minR.value = lo; maxR.value = hi;
        minT.value = fmt(lo); maxT.value = fmt(hi);
        /* Bằng mặc định thì không gửi tham số, URL cho gọn */
        minH.disabled = (lo === 0 && hi === MAX);
        maxH.disabled = minH.disabled;
        minH.value = lo; maxH.value = hi;
        paint();
    }

    function keepOrder(changed) {
        var lo = parseInt(minR.value, 10), hi = parseInt(maxR.value, 10);
        if (lo > hi) {
            if (changed === 'min') { minR.value = hi; } else { maxR.value = lo; }
        }
        sync(false);
    }

    minR.addEventListener('input', function () { keepOrder('min'); });
    maxR.addEventListener('input', function () { keepOrder('max'); });
    minT.addEventListener('change', function () { sync(true); });
    maxT.addEventListener('change', function () { sync(true); });
    [minT, maxT].forEach(function (el) {
        el.addEventListener('focus', function () { el.value = digits(el.value) || ''; el.select(); });
        el.addEventListener('blur', function () { sync(true); });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); sync(true); el.form.submit(); }
        });
    });

    /* Thả tay kéo là lọc luôn */
    [minR, maxR].forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    sync(false);
})();
</script>

<?php
$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';
?>