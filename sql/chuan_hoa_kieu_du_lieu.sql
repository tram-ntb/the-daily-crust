-- Chạy trực tiếp trong phpMyAdmin / MySQL nếu không dùng migration (SAO LƯU TRƯỚC KHI CHẠY)
-- 1) purchase_requisitions.approved_at: DECIMAL(15,2) -> DATETIME
UPDATE purchase_requisitions SET approved_at = NULL;
ALTER TABLE purchase_requisitions MODIFY approved_at DATETIME NULL;

-- 2) production_batches.start_time / end_time: VARCHAR(255) -> TIME
UPDATE production_batches SET start_time = NULL WHERE start_time IS NOT NULL AND start_time NOT REGEXP '^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$';
UPDATE production_batches SET end_time   = NULL WHERE end_time   IS NOT NULL AND end_time   NOT REGEXP '^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$';
ALTER TABLE production_batches MODIFY start_time TIME NULL, MODIFY end_time TIME NULL;

-- Kiểm tra
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME='purchase_requisitions' AND COLUMN_NAME='approved_at')
   OR (TABLE_NAME='production_batches' AND COLUMN_NAME IN ('start_time','end_time')));
