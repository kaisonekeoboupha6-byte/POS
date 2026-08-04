-- =====================================================
-- ລະບົບຂາຍ POS - ໂຄງສ້າງຖານຂໍ້ມູນ MySQL
-- ວິທີຕິດຕັ້ງ: import ໄຟລ໌ນີ້ເຂົ້າ phpMyAdmin ຫຼື
--   mysql -u root < database.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_db;

-- -----------------------------------------------------
-- 1. ຕາຕະລາງຜູ້ໃຊ້ / ພະນັກງານ (Role-Based Access)
-- -----------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(100) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  role ENUM('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
  status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ໃຊ້ງານ, 0=ປິດ',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ຜູ້ໃຊ້ admin ເລີ່ມຕົ້ນ: username=admin, password=admin
INSERT INTO users (username, password, fullname, role) VALUES
('admin', '21232f297a57a5a743894a0e4a801fc3', 'ຜູ້ດູແລລະບົບ', 'admin');

-- -----------------------------------------------------
-- 2. ໝວດໝູ່ສິນຄ້າ
-- -----------------------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (name, description) VALUES
('ເຄື່ອງດື່ມ', 'ນ້ຳດື່ມ, ນ້ຳອັດລົມ, ກາເຟ'),
('ອາຫານແຫ້ງ', 'ໝີ່, ເຂົ້າໜົມ, ອາຫານກະປ໋ອງ'),
('ຂອງໃຊ້ທົ່ວໄປ', 'ສະບູ, ຢາຖູແຂ້ວ, ຜົງຊັກຟອກ');

-- -----------------------------------------------------
-- 3. ຜູ້ສະໜອງສິນຄ້າ (Supplier)
-- -----------------------------------------------------
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO suppliers (name, phone, address) VALUES
('ບໍລິສັດ ຕົວຢ່າງ ຈຳກັດ', '020 5555 5555', 'ນະຄອນຫຼວງວຽງຈັນ');

-- -----------------------------------------------------
-- 4. ສິນຄ້າ (ມີ barcode, ຕົ້ນທຶນ, ລາຄາຂາຍ, ສະຕ໋ອກ, ຈຸດສັ່ງຊື້ຕໍ່າສຸດ, ຮູບ)
-- -----------------------------------------------------
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  barcode VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category_id INT DEFAULT NULL,
  cost_price DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'ລາຄາຕົ້ນທຶນ',
  sell_price DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'ລາຄາຂາຍ',
  stock_qty INT NOT NULL DEFAULT 0 COMMENT 'ຈຳນວນຄົງຄັງ',
  min_stock INT NOT NULL DEFAULT 5 COMMENT 'ຈຳນວນຂັ້ນຕໍ່າແຈ້ງເຕືອນ',
  image VARCHAR(255) DEFAULT NULL COMMENT 'ຊື່ໄຟລ໌ຮູບໃນໂຟນເດີ uploads/',
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO products (barcode, name, category_id, cost_price, sell_price, stock_qty, min_stock) VALUES
('8850100100016', 'ນ້ຳດື່ມ 600ml', 1, 2000, 3000, 100, 20),
('8850100100023', 'ໂຄຄາໂຄລາ ກະປ໋ອງ', 1, 5000, 7000, 50, 10),
('8850100100030', 'ໝີ່ຕົ້ມຍຳ', 2, 3500, 5000, 80, 15),
('8850100100047', 'ສະບູຖູໂຕ', 3, 8000, 12000, 30, 5),
('8850100100054', 'ກາເຟກະປ໋ອງ', 1, 7000, 10000, 40, 10);

-- -----------------------------------------------------
-- 5. ລູກຄ້າ / ສະມາຊິກ (CRM & Loyalty)
-- -----------------------------------------------------
CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE COMMENT 'ລະຫັດສະມາຊິກ',
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  points INT NOT NULL DEFAULT 0 COMMENT 'ຄະແນນສະສົມ',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO customers (code, name, phone) VALUES
('C0001', 'ລູກຄ້າທົ່ວໄປ', '-');

-- -----------------------------------------------------
-- 6. ກະການເຮັດວຽກ (Shift) - ເປີດ/ປິດກະ + ເງິນລີ້ນຊັກ
-- -----------------------------------------------------
CREATE TABLE shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  opening_cash DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'ເງິນທອນຕອນເປີດກະ',
  closing_cash DECIMAL(15,2) DEFAULT NULL COMMENT 'ເງິນນັບຕົວຈິງຕອນປິດກະ',
  expected_cash DECIMAL(15,2) DEFAULT NULL COMMENT 'ເງິນທີ່ຄວນມີ = ເປີດກະ + ຍອດຂາຍເງິນສົດ',
  difference DECIMAL(15,2) DEFAULT NULL COMMENT 'ສ່ວນຕ່າງ',
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 7. ບິນຂາຍ (ຮອງຮັບ ຈ່າຍສົດ/ບັດ/QR, ສ່ວນຫຼຸດ %/ຈຳນວນເງິນ,
--    ພັກບິນ (held), ຍົກເລີກບິນ (cancelled), ໃບກຳກັບພາສີ)
-- -----------------------------------------------------
CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  user_id INT NOT NULL COMMENT 'ພະນັກງານຂາຍ',
  shift_id INT DEFAULT NULL,
  customer_id INT DEFAULT NULL,
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_type ENUM('percent','amount') DEFAULT NULL,
  discount_value DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'ມູນຄ່າສ່ວນຫຼຸດເປັນເງິນ',
  points_used INT NOT NULL DEFAULT 0,
  points_discount DECIMAL(15,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  total DECIMAL(15,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','card','qr') DEFAULT NULL,
  paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  change_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  points_earned INT NOT NULL DEFAULT 0,
  status ENUM('completed','held','cancelled') NOT NULL DEFAULT 'completed',
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 8. ລາຍການສິນຄ້າໃນບິນ
-- -----------------------------------------------------
CREATE TABLE sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(150) NOT NULL COMMENT 'ບັນທຶກຊື່ໄວ້ ເຜື່ອສິນຄ້າຖືກລຶບ',
  qty INT NOT NULL,
  price DECIMAL(15,2) NOT NULL COMMENT 'ລາຄາຂາຍ/ໜ່ວຍ ຕອນຂາຍ',
  cost DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'ຕົ້ນທຶນ/ໜ່ວຍ ຕອນຂາຍ (ໃຊ້ຄິດກຳໄລ)',
  total DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 9. ການຮັບສິນຄ້າເຂົ້າສາງ ຈາກຜູ້ສະໜອງ
-- -----------------------------------------------------
CREATE TABLE purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  supplier_id INT DEFAULT NULL,
  user_id INT NOT NULL,
  total DECIMAL(15,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  cost DECIMAL(15,2) NOT NULL,
  total DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 10. ການປັບສະຕ໋ອກ / ກວດນັບສິນຄ້າ
-- -----------------------------------------------------
CREATE TABLE stock_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  change_qty INT NOT NULL COMMENT 'ບວກ = ເພີ່ມ, ລົບ = ຫັກອອກ',
  reason VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 11. ປະຫວັດຄະແນນສະສົມ (Point Reward)
-- -----------------------------------------------------
CREATE TABLE point_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  sale_id INT DEFAULT NULL,
  points INT NOT NULL,
  type ENUM('earn','redeem','adjust') NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 12. ການຕັ້ງຄ່າລະບົບ
-- -----------------------------------------------------
CREATE TABLE settings (
  skey VARCHAR(50) PRIMARY KEY,
  svalue TEXT
) ENGINE=InnoDB;

INSERT INTO settings (skey, svalue) VALUES
('store_name', 'ຮ້ານຄ້າຕົວຢ່າງ'),
('store_address', 'ນະຄອນຫຼວງວຽງຈັນ, ສປປ ລາວ'),
('store_phone', '020 0000 0000'),
('tax_id', '000000000'),
('tax_rate', '0'),
('point_rate', '10000'),
('point_value', '100'),
('receipt_footer', 'ຂອບໃຈທີ່ອຸດໜູນ');
