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

            // Thực hiện Cập nhật (UPDATE)
            $sql = "
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
            ";

            $stmt = $conn->prepare($sql);

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

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header('Location: /admin/products/');
                exit;
            }

            $error = 'Không thể cập nhật sản phẩm.';
            $stmt->close();
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

            <form method="post" class="card card-body shadow-sm">
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