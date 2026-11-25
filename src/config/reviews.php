<?php

/* =====================================================
   ĐÁNH GIÁ & NHẬN XÉT SẢN PHẨM - hàm dùng chung
   ===================================================== */

/*
 * Điều kiện để khách được đánh giá sản phẩm đã mua:
 *   false = đơn hàng chỉ cần KHÁC "Đã hủy"        (dễ thử nghiệm)
 *   true  = đơn hàng phải ở trạng thái "Hoàn thành" (đã nhận hàng)
 */
const REVIEW_REQUIRE_COMPLETED = false;

/* Tự tạo bảng reviews nếu chưa có (không cần xóa database cũ) */
function reviews_ensure_table(mysqli $conn): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS reviews (
            ReviewID INT AUTO_INCREMENT PRIMARY KEY,
            ProductID INT NOT NULL,
            CustomerID INT NOT NULL,
            OrderID INT NULL,
            Rating TINYINT NOT NULL,
            Comment TEXT NOT NULL,
            IsVisible BOOLEAN NOT NULL DEFAULT TRUE,
            IsRead BOOLEAN NOT NULL DEFAULT FALSE,
            AdminReply TEXT NULL,
            RepliedAt DATETIME NULL,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT uq_reviews_product_customer UNIQUE (ProductID, CustomerID),
            CONSTRAINT chk_reviews_rating CHECK (Rating BETWEEN 1 AND 5),
            CONSTRAINT fk_reviews_product
                FOREIGN KEY (ProductID) REFERENCES products(ProductID)
                ON DELETE CASCADE,
            CONSTRAINT fk_reviews_customer
                FOREIGN KEY (CustomerID) REFERENCES customers(CustomerID)
                ON DELETE CASCADE,
            CONSTRAINT fk_reviews_order
                FOREIGN KEY (OrderID) REFERENCES orders(OrderID)
                ON DELETE SET NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");

    $done = true;
}

/*
 * Tìm đơn hàng đủ điều kiện đánh giá của khách với sản phẩm.
 * Trả về OrderID, hoặc 0 nếu khách chưa mua.
 */
function review_eligible_order(mysqli $conn, int $customerID, int $productID): int
{
    $statusSql = REVIEW_REQUIRE_COMPLETED
        ? "o.Status = 'Completed'"
        : "o.Status <> 'Cancelled'";

    $stmt = $conn->prepare("
        SELECT o.OrderID
        FROM orders o
        JOIN orderdetail od ON od.OrderID = o.OrderID
        WHERE o.CustomerID = ?
          AND od.ProductID = ?
          AND $statusSql
        ORDER BY o.OrderID DESC
        LIMIT 1
    ");
    $stmt->bind_param('ii', $customerID, $productID);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['OrderID'] : 0;
}

/* Số sao dạng HTML (★ đầy / ☆ rỗng) */
function review_stars(float $rating, string $class = ''): string
{
    $full = (int) round($rating);
    $html = '<span class="stars ' . e($class) . '" aria-label="'
        . e(number_format($rating, 1)) . ' trên 5 sao">';

    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="bi ' . ($i <= $full ? 'bi-star-fill' : 'bi-star') . '"></i>';
    }

    return $html . '</span>';
}

/* Che bớt tên khách khi hiển thị công khai: "Nguyễn Minh Anh" -> "Nguyễn M. A." */
function review_display_name(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];

    if (count($parts) <= 1) {
        return $name;
    }

    $out = [array_shift($parts)];

    foreach ($parts as $p) {
        $out[] = mb_substr($p, 0, 1, 'UTF-8') . '.';
    }

    return implode(' ', $out);
}

/* Số đánh giá mới chưa được nhân viên xem (hiện ở menu quản trị) */
function reviews_unread_count(mysqli $conn): int
{
    reviews_ensure_table($conn);

    $result = $conn->query('SELECT COUNT(*) AS total FROM reviews WHERE IsRead = 0');
    $count = $result ? (int) $result->fetch_assoc()['total'] : 0;

    if ($result) {
        $result->free();
    }

    return $count;
}
