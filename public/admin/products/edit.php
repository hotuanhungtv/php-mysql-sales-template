<?php
require_once '/var/www/src/config/auth.php';
require_admin();


$pageTitle = 'Sửa sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

// 1. Lấy ProductID từ URL
$productID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productID <= 0) {
    header('Location: /admin/products/');
    exit;
}

// 2. Lấy thông tin sản phẩm hiện tại từ CSDL
$sqlProduct = "SELECT * FROM products WHERE ProductID = ?";
$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();
$resultProduct = $stmtProduct->get_result();
$product = $resultProduct->fetch_assoc();
$stmtProduct->close();

if (!$product) {
    header('Location: /admin/products/');
    exit;
}

// 3. Lấy danh sách Categories
$sqlCategories = "SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName";
$categories = $conn->query($sqlCategories);

// 4. Lấy danh sách Suppliers
$sqlSuppliers = "SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName";
$suppliers = $conn->query($sqlSuppliers);

// 4b. Ảnh hiện có của sản phẩm (ảnh chính đứng đầu)
const MAX_IMAGES = 8;
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

function load_product_images(mysqli $conn, int $productID): array
{
    $stmt = $conn->prepare(
        'SELECT ProductImageID, ImageFile, IsPrimary, SortOrder
         FROM product_images
         WHERE ProductID = ?
         ORDER BY IsPrimary DESC, SortOrder ASC, ProductImageID ASC'
    );
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

$images = load_product_images($conn, $productID);

// 5. Xử lý khi người dùng submit Form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation kiểm tra dữ liệu
    if ($productCode === '') {
        $error = 'Mã sản phẩm không được để trống.';
    } elseif ($productName === '') {
        $error = 'Tên sản phẩm không được để trống.';
    } elseif ($price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';
    } elseif ($stockQuantity < 0) {
        $error = 'Số lượng tồn kho không hợp lệ.';
    } elseif ($categoryID <= 0) {
        $error = 'Vui lòng chọn danh mục.';
    } elseif ($supplierID <= 0) {
        $error = 'Vui lòng chọn nhà cung cấp.';
    } else {
        // Kiểm tra xem Mã sản phẩm có bị trùng với sản phẩm KHÁC hay không
        $sqlCheck = "SELECT ProductID FROM products WHERE ProductCode = ? AND ProductID != ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param('si', $productCode, $productID);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            $error = "Mã sản phẩm '$productCode' đã được sử dụng cho sản phẩm khác.";
            $stmtCheck->close();
        } else {
            $stmtCheck->close();

            // ----- Kiểm tra ảnh trước khi ghi bất kỳ thứ gì -----
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            $existingIds = array_map(
                static fn(array $im): int => (int) $im['ProductImageID'],
                $images
            );

            // Ảnh cần xóa (chỉ nhận ID thuộc về sản phẩm này)
            $deleteIds = array_values(array_intersect(
                $existingIds,
                array_map('intval', (array) ($_POST['delete_images'] ?? []))
            ));

            // Ảnh được chọn làm ảnh chính (phải còn tồn tại sau khi xóa)
            $primaryId = (int) ($_POST['primary_image'] ?? 0);
            if (!in_array($primaryId, $existingIds, true)
                || in_array($primaryId, $deleteIds, true)) {
                $primaryId = 0;
            }

            // Ảnh mới tải lên
            $newFiles   = [];
            $imageErrors = [];
            $upload     = $_FILES['new_images'] ?? null;

            if ($upload && is_array($upload['name'])) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                foreach ($upload['name'] as $i => $fileName) {
                    $code = $upload['error'][$i];

                    if ($code === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($code !== UPLOAD_ERR_OK) {
                        $imageErrors[] = 'Ảnh "' . $fileName . '" tải lên thất bại (có thể quá nặng).';
                        continue;
                    }
                    if ($upload['size'][$i] > MAX_IMAGE_BYTES) {
                        $imageErrors[] = 'Ảnh "' . $fileName . '" vượt quá 2MB.';
                        continue;
                    }

                    $mime = $finfo->file($upload['tmp_name'][$i]);
                    if (!isset($allowedTypes[$mime])) {
                        $imageErrors[] = 'Ảnh "' . $fileName . '" không đúng định dạng (chỉ JPG, PNG, WebP).';
                        continue;
                    }

                    $newFiles[] = [$upload['tmp_name'][$i], $allowedTypes[$mime]];
                }
            }

            $afterCount = count($existingIds) - count($deleteIds) + count($newFiles);
            if ($afterCount > MAX_IMAGES) {
                $imageErrors[] = 'Mỗi sản phẩm chỉ có tối đa ' . MAX_IMAGES
                    . ' ảnh (sau khi cập nhật sẽ là ' . $afterCount . ' ảnh).';
            }

            if (!empty($imageErrors)) {
                $error = implode(' ', $imageErrors);
            } else {
                $uploadedPaths = [];   // file mới đã lưu (để xóa nếu lỗi)
                $filesToDelete = [];   // file cũ cần xóa sau khi commit
                $uploadDir     = '/var/www/html/uploads/products/';

                try {
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $conn->begin_transaction();

                    // 1. Cập nhật thông tin sản phẩm
                    $stmt = $conn->prepare("
                        UPDATE products
                        SET
                            ProductCode = ?,
                            ProductName = ?,
                            Description = ?,
                            Unit = ?,
                            Price = ?,
                            StockQuantity = ?,
                            IsActive = ?,
                            SupplierID = ?,
                            CategoryID = ?
                        WHERE ProductID = ?
                    ");

                    // s: string, d: double/float, i: integer (10 tham số)
                    $stmt->bind_param(
                        'ssssdiiiii',
                        $productCode,
                        $productName,
                        $description,
                        $unit,
                        $price,
                        $stockQuantity,
                        $isActive,
                        $supplierID,
                        $categoryID,
                        $productID
                    );
                    $stmt->execute();
                    $stmt->close();

                    // 2. Xóa các ảnh được đánh dấu
                    if (!empty($deleteIds)) {
                        foreach ($images as $im) {
                            if (in_array((int) $im['ProductImageID'], $deleteIds, true)) {
                                $filesToDelete[] = $im['ImageFile'];
                            }
                        }

                        $stmtDel = $conn->prepare(
                            'DELETE FROM product_images
                             WHERE ProductImageID = ? AND ProductID = ?'
                        );
                        foreach ($deleteIds as $imageId) {
                            $stmtDel->bind_param('ii', $imageId, $productID);
                            $stmtDel->execute();
                        }
                        $stmtDel->close();
                    }

                    // 3. Thêm ảnh mới
                    if (!empty($newFiles)) {
                        $stmtAdd = $conn->prepare(
                            'INSERT INTO product_images
                                (ProductID, ImageFile, IsPrimary, SortOrder, AltText)
                             VALUES (?, ?, 0, ?, ?)'
                        );

                        foreach ($newFiles as $k => [$tmpPath, $extension]) {
                            $newName = 'product-' . $productID . '-'
                                . bin2hex(random_bytes(6)) . '.' . $extension;
                            $destination = $uploadDir . $newName;

                            if (!move_uploaded_file($tmpPath, $destination)) {
                                throw new Exception('Không thể lưu file ảnh mới.');
                            }
                            $uploadedPaths[] = $destination;

                            $sortTemp = 900 + $k;   // tạm, sẽ được đánh số lại ở bước 4
                            $altText  = $productName . ' - Ảnh mới';
                            $stmtAdd->bind_param('isis', $productID, $newName, $sortTemp, $altText);
                            $stmtAdd->execute();
                        }
                        $stmtAdd->close();
                    }

                    // 4. Đánh số lại thứ tự và đặt ảnh chính
                    $stmtIds = $conn->prepare(
                        'SELECT ProductImageID
                         FROM product_images
                         WHERE ProductID = ?
                         ORDER BY SortOrder ASC, ProductImageID ASC'
                    );
                    $stmtIds->bind_param('i', $productID);
                    $stmtIds->execute();
                    $allIds = array_map(
                        'intval',
                        array_column($stmtIds->get_result()->fetch_all(MYSQLI_ASSOC), 'ProductImageID')
                    );
                    $stmtIds->close();

                    if ($primaryId === 0) {
                        // Không chọn ảnh chính mới: giữ ảnh chính cũ nếu còn
                        foreach ($images as $im) {
                            $imId = (int) $im['ProductImageID'];
                            if ((int) $im['IsPrimary'] === 1 && !in_array($imId, $deleteIds, true)) {
                                $primaryId = $imId;
                                break;
                            }
                        }
                    }
                    if ($primaryId === 0 && !empty($allIds)) {
                        $primaryId = $allIds[0];
                    }

                    $ordered = $allIds;
                    if ($primaryId > 0) {
                        $ordered = array_merge(
                            [$primaryId],
                            array_values(array_diff($allIds, [$primaryId]))
                        );
                    }

                    $stmtOrder = $conn->prepare(
                        'UPDATE product_images
                         SET SortOrder = ?, IsPrimary = ?
                         WHERE ProductImageID = ? AND ProductID = ?'
                    );
                    foreach ($ordered as $pos => $imageId) {
                        $sortOrder = $pos + 1;
                        $isPrimary = ($imageId === $primaryId) ? 1 : 0;
                        $stmtOrder->bind_param('iiii', $sortOrder, $isPrimary, $imageId, $productID);
                        $stmtOrder->execute();
                    }
                    $stmtOrder->close();

                    $conn->commit();

                    // 5. Xóa file ảnh cũ khỏi ổ đĩa (nếu không sản phẩm nào khác dùng chung)
                    foreach ($filesToDelete as $file) {
                        $stmtShared = $conn->prepare(
                            'SELECT COUNT(*) AS total FROM product_images WHERE ImageFile = ?'
                        );
                        $stmtShared->bind_param('s', $file);
                        $stmtShared->execute();
                        $shared = (int) $stmtShared->get_result()->fetch_assoc()['total'];
                        $stmtShared->close();

                        $path = $uploadDir . basename($file);
                        if ($shared === 0 && is_file($path)) {
                            @unlink($path);
                        }
                    }

                    flash_set('success', 'Đã cập nhật sản phẩm.');
                    $conn->close();
                    header('Location: /admin/products/');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();

                    foreach ($uploadedPaths as $path) {
                        if (is_file($path)) {
                            @unlink($path);
                        }
                    }

                    $error = ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1062)
                        ? "Mã sản phẩm '$productCode' đã được sử dụng cho sản phẩm khác."
                        : 'Không thể cập nhật sản phẩm: ' . $e->getMessage();
                }
            }
        }
    }
}

// Xử lý giá trị được chọn cho <select>
$selectedCategoryID = $_POST['category_id'] ?? $product['CategoryID'];
$selectedSupplierID = $_POST['supplier_id'] ?? $product['SupplierID'];

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="mb-3">Sửa sản phẩm</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
<?= csrf_field() ?>
                <div class="mb-3">
                    <label for="product_code" class="form-label">Mã sản phẩm (*)</label>
                    <input type="text" class="form-control" id="product_code" name="product_code" 
                           value="<?= htmlspecialchars($_POST['product_code'] ?? $product['ProductCode']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="product_name" class="form-label">Tên sản phẩm (*)</label>
                    <input type="text" class="form-control" id="product_name" name="product_name" 
                           value="<?= htmlspecialchars($_POST['product_name'] ?? $product['ProductName']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Mô tả</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? $product['Description'] ?? '') ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="unit" class="form-label">Đơn vị tính</label>
                        <input type="text" class="form-control" id="unit" name="unit" 
                               value="<?= htmlspecialchars($_POST['unit'] ?? $product['Unit'] ?? '') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="price" class="form-label">Giá (VNĐ) (*)</label>
                        <input type="number" step="1000" class="form-control" id="price" name="price" 
                               value="<?= htmlspecialchars($_POST['price'] ?? $product['Price']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="stock_quantity" class="form-label">Số lượng tồn kho (*)</label>
                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                               value="<?= htmlspecialchars($_POST['stock_quantity'] ?? $product['StockQuantity']) ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">Danh mục (*)</label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <option value="<?= $category['CategoryID'] ?>" 
                                    <?= ($selectedCategoryID == $category['CategoryID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['CategoryName']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="supplier_id" class="form-label">Nhà cung cấp (*)</label>
                    <select name="supplier_id" id="supplier_id" class="form-select" required>
                        <option value="">-- Chọn nhà cung cấp --</option>
                        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $supplier['SupplierID'] ?>" 
                                <?= ($selectedSupplierID == $supplier['SupplierID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($supplier['SupplierName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-check mb-3">
                    <?php 
                        $isChecked = isset($_POST['is_active']) 
                            ? true 
                            : ($_SERVER['REQUEST_METHOD'] === 'GET' ? (int)$product['IsActive'] === 1 : false);
                    ?>
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $isChecked ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">
                        Đang kinh doanh
                    </label>
                </div>

                <!-- ===== Quản lý ảnh sản phẩm ===== -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold mb-0">Ảnh sản phẩm</label>
                        <span class="text-muted small">
                            Hiện có <strong id="imgCurrent"><?= count($images) ?></strong>/<?= MAX_IMAGES ?> ảnh
                        </span>
                    </div>

                    <?php if (!empty($images)): ?>
                        <div class="row g-3 mb-3" id="imageGrid">
                            <?php $hasPrimary = false; foreach ($images as $im) { if ((int) $im['IsPrimary'] === 1) { $hasPrimary = true; } } ?>
                            <?php foreach ($images as $n => $im): ?>
                                <?php
                                $isPrimaryImg = (int) $im['IsPrimary'] === 1 || (!$hasPrimary && $n === 0);
                                ?>
                                <div class="col-6 col-md-3">
                                    <div class="img-manage" data-id="<?= (int) $im['ProductImageID'] ?>">
                                        <div class="img-manage-thumb">
                                            <img src="/uploads/products/<?= e($im['ImageFile']) ?>" alt="">
                                            <?php if ($isPrimaryImg): ?>
                                                <span class="badge bg-primary img-badge">Ảnh chính</span>
                                            <?php endif; ?>
                                        </div>

                                        <label class="form-check small mt-2 mb-1">
                                            <input class="form-check-input" type="radio"
                                                   name="primary_image"
                                                   value="<?= (int) $im['ProductImageID'] ?>"
                                                   <?= $isPrimaryImg ? 'checked' : '' ?>>
                                            <span class="form-check-label">Đặt làm ảnh chính</span>
                                        </label>

                                        <label class="form-check small mb-0 text-danger">
                                            <input class="form-check-input js-delete" type="checkbox"
                                                   name="delete_images[]"
                                                   value="<?= (int) $im['ProductImageID'] ?>">
                                            <span class="form-check-label">Xóa ảnh này</span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2">Sản phẩm này chưa có ảnh nào.</div>
                    <?php endif; ?>

                    <label for="new_images" class="form-label">Thêm ảnh mới</label>
                    <input
                        type="file"
                        class="form-control"
                        id="new_images"
                        name="new_images[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                    >
                    <div class="form-text">
                        JPG, PNG hoặc WebP, mỗi ảnh tối đa 2MB. Giữ Ctrl (Cmd) để chọn nhiều ảnh cùng lúc.
                        Tổng số ảnh của một sản phẩm tối đa <?= MAX_IMAGES ?>.
                    </div>
                    <div class="new-previews" id="newPreviews"></div>
                </div>

                <script>
                (function () {
                    var MAX = <?= MAX_IMAGES ?>;
                    var MAX_BYTES = <?= MAX_IMAGE_BYTES ?>;
                    var input = document.getElementById('new_images');
                    var preview = document.getElementById('newPreviews');
                    var current = document.getElementById('imgCurrent');
                    var total = <?= count($images) ?>;

                    function removed() {
                        return document.querySelectorAll('.js-delete:checked').length;
                    }

                    function refresh() {
                        var added = input.files ? input.files.length : 0;
                        current.textContent = total - removed() + added;
                    }

                    /* Đánh dấu mờ ảnh sắp xóa */
                    document.querySelectorAll('.js-delete').forEach(function (cb) {
                        cb.addEventListener('change', function () {
                            cb.closest('.img-manage').classList.toggle('marked', cb.checked);
                            refresh();
                        });
                    });

                    /* Xem trước ảnh mới + kiểm tra số lượng/dung lượng */
                    input.addEventListener('change', function () {
                        preview.innerHTML = '';
                        var files = Array.prototype.slice.call(input.files);
                        var room = MAX - (total - removed());
                        var problem = '';

                        if (files.length > room) {
                            problem = 'Chỉ còn chỗ cho ' + Math.max(room, 0) + ' ảnh nữa (tối đa ' + MAX + ' ảnh).';
                        }
                        files.forEach(function (f) {
                            if (f.size > MAX_BYTES) { problem = 'Ảnh "' + f.name + '" vượt quá 2MB.'; }
                        });

                        if (problem) {
                            alert(problem + ' Vui lòng chọn lại.');
                            input.value = '';
                            refresh();
                            return;
                        }

                        files.forEach(function (f) {
                            var box = document.createElement('div');
                            box.className = 'new-preview';
                            var img = document.createElement('img');
                            img.src = URL.createObjectURL(f);
                            box.appendChild(img);
                            preview.appendChild(box);
                        });
                        refresh();
                    });
                })();
                </script>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning">Cập nhật sản phẩm</button>
                    <a href="/admin/products/index.php" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>