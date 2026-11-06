# Website bán hàng PHP + MySQL

## Chạy project
```bash
docker compose up -d
```
Mở http://localhost:8080 (xem cổng trong compose.yaml).

Khi đổi `database/schema.sql` hoặc `seed.sql`, cần xóa dữ liệu cũ để MySQL nạp lại:
```bash
docker compose down -v
docker compose up -d --build
```

## Tài khoản thử nghiệm
| Vai trò | Đăng nhập tại | Email | Mật khẩu |
|---|---|---|---|
| Quản trị viên | /admin/login.php | admin@shop.local | Admin@123 |
| Nhân viên | /admin/login.php | nhanvien1@shop.local | Staff@123 |
| Khách hàng | /login.php | khach@shop.local | Khach@123 |

## Phân quyền
- Khách hàng: xem sản phẩm, giỏ hàng, đặt hàng (bắt buộc đăng nhập), xem/hủy đơn, sửa hồ sơ.
- Nhân viên: xem dashboard số đơn, xử lý đơn hàng (xác nhận, giao, hoàn thành, hủy).
- Quản trị viên: toàn quyền + sản phẩm, danh mục, khách hàng, nhân viên, shipper, thống kê doanh thu.
