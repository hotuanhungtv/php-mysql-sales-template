<?php
require_once '/var/www/src/config/auth.php';
require_admin();

$pageTitle = 'Thêm sản phẩm mới';

require_once '/var/www/src/config/database.php';

$errors = [];

// Gợi ý mã sản phẩm kế tiếp (SP001, SP002... chọn số lớn nhất + 1)
$nextCode = 'SP001';
$codeResult = $conn->query(
    "SELECT COALESCE(MAX(CAST(SUBSTRING(ProductCode, 3) AS UNSIGNED)), 0) + 1
     FROM products
     WHERE ProductCode REGEXP '^SP[0-9]+$'"
);
if ($codeResult) {
    $nextCode = 'SP' . str_pad((string) (int) $codeResult->fetch_row()[0], 3, '0', STR_PAD_LEFT);
    $codeResult->free();
}

// Lấy danh sách Danh mục và Nhà cung cấp
$categories = $conn->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName ASC");
$suppliers  = $conn->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Nhận dữ liệu Form
    $productCode   = trim($_POST['ProductCode'] ?? '');
    $productName   = trim($_POST['ProductName'] ?? '');
    $description   = trim($_POST['Description'] ?? '');
    $categoryID    = (int) ($_POST['CategoryID'] ?? 0);
    $supplierID    = (int) ($_POST['SupplierID'] ?? 0);
    $unit          = trim($_POST['Unit'] ?? '');
    $price         = (float) ($_POST['Price'] ?? 0);
    $stockQuantity = (int) ($_POST['StockQuantity'] ?? 0);
    $isActive      = isset($_POST['IsActive']) ? 1 : 0;

    $files = $_FILES['product_images'] ?? null;

    // 2. Validate dữ liệu
    if (empty($productCode))  $errors[] = 'Mã sản phẩm không được để trống.';
    if (empty($productName))  $errors[] = 'Tên sản phẩm không được để trống.';
    if ($categoryID <= 0)     $errors[] = 'Vui lòng chọn danh mục.';
    if ($supplierID <= 0)     $errors[] = 'Vui lòng chọn nhà cung cấp.';
    if ($price < 0)          $errors[] = 'Giá sản phẩm không hợp lệ.';

    // Validate danh sách File Upload (Phải chọn từ 1 đến 8 ảnh)
    $uploadedCount = count($files['name'] ?? []);
    if (!$files || $uploadedCount === 0 || empty($files['name'][0])) {
        $errors[] = 'Vui lòng chọn ít nhất 1 ảnh cho sản phẩm.';
    } elseif ($uploadedCount > 8) {
        $errors[] = 'Bạn chỉ được chọn tối đa 8 ảnh.';
    } else {
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $uploadedCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Lỗi upload ở ảnh thứ " . ($i + 1);
                continue;
            }

            if ($files['size'][$i] > 2 * 1024 * 1024) {
                $errors[] = "Ảnh thứ " . ($i + 1) . " vượt quá dung lượng 2 MB.";
            }

            $mimeType = $finfo->file($files['tmp_name'][$i]);
            if (!array_key_exists($mimeType, $allowedTypes)) {
                $errors[] = "Ảnh thứ " . ($i + 1) . " không đúng định dạng (Chỉ chấp nhận JPG, PNG, WebP).";
            }
        }
    }

    // 3. Tiến hành lưu vào Database bằng Transaction
    if (empty($errors)) {
        $uploadedPaths = []; // Mảng theo dõi đường dẫn các file đã lưu để rollback nếu lỗi
        $uploadDir     = '/var/www/html/uploads/products/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        try {
            $conn->begin_transaction();

            // STEP 1: Thêm sản phẩm vào bảng products
            $stmtProd = $conn->prepare("
                INSERT INTO products (ProductCode, ProductName, Description, CategoryID, SupplierID, Unit, Price, StockQuantity, IsActive)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtProd->bind_param("sssiisdii", $productCode, $productName, $description, $categoryID, $supplierID, $unit, $price, $stockQuantity, $isActive);
            $stmtProd->execute();

            $productID = $conn->insert_id;
            $stmtProd->close();

            // STEP 2: Lặp qua từng file để lưu vào disk và INSERT vào bảng product_images
            $stmtImage = $conn->prepare("
                INSERT INTO product_images (ProductID, ImageFile, IsPrimary, SortOrder, AltText)
                VALUES (?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < $uploadedCount; $i++) {
                $mimeType    = $finfo->file($files['tmp_name'][$i]);
                $extension   = $allowedTypes[$mimeType];
                $newFileName = 'product-' . $productID . '-' . ($i + 1) . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
                $destination = $uploadDir . $newFileName;

                if (!move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    throw new Exception("Không thể lưu file ảnh thứ " . ($i + 1));
                }

                $uploadedPaths[] = $destination;

                // Ảnh đầu tiên (index 0) làm ảnh chính (IsPrimary = 1), các ảnh còn lại IsPrimary = 0
                $isPrimary = ($i === 0) ? 1 : 0;
                $sortOrder = $i + 1;
                $altText   = $productName . " - Ảnh " . $sortOrder;

                $stmtImage->bind_param("isiis", $productID, $newFileName, $isPrimary, $sortOrder, $altText);
                $stmtImage->execute();
            }

            $stmtImage->close();
            
            // TẤT CẢ THÀNH CÔNG -> HOÀN TẤT TRANSACTION
            $conn->commit();

            header('Location: /admin/products/index.php?msg=created');
            exit();

        } catch (Throwable $e) {
            // CÓ LỖI XẢY RA -> ROLLBACK DATABASE & XÓA FILE RÁC
            $conn->rollback();

            foreach ($uploadedPaths as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $errors[] = ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1062)
                ? 'Mã sản phẩm "' . $productCode . '" đã tồn tại. Vui lòng nhập mã khác (gợi ý: ' . $nextCode . ').'
                : 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Thêm sản phẩm mới (Lưu nhiều ảnh)</h4>
                </div>
                <div class="card-body">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ProductCode" class="form-label fw-bold">Mã SP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ProductCode" name="ProductCode" value="<?= htmlspecialchars($_POST['ProductCode'] ?? $nextCode) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="ProductName" class="form-label fw-bold">Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ProductName" name="ProductName" value="<?= htmlspecialchars($_POST['ProductName'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="Description" class="form-label fw-bold">Mô tả sản phẩm</label>
                            <textarea class="form-control" id="Description" name="Description" rows="3" placeholder="Nhập mô tả sản phẩm..."><?= htmlspecialchars($_POST['Description'] ?? '') ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="CategoryID" class="form-label fw-bold">Danh mục <span class="text-danger">*</span></label>
                                <select class="form-select" id="CategoryID" name="CategoryID" required>
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?= $cat['CategoryID'] ?>" <?= (($_POST['CategoryID'] ?? '') == $cat['CategoryID']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['CategoryName']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="SupplierID" class="form-label fw-bold">Nhà cung cấp <span class="text-danger">*</span></label>
                                <select class="form-select" id="SupplierID" name="SupplierID" required>
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php while ($sup = $suppliers->fetch_assoc()): ?>
                                        <option value="<?= $sup['SupplierID'] ?>" <?= (($_POST['SupplierID'] ?? '') == $sup['SupplierID']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sup['SupplierName']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="Unit" class="form-label fw-bold">Đơn vị tính</label>
                                <input type="text" class="form-control" id="Unit" name="Unit" value="<?= htmlspecialchars($_POST['Unit'] ?? '') ?>" placeholder="Chiếc, Cái...">
                            </div>
                            <div class="col-md-4">
                                <label for="Price" class="form-label fw-bold">Đơn giá <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="Price" name="Price" value="<?= htmlspecialchars($_POST['Price'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="StockQuantity" class="form-label fw-bold">Tồn kho</label>
                                <input type="number" class="form-control" id="StockQuantity" name="StockQuantity" value="<?= htmlspecialchars($_POST['StockQuantity'] ?? '0') ?>" min="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="productImages" class="form-label fw-bold">
                                Chọn ảnh sản phẩm (Từ 1 đến 8 ảnh) <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="file" 
                                class="form-control" 
                                id="productImages" 
                                name="product_images[]" 
                                accept="image/jpeg,image/png,image/webp" 
                                multiple 
                                required
                            >
                            <small class="text-muted">Giữ phím Ctrl / Cmd để chọn tối đa 8 ảnh. Ảnh đầu tiên sẽ làm ảnh đại diện chính.</small>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="IsActive" name="IsActive" value="1" checked>
                            <label class="form-check-label fw-bold" for="IsActive">Đang kinh doanh</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/products/index.php" class="btn btn-secondary">Hủy</a>
                            <button type="submit" class="btn btn-primary fw-bold">Lưu sản phẩm</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>