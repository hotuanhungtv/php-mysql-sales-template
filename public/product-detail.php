<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';
//kiểm tra sản phẩm tồn tại
$productID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productID <= 0) {
    header('Location: /products.php');
    exit;
}

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Description,
        p.Unit,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        s.SupplierName

    FROM
        products p,
        categories c,
        suppliers s

    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
        AND p.ProductID = ?
        AND p.IsActive = 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    'i',
    $productID
);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$result->free();
$stmt->close();
//Xử lý thêm sản phẩm vào giỏ hàng
$cartMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_to_cart'])) {

    $quantity = isset($_POST['quantity'])
        ? (int) $_POST['quantity']
        : 1;

    if ($quantity < 1) {
        $quantity = 1;
    }

    $stockQuantity = (int) $product['StockQuantity'];

    if ($stockQuantity <= 0) {

        $cartMessage = 'Sản phẩm hiện đã hết hàng.';

    } else {

        $currentQuantity =
            $_SESSION['cart'][$productID] ?? 0;

        $newQuantity =
            $currentQuantity + $quantity;

        if ($newQuantity > $stockQuantity) {
            $newQuantity = $stockQuantity;
        }

        $_SESSION['cart'][$productID] =
            $newQuantity;

        $cartMessage =
            'Đã thêm sản phẩm vào giỏ hàng.';
    }
}
//Truy vấn hình ảnh
if (!$product) {
    header('Location: /products.php');
    exit;
}

$sqlImages = "
    SELECT ImageFile, IsPrimary
    FROM product_images
    WHERE ProductID = ?
    ORDER BY IsPrimary DESC, SortOrder ASC, ProductImageID ASC
";

$stmtImages = $conn->prepare($sqlImages);

$stmtImages->bind_param(
    'i',
    $productID
);

$stmtImages->execute();

$imageResult = $stmtImages->get_result();

$images = [];

while ($image = $imageResult->fetch_assoc()) {
    $images[] = $image;
}

$imageResult->free();
$stmtImages->close();

$pageTitle = $product['ProductName'] ?? 'Chi tiết sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>


<main class="container py-5">

    <div class="mb-4">

        <a
            href="/products.php"
            class="text-decoration-none"
        >
            <i class="bi bi-arrow-left"></i> Quay lại danh sách sản phẩm
        </a>

    </div>

    <div class="row g-5">

        <div class="col-lg-6">

            <?php if (!empty($images)): ?>
                <?php
                $galleryUrls = array_map(
                    static fn(array $im): string => '/uploads/products/' . rawurlencode($im['ImageFile']),
                    $images
                );
                $galleryTotal = count($galleryUrls);
                ?>
                <div class="gallery" id="gallery">

                    <div class="gallery-main" id="galleryMain" title="Bấm để phóng to">
                        <img
                            id="galleryImg"
                            src="<?= htmlspecialchars($galleryUrls[0]) ?>"
                            alt="<?= htmlspecialchars($product['ProductName']) ?>"
                        >

                        <?php if ($galleryTotal > 1): ?>
                            <button type="button" class="gallery-nav prev" id="galleryPrev" aria-label="Ảnh trước">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="gallery-nav next" id="galleryNext" aria-label="Ảnh sau">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        <?php endif; ?>

                        <span class="gallery-count" id="galleryCount">1/<?= $galleryTotal ?></span>

                        <button type="button" class="gallery-zoom" id="galleryZoom" aria-label="Phóng to ảnh">
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </div>

                    <?php if ($galleryTotal > 1): ?>
                        <div class="gallery-thumbs" id="galleryThumbs">
                            <?php foreach ($galleryUrls as $i => $url): ?>
                                <button
                                    type="button"
                                    class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>"
                                    data-index="<?= $i ?>"
                                    aria-label="Xem ảnh <?= $i + 1 ?>"
                                >
                                    <img src="<?= htmlspecialchars($url) ?>" alt="">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="lightbox" id="lightbox" hidden>
                    <div class="lb-dialog" id="lbDialog">
                        <button type="button" class="lb-close" id="lbClose" aria-label="Đóng">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php if ($galleryTotal > 1): ?>
                            <button type="button" class="lb-nav lb-prev" id="lbPrev" aria-label="Ảnh trước">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="lb-nav lb-next" id="lbNext" aria-label="Ảnh sau">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        <?php endif; ?>
                        <img id="lbImg" src="" alt="<?= htmlspecialchars($product['ProductName']) ?>">
                    </div>

                    <?php if ($galleryTotal > 1): ?>
                        <div class="lb-thumbs" id="lbThumbs">
                            <?php foreach ($galleryUrls as $i => $url): ?>
                                <button type="button" class="lb-thumb" data-index="<?= $i ?>"
                                        aria-label="Xem ảnh <?= $i + 1 ?>">
                                    <img src="<?= htmlspecialchars($url) ?>" alt="">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <script>
                (function () {
                    var urls = <?= json_encode($galleryUrls, JSON_UNESCAPED_SLASHES) ?>;
                    var total = urls.length;
                    var cur = 0;

                    var $ = function (id) { return document.getElementById(id); };
                    var img = $('galleryImg'), count = $('galleryCount'), box = $('galleryThumbs');
                    var thumbs = box ? Array.prototype.slice.call(box.querySelectorAll('.gallery-thumb')) : [];
                    var lb = $('lightbox'), lbImg = $('lbImg'), lbBox = $('lbThumbs');
                    var lbThumbs = lbBox ? Array.prototype.slice.call(lbBox.querySelectorAll('.lb-thumb')) : [];

                    function centerThumb(container, el) {
                        if (!container || !el) { return; }
                        container.scrollTo({
                            left: el.offsetLeft - container.clientWidth / 2 + el.clientWidth / 2,
                            behavior: 'smooth'
                        });
                    }

                    function show(i) {
                        cur = (i + total) % total;

                        img.src = urls[cur];
                        count.textContent = (cur + 1) + '/' + total;
                        thumbs.forEach(function (t, k) { t.classList.toggle('active', k === cur); });
                        centerThumb(box, thumbs[cur]);

                        if (!lb.hidden) {
                            lbImg.src = urls[cur];
                            lbThumbs.forEach(function (t, k) { t.classList.toggle('active', k === cur); });
                            centerThumb(lbBox, lbThumbs[cur]);
                        }
                    }

                    function openLightbox() {
                        lb.hidden = false;
                        document.body.style.overflow = 'hidden';
                        show(cur);
                    }
                    function closeLightbox() {
                        lb.hidden = true;
                        document.body.style.overflow = '';
                    }

                    /* Ảnh nhỏ bên ngoài: bấm để đổi ảnh lớn */
                    thumbs.forEach(function (t) {
                        t.addEventListener('click', function () { show(parseInt(t.dataset.index, 10)); });
                    });

                    /* Mũi tên trên ảnh lớn */
                    if ($('galleryPrev')) {
                        $('galleryPrev').addEventListener('click', function (e) { e.stopPropagation(); show(cur - 1); });
                        $('galleryNext').addEventListener('click', function (e) { e.stopPropagation(); show(cur + 1); });
                    }

                    /* Bấm vào ảnh lớn -> mở cửa sổ xem chi tiết */
                    $('galleryMain').addEventListener('click', openLightbox);
                    $('galleryZoom').addEventListener('click', function (e) { e.stopPropagation(); openLightbox(); });

                    /* Trong cửa sổ xem chi tiết */
                    $('lbClose').addEventListener('click', closeLightbox);
                    lb.addEventListener('click', function (e) {
                        if (!e.target.closest('.lb-dialog') && !e.target.closest('.lb-thumbs')) { closeLightbox(); }
                    });
                    if ($('lbPrev')) {
                        $('lbPrev').addEventListener('click', function () { show(cur - 1); });
                        $('lbNext').addEventListener('click', function () { show(cur + 1); });
                    }
                    lbThumbs.forEach(function (t) {
                        t.addEventListener('click', function () { show(parseInt(t.dataset.index, 10)); });
                    });

                    /* Bàn phím: mũi tên trái/phải, Esc */
                    document.addEventListener('keydown', function (e) {
                        var inGallery = !lb.hidden || (document.activeElement && $('gallery').contains(document.activeElement));
                        if (!inGallery) { return; }
                        if (e.key === 'ArrowLeft') { show(cur - 1); }
                        else if (e.key === 'ArrowRight') { show(cur + 1); }
                        else if (e.key === 'Escape' && !lb.hidden) { closeLightbox(); }
                    });

                    /* Vuốt ngang trên điện thoại */
                    function swipe(el) {
                        var x0 = null;
                        el.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
                        el.addEventListener('touchend', function (e) {
                            if (x0 === null || total < 2) { return; }
                            var dx = e.changedTouches[0].clientX - x0;
                            if (Math.abs(dx) > 40) { show(dx < 0 ? cur + 1 : cur - 1); }
                            x0 = null;
                        }, { passive: true });
                    }
                    swipe($('galleryMain'));
                    swipe($('lbDialog'));
                })();
                </script>
            <?php else: ?>
                <div class="detail-main-img text-muted">
                    Chưa có hình ảnh
                </div>
            <?php endif; ?>

        </div>

        <div class="col-lg-6">

            <p class="text-muted mb-2">
                <?=
                    htmlspecialchars(
                        $product['CategoryName']
                    )
                ?>
            </p>

            <h1 class="mb-3">
                <?=
                    htmlspecialchars(
                        $product['ProductName']
                    )
                ?>
            </h1>

            <p class="text-muted">
                Mã sản phẩm:
                <?=
                    htmlspecialchars(
                        $product['ProductCode']
                    )
                ?>
            </p>

            <p class="fs-2 fw-bold product-price">
                <?=
                    number_format(
                        (float) $product['Price'],
                        0,
                        ',',
                        '.'
                    )
                ?> đ
            </p>

            <hr>

            <dl class="row">

                <dt class="col-sm-4">
                    Danh mục
                </dt>

                <dd class="col-sm-8">
                    <?=
                        htmlspecialchars(
                            $product['CategoryName']
                        )
                    ?>
                </dd>

                <dt class="col-sm-4">
                    Nhà cung cấp
                </dt>

                <dd class="col-sm-8">
                    <?=
                        htmlspecialchars(
                            $product['SupplierName']
                        )
                    ?>
                </dd>

                <dt class="col-sm-4">
                    Đơn vị tính
                </dt>

                <dd class="col-sm-8">
                    <?=
                        htmlspecialchars(
                            $product['Unit'] ?? ''
                        )
                    ?>
                </dd>

                <dt class="col-sm-4">
                    Tồn kho
                </dt>

                <dd class="col-sm-8">
                    <?= (int) $product['StockQuantity'] ?>
                </dd>

            </dl>
<!-- Giao diện thêm sp-->
            <?php if ($cartMessage !== ''): ?>

                <div class="alert alert-info">
                    <?= htmlspecialchars($cartMessage) ?>
                </div>

            <?php endif; ?>

            <?php if ((int) $product['StockQuantity'] > 0): ?>

                <form method="post" class="mb-4">
<?= csrf_field() ?>

                    <div class="row g-3 align-items-end">

                        <div class="col-auto">

                            <label
                                for="quantity"
                                class="form-label"
                            >
                                Số lượng
                            </label>

                            <input
                                type="number"
                                name="quantity"
                                id="quantity"
                                class="form-control"
                                value="1"
                                min="1"
                                max="<?= (int) $product['StockQuantity'] ?>"
                                style="width: 100px;"
                            >

                        </div>

                        <div class="col-auto">

                            <button
                                type="submit"
                                name="add_to_cart"
                                class="btn btn-primary btn-lg"
                            >
                                <i class="bi bi-cart-plus"></i> Thêm vào giỏ hàng
                            </button>

                        </div>

                    </div>

                </form>

            <?php else: ?>

                <div class="alert alert-warning">
                    Sản phẩm hiện đã hết hàng.
                </div>

            <?php endif; ?>

<!-- Mô tả sản phẩm-->
            <?php if (!empty($product['Description'])): ?>

                <hr>

                <h5>Mô tả sản phẩm</h5>

                <p>
                    <?=
                        nl2br(
                            htmlspecialchars(
                                $product['Description']
                            )
                        )
                    ?>
                </p>

            <?php endif; ?>

        </div>

    </div>

</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';