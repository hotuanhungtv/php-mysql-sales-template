<?php
require_once '/var/www/src/config/auth.php';
require_admin();


$pageTitle = 'Quản lý sản phẩm';

require_once '/var/www/src/config/database.php';

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Unit,
        p.Price,
        p.StockQuantity,
        p.IsActive,
        c.CategoryName,
        s.SupplierName,
        pi.ImageFile,
        pi.AltText
    FROM
        products AS p,
        categories AS c,
        suppliers AS s,
        product_images AS pi
    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
        AND p.ProductID = pi.ProductID
        AND pi.IsPrimary = 1
    ORDER BY
        p.ProductID
";

$result = $conn->query($sql);

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container-fluid mt-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Quản lý sản phẩm</h2>

        <a href="/admin/products/create.php" class="btn btn-primary">
            Thêm sản phẩm
        </a>
    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped align-middle">

            <thead class="table-dark text-center">
                <tr>
                    <th>Hình ảnh</th> <!-- CỘT HÌNH ẢNH -->
                    <th>Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Nhà cung cấp</th>
                    <th>Đơn vị</th>
                    <th>Giá</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                    <th style="min-width: 130px;">Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php while ($product = $result->fetch_assoc()): ?>

                <tr>
                    <!-- XỬ LÝ HIỂN THỊ HÌNH ẢNH -->
                    <td class="text-center" style="width: 80px;">
                        <?php if (!empty($product['ImageFile']) && file_exists('/var/www/html/uploads/products/' . $product['ImageFile'])): ?>
                            <img src="/uploads/products/<?= htmlspecialchars($product['ImageFile']) ?>" 
                                 alt="<?= htmlspecialchars($product['AltText'] ?? $product['ProductName']) ?>" 
                                 class="img-thumbnail" 
                                 style="max-width: 60px; max-height: 60px; object-fit: cover;">
                        <?php else: ?>
                            <span class="badge bg-light text-secondary border">Chưa có ảnh</span>
                        <?php endif; ?>
                    </td>

                    <td><?= htmlspecialchars($product['ProductCode']) ?></td>

                    <td><?= htmlspecialchars($product['ProductName']) ?></td>

                    <td><?= htmlspecialchars($product['CategoryName']) ?></td>

                    <td><?= htmlspecialchars($product['SupplierName']) ?></td>

                    <td><?= htmlspecialchars($product['Unit'] ?? '') ?></td>

                    <td class="text-end fw-bold">
                        <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                    </td>

                    <td class="text-center">
                        <?= (int) $product['StockQuantity'] ?>
                    </td>

                    <td class="text-center">
                        <?php if ((int) $product['IsActive'] === 1): ?>
                            <span class="badge bg-success">Đang bán</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Ngừng bán</span>
                        <?php endif; ?>
                    </td>

                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="/admin/products/edit.php?id=<?= $product['ProductID'] ?>" class="btn btn-sm btn-warning">
                                Sửa
                            </a>

                            <form action="/admin/products/delete.php" 
                                  method="post" 
                                  class="d-inline"
                                  onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
<?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $product['ProductID'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    Xóa
                                </button>
                            </form>
                        </div>
                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>