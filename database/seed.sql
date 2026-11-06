SET NAMES utf8mb4;

INSERT INTO categories (CategoryName, Description)
VALUES
    ('Điện thoại', 'Các sản phẩm điện thoại di động'),
    ('Máy tính', 'Máy tính xách tay và máy tính để bàn'),
    ('Phụ kiện', 'Phụ kiện dành cho thiết bị công nghệ');

INSERT INTO suppliers
    (SupplierName, ContactName, Address, City, PostalCode, Country, Phone)
VALUES
    ('Công ty Công nghệ ABC', 'Nguyễn Văn An',
     '01 Nguyễn Huệ', 'TP. Hồ Chí Minh', '700000', 'Việt Nam', '0901234567'),
    ('Công ty Thiết bị XYZ', 'Trần Minh Bình',
     '25 Lê Lợi', 'Đà Nẵng', '550000', 'Việt Nam', '0912345678');

INSERT INTO customers
    (CustomerName, ContactName, Address, City, PostalCode, Country)
VALUES
    ('Nguyễn Minh Anh', 'Nguyễn Minh Anh',
     '12 Nguyễn Trãi', 'TP. Hồ Chí Minh', '700000', 'Việt Nam'),
    ('Trần Thanh Hà', 'Trần Thanh Hà',
     '35 Hùng Vương', 'Cần Thơ', '900000', 'Việt Nam'),
    ('Lê Quốc Bảo', 'Lê Quốc Bảo',
     '18 Lê Lợi', 'Trà Vinh', '870000', 'Việt Nam');

INSERT INTO employees
    (LastName, FirstName, BirthDate, Photo, Notes, Email, PasswordHash, Role)
VALUES
    ('Nguyễn', 'Hoàng Nam', '1995-05-15', NULL, 'Nhân viên bán hàng',
     'nhanvien1@shop.local', '$2y$10$GWbKmx8WRgb5QYnvwNGX0e83lhzupGssf5VqO97fdb5pGTL9F7692', 'staff'),
    ('Trần', 'Thúy An', '1997-09-20', NULL, 'Nhân viên chăm sóc khách hàng',
     'nhanvien2@shop.local', '$2y$10$arek2XLMj9UDp0jqn0B00.fCjtgyJlhA5i/ztP1L9cM.IgjNlVDjG', 'staff'),
    ('Quản trị', 'Hệ thống', '1990-01-01', NULL, 'Tài khoản quản trị viên',
     'admin@shop.local', '$2y$10$rVdOpPz0cfcyCoar6M9tWeNcpGROTnvDjpqZ6GhBQP1jF8uUmHfCK', 'admin');

-- Tài khoản khách hàng để thử đăng nhập
INSERT INTO customers
    (CustomerName, ContactName, Address, City, Country, Phone, Email, PasswordHash)
VALUES
    ('Khách Thử Nghiệm', 'Khách Thử Nghiệm', '99 Trần Phú', 'Trà Vinh',
     'Việt Nam', '0987654321', 'khach@shop.local', '$2y$10$inAEvOt7BT0ek8Z76KO1cevw3iNnN2ZAtfeJrHN0.2iJG/eun3J/K');

INSERT INTO shippers (ShipperName, Phone)
VALUES
    ('Giao hàng nhanh', '19001001'),
    ('Giao hàng tiết kiệm', '19001002');

INSERT INTO products
    (ProductCode, ProductName, Description, Unit, Price,
     StockQuantity, IsActive, SupplierID, CategoryID)
VALUES
    ('SP001', 'Điện thoại Smartphone A',
     'Điện thoại thông minh màn hình lớn, phù hợp nhu cầu sử dụng hằng ngày.',
     'Chiếc', 8500000.00, 20, TRUE, 1, 1),
    ('SP002', 'Laptop B',
     'Máy tính xách tay phục vụ học tập và làm việc.',
     'Chiếc', 18500000.00, 10, TRUE, 2, 2),
    ('SP003', 'Chuột không dây C',
     'Chuột không dây nhỏ gọn, kết nối ổn định.',
     'Chiếc', 450000.00, 50, TRUE, 2, 3);

INSERT INTO product_images
    (ProductID, ImageFile, AltText, IsPrimary, SortOrder)
VALUES
    (1, 'ip_18prm512.webp', 'Điện thoại Smartphone A - ảnh chính', TRUE, 1),
    (1, 'ip_18pro2T.webp', 'Điện thoại Smartphone A - mặt sau', FALSE, 2),
    (2, 'laptop_hp_gaming_victus.webp', 'Laptop B - ảnh chính', TRUE, 1),
    (3, 'chuot_logitech.webp', 'Chuột không dây C - ảnh chính', TRUE, 1);

INSERT INTO orders
    (OrderDate, TotalAmount, Status, CustomerID, EmployeeID, ShipperID)
VALUES
    ('2026-08-10 09:30:00', 9400000.00,  'Completed', 1, 1, 1),
    ('2026-08-11 14:00:00', 18500000.00, 'Shipping',  2, 2, 2),
    ('2026-08-12 16:45:00', 900000.00,   'Pending',   3, NULL, NULL);

INSERT INTO orderdetail (Quantity, UnitPrice, OrderID, ProductID)
VALUES
    (1, 8500000.00, 1, 1),
    (2, 450000.00, 1, 3),
    (1, 18500000.00, 2, 2),
    (2, 450000.00, 3, 3);