-- Quantum Gear: categories and products
-- Import in phpMyAdmin (select the quantum_gear database, then Import this file).
-- Safe to re-run: tables are only created if missing, and seed rows are skipped if they already exist.

CREATE DATABASE IF NOT EXISTS quantum_gear;
USE quantum_gear;

CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50) NOT NULL UNIQUE,
    icon        VARCHAR(50) NOT NULL DEFAULT 'fa-box' -- Font Awesome class shown when a product has no image
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    product_id  INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name        VARCHAR(150) NOT NULL UNIQUE,
    brand       VARCHAR(50) NOT NULL,
    description TEXT NULL,
    price       DECIMAL(10,2) NOT NULL,
    old_price   DECIMAL(10,2) NULL,                 -- NULL = not on sale
    stock       INT UNSIGNED NOT NULL DEFAULT 0,
    image_path  VARCHAR(255) NULL,                  -- NULL = show the category icon
    rating      TINYINT UNSIGNED NOT NULL DEFAULT 0, -- placeholder (0-5) until reviews exist
    reviews     INT UNSIGNED NOT NULL DEFAULT 0,     -- placeholder review count
    is_active   TINYINT(1) NOT NULL DEFAULT 1,       -- 0 = hidden from the shop
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_price     CHECK (price >= 0),
    CONSTRAINT chk_old_price CHECK (old_price IS NULL OR old_price >= 0),
    CONSTRAINT chk_rating    CHECK (rating <= 5),
    INDEX idx_products_brand (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data
INSERT IGNORE INTO categories (name, icon) VALUES
    ('Components & Storage', 'fa-memory'),
    ('Computer Systems',     'fa-desktop'),
    ('Computer Peripherals', 'fa-computer-mouse'),
    ('Gaming & VR',          'fa-gamepad'),
    ('Networking',           'fa-wifi'),
    ('Electronics',          'fa-headphones');

-- Products (images are in Images/products/)
-- (Filenames with '' are inch marks; each ' is doubled again to escape it inside the SQL string.)
INSERT IGNORE INTO products (category_id, name, brand, description, price, old_price, stock, image_path)
SELECT c.category_id, p.name, p.brand, p.description, p.price, p.old_price, p.stock, p.image_path
FROM (
    SELECT 'Components & Storage' AS category, 'AMD Radeon RX 9060 XT' AS name, 'AMD' AS brand,
           'Mainstream RDNA 4 graphics card built for smooth 1080p and 1440p gaming, with AMD FSR upscaling and hardware ray tracing.' AS description,
           529.00 AS price, NULL AS old_price, 18 AS stock, 'Images/products/AMD_RX9060XT.svg' AS image_path
    UNION ALL SELECT 'Components & Storage', 'AMD Radeon RX 9070 XT', 'AMD',
           'High-performance RDNA 4 graphics card with 16GB of GDDR6 memory, made for high-refresh 1440p and 4K gaming with AMD FSR 4 and ray tracing.',
           999.00, 1099.00, 10, 'Images/products/AMD_RX9070XT.svg'
    UNION ALL SELECT 'Components & Storage', 'NVIDIA GeForce RTX 5080 Founders Edition', 'NVIDIA',
           'Blackwell-based graphics card with 16GB of GDDR7 memory and DLSS 4, delivering fast 4K gaming and strong AI and creative performance.',
           1749.00, NULL, 6, 'Images/products/Nvidia_RTX5080.svg'
    UNION ALL SELECT 'Components & Storage', 'AMD Ryzen 7 9800X3D Processor', 'AMD',
           '8-core, 16-thread AM5 desktop processor with AMD 3D V-Cache technology, one of the fastest CPUs available for gaming.',
           729.00, 799.00, 14, 'Images/products/AMD_Ryzen-7-9800X3D.svg'
    UNION ALL SELECT 'Components & Storage', 'AMD Ryzen 9 9950X3D Processor', 'AMD',
           '16-core, 32-thread AM5 desktop processor with AMD 3D V-Cache, combining top gaming performance with heavy multitasking and content creation power.',
           1099.00, NULL, 7, 'Images/products/AMD_Ryzen-9-9950X3D.svg'
    UNION ALL SELECT 'Computer Peripherals', 'Lenovo 24" 100Hz Monitor', 'Lenovo',
           '24-inch everyday monitor with a smooth 100Hz refresh rate and slim bezels, ideal for work, study and casual gaming.',
           159.00, 189.00, 30, 'Images/products/Lenovo_24''''-100hz-Monitor.svg'
    UNION ALL SELECT 'Electronics', 'Apple iPhone 18 Pro', 'Apple',
           'Apple''s Pro iPhone with a premium design, advanced multi-camera system and all-day battery life.',
           1749.00, NULL, 12, 'Images/products/Apple_iPhone-18-Pro.svg'
    UNION ALL SELECT 'Electronics', 'Apple iPhone Duo', 'Apple',
           'A new take on the iPhone with a sleek design, powerful Apple silicon and a versatile camera system.',
           1399.00, NULL, 9, 'Images/products/Apple_iPhone-Duo.svg'
    UNION ALL SELECT 'Electronics', 'Apple iPad 11"', 'Apple',
           'Versatile 11-inch iPad with a bright Liquid Retina display, great for streaming, note-taking and everyday productivity.',
           549.00, 599.00, 20, 'Images/products/Apple_iPad-11''''.svg'
    UNION ALL SELECT 'Electronics', 'Sharp 55" 4K UHD TV', 'Sharp',
           '55-inch 4K Ultra HD smart TV with vivid colour and built-in streaming apps, a great centrepiece for any living room.',
           899.00, 1099.00, 8, 'Images/products/Sharp_55''''-4K-TV.svg'
) AS p
JOIN categories c ON c.name = p.category;
