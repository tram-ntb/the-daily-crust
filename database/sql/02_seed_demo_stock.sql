-- =====================================================================
-- Tạo tồn kho thành phẩm DEMO để màn hình POS bán thử được.
-- (Trong dữ liệu mẫu chỉ sản phẩm 1 có lô, và lô đó đã hết hạn.)
-- Chạy SAU khi đã chạy migration của Phân hệ 1 và Phân hệ 2 (cần lot_id tự tăng).
-- Mỗi sản phẩm đang bán, chưa có lô bán được, nhận 1 lô 30 cái, hạn dùng theo expiry_duration_day.
-- Xem lại bằng: SELECT product_id, remaining_qty, expiry_date FROM product_lots ORDER BY lot_id DESC;
-- =====================================================================
INSERT INTO product_lots (product_id, batch_id, production_date, expiry_date, initial_qty, remaining_qty, status)
SELECT p.product_id,
       NULL,
       NOW(),
       DATE_ADD(NOW(), INTERVAL GREATEST(1, ROUND(IFNULL(p.expiry_duration_day, 1) * 24)) HOUR),
       30, 30, 'Active'
FROM products p
WHERE (p.status IS NULL OR p.status = 'Active')
  AND p.product_id NOT IN (
        SELECT product_id FROM product_lots
        WHERE status = 'Active' AND remaining_qty > 0
          AND (expiry_date IS NULL OR expiry_date > NOW())
  );
-- =====================================================================
-- Kiểm tra kết  quả

-- SELECT p.vi_name, l.lot_id, l.remaining_qty, l.expiry_date
-- FROM product_lots l
-- JOIN products p ON p.product_id = l.product_id
-- ORDER BY l.lot_id DESC
-- LIMIT 40;