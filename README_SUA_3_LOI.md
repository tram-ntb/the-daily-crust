# Sửa 3 lỗi: chi tiết thành phần, giỏ hàng lưu CSDL, cảnh báo hết hàng

Điều kiện: đã tích hợp Phân hệ 1, 2, 3 (cần `Product::boms()` và `ProductLot::sellable()` trong các Model đã gửi).

## Chép file (ghi đè nếu trùng)
| File | Việc |
|---|---|
| `database/migrations/2026_10_24_000001_create_cart_items_table.php` | MỚI: bảng `cart_items` |
| `app/Models/CartItem.php` | MỚI |
| `app/Services/CartService.php` | MỚI: logic giỏ hàng + kiểm tra tồn kho |
| `app/Http/Controllers/Customer/CartController.php` | MỚI: API giỏ hàng (JSON) |
| `app/Http/Controllers/ProductDetailController.php` | MỚI: trang chi tiết thành phần |
| `resources/views/products/show.blade.php` | MỚI: giao diện chi tiết thành phần |
| `app/Http/Controllers/Customer/OnlineOrderController.php` | SỬA: đặt hàng lấy giỏ từ CSDL, thành công mới xóa giỏ |
| `app/Http/Controllers/DashboardController.php` | SỬA: thêm tồn kho từng sản phẩm |
| `resources/views/dashboard.blade.php` | SỬA (trên bản bạn đã chỉnh): link sang chi tiết, nhãn tồn kho, khóa nút khi hết hàng |
| `resources/views/customer/_cart.blade.php` | SỬA (trên bản bạn đã chỉnh): giỏ nói chuyện với máy chủ, cảnh báo hết hàng |
| `routes/web.php` | SỬA: thêm route chi tiết sản phẩm và giỏ hàng. Nếu bạn đã sửa web.php, chỉ gộp các route mới |

## Chạy
```
php artisan migrate
php artisan optimize:clear
```

## Cách hoạt động
1. Trang chi tiết: `GET /products/{id}` công khai (route `products.show`). Chỉ truy vấn tên nguyên liệu, KHÔNG nạp cột định lượng; xếp theo chữ cái để không lộ thứ tự nhiều/ít.
2. Giỏ hàng: lưu ở bảng `cart_items` (user_id, product_id, quantity, duy nhất theo cặp user + sản phẩm). Đăng nhập ở trình duyệt khác sẽ thấy cùng giỏ.
   Giỏ cũ trong LocalStorage (khóa `tdc_cart_v1`) được tự chuyển lên tài khoản một lần rồi xóa.
3. Hết hàng: kiểm tra ở 3 lớp: (a) thẻ sản phẩm hiện nhãn "Hết hàng" và khóa nút; (b) trình duyệt chặn và hiện thông báo đỏ ngay khi bấm; (c) máy chủ kiểm tra lại khi thêm/tăng (tồn = các lô còn hàng và chưa hết hạn), trả 422 kèm thông báo.
   Nút Đặt hàng bị khóa nếu giỏ còn dòng hết hàng hoặc vượt tồn; khi đặt hàng thất bại, giỏ tự mở và hiện lỗi.

## Lưu ý
- Số bảng tăng thêm 1 (`cart_items`): nhớ thêm vào ERD (quan hệ nhiều-một với `users` và `products`). Migration thử thêm khóa ngoại; nếu CSDL từ chối vẫn tạo bảng bình thường.
- Giỏ không giữ chỗ hàng: hai khách có thể cùng bỏ món cuối vào giỏ; người đặt sau sẽ nhận thông báo hết hàng ở bước đặt.
- Mã đã qua `php -l`; phần JavaScript đã được mô phỏng trong Node (hết hàng, vượt tồn, lỗi 422, phiên hết hạn, nhập giỏ cũ). Phần PHP CHƯA chạy được trong Laravel thật (môi trường của mình không cài được Composer), hãy thử theo mục kiểm tra trong tin nhắn.
