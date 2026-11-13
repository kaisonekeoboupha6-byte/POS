-- =====================================================
-- POS Database Performance Indexes (Existing DB Migration)
-- Run on existing localhost MySQL/MariaDB database:
--   mysql -u root pos_db < database_indexes.sql
-- Verify query plans before/after in MySQL with:
--   EXPLAIN SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND created_at >= CURDATE();
--   EXPLAIN SELECT * FROM shifts WHERE user_id=1 AND status='open' ORDER BY id DESC LIMIT 1;
-- =====================================================

USE pos_db;

CREATE INDEX idx_shifts_user_status_id     ON shifts (user_id, status, id);
CREATE INDEX idx_sales_status_created      ON sales (status, created_at);
CREATE INDEX idx_sales_shift_status_method ON sales (shift_id, status, payment_method);
CREATE INDEX idx_sales_customer_created    ON sales (customer_id, created_at);
CREATE INDEX idx_products_status_name      ON products (status, name);
CREATE INDEX idx_products_status_stock_min ON products (status, stock_qty, min_stock);
CREATE INDEX idx_points_cust_id            ON point_transactions (customer_id, id);
