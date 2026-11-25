<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$keyword =
    trim($_GET['keyword'] ?? '');

$categoryID =
    (int) ($_GET['category'] ?? 0);

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM products p, categories c
    WHERE p.CategoryID = c.CategoryID
      AND p.IsActive = 1
";

$types = '';
$params = [];

if ($keyword !== '') {
    $sql .= " AND (p.ProductName LIKE ? OR p.ProductCode LIKE ?) ";
    $types .= 'ss';
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}

if ($categoryID > 0) {
    $sql .= " AND p.CategoryID = ? ";
    $types .= 'i';
    $params[] = $categoryID;
}

/* Lọc theo khoảng giá: ?min_price=1000000&max_price=20000000 */
if (isset($_GET['min_price']) && (int) $_GET['min_price'] > 0) {
    $sql .= " AND p.Price >= ? ";
    $types .= 'i';
    $params[] = (int) $_GET['min_price'];
}

if (isset($_GET['max_price']) && (int) $_GET['max_price'] > 0) {
    $sql .= " AND p.Price <= ? ";
    $types .= 'i';
    $params[] = (int) $_GET['max_price'];
}

$sql .= " ORDER BY p.ProductID DESC ";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result =
    $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {

    $products[] = [
        'id' => (int) $row['ProductID'],
        'code' => $row['ProductCode'],
        'name' => $row['ProductName'],
        'price' => (float) $row['Price'],
        'stock' => (int) $row['StockQuantity'],
        'category' => $row['CategoryName'],
        'image' => $row['ImageFile']
    ];
}

$result->free();
$stmt->close();
$conn->close();

echo json_encode(
    [
        'success' => true,
        'data' => $products
    ],
    JSON_UNESCAPED_UNICODE
);