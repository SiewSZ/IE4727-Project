-- Quantum Gear: users, categories and products
-- Import in phpMyAdmin from the home page (no database selected): this file creates quantum_gear itself.
-- Safe to re-run: tables are only created if missing, and seed rows are skipped if they already exist.

CREATE DATABASE IF NOT EXISTS quantum_gear;
USE quantum_gear;

-- Accounts created on login.html. Everyone who registers is a 'customer'; only an admin
-- can change products. Admins are made by hand (see the UPDATE at the bottom of this file).
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adds the role column to a users table made before roles existed
-- (ADD COLUMN IF NOT EXISTS works on MariaDB, the database XAMPP comes with)
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer';

-- When each user last signed in (set by PHP/users/authmain.php, shown on the admin Users tab)
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL AFTER role;

-- Wrong-password counter: 5 in a row locks the account for 5 minutes (PHP/users/authmain.php)
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_logins;

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

-- Orders placed from the cart (PHP/orders/place.php). Names, prices and the address are copied in,
-- so an order still reads correctly after a product or account is changed or deleted.
CREATE TABLE IF NOT EXISTS orders (
    order_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NULL,                         -- NULL once the account is deleted
    customer_name VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    address_label VARCHAR(50)  NOT NULL,
    address       VARCHAR(255) NOT NULL,
    subtotal      DECIMAL(10,2) NOT NULL,           -- at regular prices
    discount      DECIMAL(10,2) NOT NULL,           -- saved through sale prices
    total         DECIMAL(10,2) NOT NULL,           -- what the customer pays
    status        ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    item_id       INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    product_id    INT NULL,                         -- NULL once the product is deleted
    name          VARCHAR(150) NOT NULL,
    unit_price    DECIMAL(10,2) NOT NULL,           -- price paid for one
    regular_price DECIMAL(10,2) NOT NULL,           -- price before any sale
    qty           INT UNSIGNED NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products (product_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product reviews (PHP/products/review.php): one per customer per product, only after buying it.
-- products.rating and products.reviews are kept up to date from this table.
CREATE TABLE IF NOT EXISTS reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id    INT NULL,                            -- NULL once the account is deleted
    username   VARCHAR(50) NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL,
    comment    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,                       -- set when the customer edits their review
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (product_id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY one_review_per_customer (product_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every order email (PHP/orders/mailer.php). Project rule: emails only go to our own local
-- mailbox, so intended_for records which customer each one was really for.
CREATE TABLE IF NOT EXISTS email_log (
    email_id     INT AUTO_INCREMENT PRIMARY KEY,
    to_address   VARCHAR(100) NOT NULL,
    intended_for VARCHAR(100) NOT NULL,
    subject      VARCHAR(200) NOT NULL,
    body         TEXT NOT NULL,
    sent         TINYINT(1) NOT NULL,              -- 1 = the mail server accepted it
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Key features shown as bullet points on product.php: one feature per line
ALTER TABLE products ADD COLUMN IF NOT EXISTS features TEXT NULL AFTER description;

-- Messages sent from the form on contact.html
CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL,
    subject    ENUM('general', 'order', 'trade-in', 'build', 'other') NOT NULL DEFAULT 'general',
    message    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email addresses signed up for deal emails on hotdeals.html
CREATE TABLE IF NOT EXISTS subscribers (
    subscriber_id INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(100) NOT NULL UNIQUE,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data
INSERT IGNORE INTO categories (name, icon) VALUES
    ('Components & Storage', 'fa-memory'),
    ('Computer Systems',     'fa-desktop'),
    ('Computer Peripherals', 'fa-computer-mouse'),
    ('Gaming & VR',          'fa-gamepad'),
    ('Networking',           'fa-wifi'),
    ('Electronics',          'fa-headphones');

-- Products (images are in Images/products/; check each new image opens in a browser, a renamed .jpg will not)
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
    UNION ALL SELECT 'Gaming & VR', 'Meta Quest 3S 128GB', 'Meta',
           'All-in-one mixed reality headset with full-colour passthrough and Touch Plus controllers. Plays VR games and apps on its own, no PC or console needed.',
           449.00, NULL, 12, 'Images/products/META_Meta-Quest-3S.jpg'
    UNION ALL SELECT 'Gaming & VR', 'Nintendo Switch Console (Grey Joy-Con)', 'Nintendo',
           'Hybrid console that plays on the TV at home or in handheld mode on the go, with two detachable Joy-Con controllers for local multiplayer.',
           419.00, 449.00, 15, 'Images/products/Nintendo_Switch.jpg'
    UNION ALL SELECT 'Gaming & VR', 'PlayStation VR2', 'Sony',
           'Next-generation VR headset for PS5 with 4K HDR OLED displays, eye tracking and Sense controllers with haptic feedback and adaptive triggers.',
           549.00, 799.00, 4, 'Images/products/PlayStation_VR2.jpg'
    UNION ALL SELECT 'Computer Systems', 'QNAP TS-233 2-Bay NAS', 'QNAP',
           'Compact 2-bay network storage for home and small office backups, photo libraries and media streaming. Drives sold separately.',
           329.00, NULL, 9, 'Images/products/QNAP_TS233-NAS.jpg'
    UNION ALL SELECT 'Computer Systems', 'Synology DiskStation 4-Bay NAS', 'Synology',
           '4-bay NAS running Synology DiskStation Manager, for file sharing, automatic backups and private cloud storage across a home lab or small business. Drives sold separately.',
           899.00, 999.00, 5, 'Images/products/Synology_NAS.jpg'
    UNION ALL SELECT 'Networking', 'TRENDnet 6-Port 10G Multi-Gig Switch', 'TRENDnet',
           'Unmanaged desktop switch with multi-gigabit ports, including 10G uplinks, to speed up transfers between a NAS, workstation and router. Plug and play, fanless design.',
           349.00, NULL, 7, 'Images/products/TRENDnet_6-Port-10G-Switch.jpg'
    UNION ALL SELECT 'Networking', 'Ubiquiti UniFi Cloud Gateway Max', 'Ubiquiti',
           'Compact UniFi gateway with a built-in network controller, firewall and VPN, managed from the UniFi app. A strong core for a home or small office network.',
           389.00, NULL, 6, 'Images/products/Ubiquiti_Cloud-Gateway-Max.jpg'
    UNION ALL SELECT 'Components & Storage', 'WD Blue 1TB 3.5" Hard Drive', 'Western Digital',
           'Reliable 3.5-inch SATA hard drive for desktop PCs, ideal as a second drive for games, photos and backups.',
           69.00, 79.00, 25, 'Images/products/WD_WD-Blue-1TB-Hard-Drive.jpg'
    UNION ALL SELECT 'Components & Storage', 'WD Blue 1TB SATA SSD', 'Western Digital',
           '2.5-inch SATA solid state drive with 3D NAND, for much faster boot and load times than a hard drive. An easy upgrade for older PCs and laptops.',
           99.00, 119.00, 18, 'Images/products/WD_WD-Blue-1TB-Sata-SSD.jpg'
    UNION ALL SELECT 'Networking', 'XLTTYW Wall Mount for TP-Link Deco WiFi 7 (3-Pack)', 'XLTTYW',
           'Wall mount brackets that hold TP-Link Deco WiFi 7 mesh units off the shelf for better coverage and a tidier setup. Mounts only; Deco units not included.',
           29.90, NULL, 30, 'Images/products/XLTTYW_Deco-WiFi-7-Wall-Mount-Kit.jpg'
    UNION ALL SELECT 'Components & Storage', 'ASUS TUF Gaming B650-PLUS Motherboard', 'ASUS',
           'Durable AM5 ATX motherboard for Ryzen 7000, 8000 and 9000 processors, with DDR5 memory support and PCIe 5.0 M.2 storage for a fast, future-ready build.',
           289.00, NULL, 11, 'Images/products/ASUS_TUF-Gaming-B650-Plus.jpg'
    UNION ALL SELECT 'Components & Storage', 'Gigabyte B550 Eagle WIFI6 Motherboard', 'Gigabyte',
           'Value AM4 ATX motherboard with built-in WiFi 6 and PCIe 4.0 support, a great base for a Ryzen 5000 gaming or home office PC.',
           199.00, NULL, 9, 'Images/products/Gigabyte_B550-Eagle-WIFI6-Motherboard.jpg'
    UNION ALL SELECT 'Components & Storage', 'Gigabyte B550 Gaming X V2 Motherboard', 'Gigabyte',
           'Gaming-focused AM4 ATX motherboard with PCIe 4.0, dual M.2 slots and solid power delivery for Ryzen 5000 processors.',
           179.00, 199.00, 14, 'Images/products/Gigabyte_B550-Gaming-X-V2-Motherboard.jpg'
    UNION ALL SELECT 'Components & Storage', 'Corsair Vengeance LPX 32GB (2x16GB) DDR4-3200', 'Corsair',
           'Low-profile DDR4 desktop memory kit with an aluminium heat spreader, a simple upgrade for faster multitasking and gaming on DDR4 PCs.',
           109.00, 129.00, 22, 'Images/products/CORSAIR-Vengeance-LPX-32GB-DDR4-3200.jpg'
    UNION ALL SELECT 'Components & Storage', 'Crucial Pro 64GB (2x32GB) DDR5-6400 Kit', 'Crucial',
           'High-capacity DDR5 desktop memory kit for heavy multitasking, content creation and gaming, with one-click overclocking profiles.',
           329.00, NULL, 8, 'Images/products/CRUCIAL_Pro-DDR5-RAM-64GB-Kit-(2x32GB)-6400MHz-CL40.jpg'
    UNION ALL SELECT 'Components & Storage', 'Lexar 16GB DDR5-5600 Desktop Memory', 'Lexar',
           'Single 16GB DDR5 desktop memory module, an affordable way to add memory to a DDR5 PC or start a budget build.',
           79.00, NULL, 3, 'Images/products/Lexar_UDIMM-RAM-DDR5-16GB-5600MHz.jpg'
    UNION ALL SELECT 'Components & Storage', 'Corsair RM850x Shift 850W Power Supply', 'Corsair',
           'Fully modular 850W 80 PLUS Gold power supply with side-mounted connectors that make cable management much easier, ready for the latest graphics cards.',
           229.00, 259.00, 10, 'Images/products/CORSAIR_RM850x-Shift-PSU.jpg'
    UNION ALL SELECT 'Components & Storage', 'ARCTIC Liquid Freezer III Pro 360 CPU Cooler', 'ARCTIC',
           '360mm all-in-one liquid CPU cooler that keeps high-end processors cool and quiet under heavy gaming and rendering loads.',
           189.00, NULL, 9, 'Images/products/ARCTIC_Liquid-Freezer-III-Pro-360.jpg'
    UNION ALL SELECT 'Components & Storage', 'NZXT H5 Flow Mid-Tower Case', 'NZXT',
           'Compact ATX mid-tower case with a perforated front panel for strong airflow and a tempered glass side to show off your build.',
           119.00, 139.00, 12, 'Images/products/NZXT_H5-Flow.jpg'
    UNION ALL SELECT 'Components & Storage', 'SanDisk Extreme PRO 128GB SDXC UHS-I Card', 'SanDisk',
           'Fast SD card for cameras and drones, made for burst photography and smooth 4K video recording.',
           45.00, NULL, 40, 'Images/products/SanDisk_128GB-Extreme-PRO-SDXC-UHS-I-Memory-Card.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Belkin Thunderbolt 5 Cable', 'Belkin',
           'USB-C Thunderbolt 5 cable for the fastest docks, external SSDs and displays, with high-power laptop charging.',
           99.00, NULL, 20, 'Images/products/Belkin_Thunderbolt-5-Cable.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Keychron V3 Max TKL Wireless Mechanical Keyboard', 'Keychron',
           'Tenkeyless mechanical keyboard with a smooth gasket-mounted feel, hot-swappable switches and three ways to connect.',
           159.00, NULL, 10, 'Images/products/Keychron_V3-Max-TKL-Wireless-Mechanical-Keyboard.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Logitech MX Keys Wireless Keyboard', 'Logitech',
           'Quiet, full-size wireless keyboard with smart backlighting, made for long days of typing across up to three devices.',
           159.00, NULL, 14, 'Images/products/Logitech_MX-Keys-Wireless-Keyboard.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Logitech MX Master 3S Wireless Mouse', 'Logitech',
           'Ergonomic productivity mouse with near-silent clicks, a fast MagSpeed scroll wheel and precise tracking on almost any surface.',
           129.00, 149.00, 18, 'Images/products/Logitech_MX-Master-3S.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Logitech G PRO X SUPERLIGHT 2c Wireless Gaming Mouse', 'Logitech',
           'Ultra-light wireless esports mouse with Logitech''s HERO 2 sensor and fast, lag-free LIGHTSPEED wireless.',
           219.00, 249.00, 5, 'Images/products/Logitech_G-PRO-X-SUPERLIGHT-2c-Wireless-Gaming-Mouse.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Logitech C922n Full HD Webcam', 'Logitech',
           'Full HD webcam for streaming, video calls and online classes, with autofocus and a tripod included.',
           129.00, NULL, 16, 'Images/products/Logitech_C922n-Full-HD-1080P-Webcam.jpg'
    UNION ALL SELECT 'Computer Peripherals', 'Logitech Zone 300 Wireless Headset', 'Logitech',
           'Lightweight Bluetooth headset with a noise-cancelling microphone, for clear calls in a busy office or at home.',
           129.00, NULL, 11, 'Images/products/Logitech_Zone-300-Wireless-Bluetooth-Headset-with-Microphone-with-Noise-Cancellation.jpg'
    UNION ALL SELECT 'Electronics', 'Google TV Streamer 4K', 'Google',
           'Streaming box that brings Google TV, your favourite apps and smart home controls to any TV, in 4K HDR.',
           139.00, NULL, 15, 'Images/products/Google_TV-Streamer-4K.jpg'
    UNION ALL SELECT 'Electronics', 'Sennheiser MOMENTUM 4 Wireless Special Edition', 'Sennheiser',
           'Premium wireless headphones with adaptive noise cancellation, detailed Sennheiser sound and very long battery life.',
           449.00, 549.00, 4, 'Images/products/Sennheiser_MOMENTUM-4-Wireless-Special-Edition-Headphones.jpg'
    UNION ALL SELECT 'Electronics', 'Ceptics UK Travel Plug Adapter (Type G) 3-Pack', 'Ceptics',
           'Type G plug adapters, the plug used in Singapore and the UK, so visitors can charge devices bought overseas.',
           19.90, NULL, 35, 'Images/products/Ceptics_UK-Travel-Plug-Adapter-(Type-G)-3-Pack.jpg'
    UNION ALL SELECT 'Electronics', 'Duracell AAA Alkaline Batteries (20-Pack)', 'Duracell',
           'Long-lasting AAA batteries for remotes, wireless mice and keyboards, game controllers and everyday devices.',
           19.90, 24.90, 50, 'Images/products/Duracell_AAA-Alkaline-Batteries-(Pack-of-20-Batteries).jpg'
) AS p
JOIN categories c ON c.name = p.category;


-- Key features for the products above (only filled in when still empty, so admin edits are kept)
UPDATE products SET features = 'AMD RDNA 4 architecture
16GB GDDR6 memory
AMD FSR 4 upscaling support
Hardware-accelerated ray tracing
PCIe 5.0 x16 interface' WHERE name = 'AMD Radeon RX 9060 XT' AND features IS NULL;
UPDATE products SET features = 'AMD RDNA 4 architecture with 64 compute units
16GB GDDR6 memory
AMD FSR 4 upscaling support
Built for high-refresh 1440p and 4K gaming
PCIe 5.0 x16 interface' WHERE name = 'AMD Radeon RX 9070 XT' AND features IS NULL;
UPDATE products SET features = 'NVIDIA Blackwell architecture
16GB GDDR7 memory
DLSS 4 with Multi Frame Generation
Fifth-generation Tensor Cores for AI workloads
Compact double flow-through cooler' WHERE name = 'NVIDIA GeForce RTX 5080 Founders Edition' AND features IS NULL;
UPDATE products SET features = '8 cores, 16 threads
Up to 5.2 GHz boost clock
96MB L3 cache with AMD 3D V-Cache
AM5 socket, DDR5 and PCIe 5.0 support
Unlocked for overclocking' WHERE name = 'AMD Ryzen 7 9800X3D Processor' AND features IS NULL;
UPDATE products SET features = '16 cores, 32 threads
Up to 5.7 GHz boost clock
128MB L3 cache with AMD 3D V-Cache
AM5 socket, DDR5 and PCIe 5.0 support
Unlocked for overclocking' WHERE name = 'AMD Ryzen 9 9950X3D Processor' AND features IS NULL;
UPDATE products SET features = '24-inch Full HD (1920 x 1080) display
100Hz refresh rate for smoother motion
Slim bezels for multi-monitor setups
Tilt-adjustable stand
Low blue light mode for long sessions' WHERE name = 'Lenovo 24" 100Hz Monitor' AND features IS NULL;
UPDATE products SET features = 'Pro multi-camera system
Super Retina XDR display with ProMotion
Latest Apple silicon
All-day battery life
USB-C charging' WHERE name = 'Apple iPhone 18 Pro' AND features IS NULL;
UPDATE products SET features = 'Sleek, lightweight design
Powerful Apple silicon
Versatile dual-camera system
All-day battery life
USB-C charging' WHERE name = 'Apple iPhone Duo' AND features IS NULL;
UPDATE products SET features = '11-inch Liquid Retina display
Touch ID for secure unlocking
USB-C connector
Works with Apple Pencil (USB-C)
All-day battery life' WHERE name = 'Apple iPad 11"' AND features IS NULL;
UPDATE products SET features = '55-inch 4K Ultra HD (3840 x 2160) display
HDR support for brighter, more vivid colour
Smart TV with built-in streaming apps
Multiple HDMI inputs for consoles and set-top boxes
Slim design that suits wall mounting' WHERE name = 'Sharp 55" 4K UHD TV' AND features IS NULL;
UPDATE products SET features = '128GB storage
Full-colour mixed reality passthrough
Snapdragon XR2 Gen 2 processor
Touch Plus controllers included
Standalone: no PC or console needed' WHERE name = 'Meta Quest 3S 128GB' AND features IS NULL;
UPDATE products SET features = 'Play in TV, tabletop or handheld mode
6.2-inch touchscreen
Two detachable Joy-Con controllers
Up to 9 hours of battery life
Dock included for TV play' WHERE name = 'Nintendo Switch Console (Grey Joy-Con)' AND features IS NULL;
UPDATE products SET features = '4K HDR OLED displays (2000 x 2040 per eye)
Eye tracking for more natural control
Sense controllers with haptic feedback and adaptive triggers
Around 110-degree field of view
Single USB-C cable connection to PS5' WHERE name = 'PlayStation VR2' AND features IS NULL;
UPDATE products SET features = '2 drive bays for 3.5" or 2.5" SATA drives
Quad-core ARM processor
2GB RAM
Gigabit Ethernet
Snapshots and backups with QNAP QTS' WHERE name = 'QNAP TS-233 2-Bay NAS' AND features IS NULL;
UPDATE products SET features = '4 drive bays
Synology DiskStation Manager (DSM) operating system
Automatic backups and snapshots
Synology Photos and Drive apps for a private cloud
Suits home labs and small businesses' WHERE name = 'Synology DiskStation 4-Bay NAS' AND features IS NULL;
UPDATE products SET features = '6 multi-gigabit Ethernet ports
10G ports for NAS and workstation uplinks
Unmanaged, plug and play
Fanless, silent design
Durable metal housing' WHERE name = 'TRENDnet 6-Port 10G Multi-Gig Switch' AND features IS NULL;
UPDATE products SET features = 'Built-in UniFi Network controller
2.5 GbE ports for fast wired connections
Firewall, VPN and traffic insights
Managed from the UniFi app
Compact, quiet desktop design' WHERE name = 'Ubiquiti UniFi Cloud Gateway Max' AND features IS NULL;
UPDATE products SET features = '1TB capacity
3.5-inch SATA 6Gb/s interface
7200 RPM spindle speed
64MB cache
2-year limited warranty' WHERE name = 'WD Blue 1TB 3.5" Hard Drive' AND features IS NULL;
UPDATE products SET features = '1TB capacity with 3D NAND
Up to 560MB/s read and 530MB/s write
2.5-inch, 7mm SATA form factor
Much faster boot and load times than a hard drive
5-year limited warranty' WHERE name = 'WD Blue 1TB SATA SSD' AND features IS NULL;
UPDATE products SET features = 'Fits TP-Link Deco WiFi 7 mesh units
3 wall mounts per pack
Lifts units off the shelf for better WiFi coverage
Keeps cables tidy
Mounts only: Deco units not included' WHERE name = 'XLTTYW Wall Mount for TP-Link Deco WiFi 7 (3-Pack)' AND features IS NULL;
UPDATE products SET features = 'AMD AM5 socket for Ryzen 7000, 8000 and 9000 processors
ATX form factor
DDR5 memory support
PCIe 5.0 M.2 slot for the fastest SSDs
2.5Gb Ethernet
TUF Gaming durability and cooling' WHERE name = 'ASUS TUF Gaming B650-PLUS Motherboard' AND features IS NULL;
UPDATE products SET features = 'AMD AM4 socket for Ryzen 3000 and 5000 processors
ATX form factor
DDR4 memory support
Built-in WiFi 6 and Bluetooth
PCIe 4.0 M.2 slot' WHERE name = 'Gigabyte B550 Eagle WIFI6 Motherboard' AND features IS NULL;
UPDATE products SET features = 'AMD AM4 socket for Ryzen 3000 and 5000 processors
ATX form factor
DDR4 memory support
Dual M.2 slots, including PCIe 4.0
RGB lighting with Gigabyte RGB Fusion' WHERE name = 'Gigabyte B550 Gaming X V2 Motherboard' AND features IS NULL;
UPDATE products SET features = '32GB kit (2 x 16GB)
DDR4-3200 speed
Low-profile design fits under large CPU coolers
Aluminium heat spreader
Intel XMP 2.0 support' WHERE name = 'Corsair Vengeance LPX 32GB (2x16GB) DDR4-3200' AND features IS NULL;
UPDATE products SET features = '64GB kit (2 x 32GB)
DDR5-6400, CL40
Intel XMP 3.0 and AMD EXPO profiles
Black aluminium heat spreaders
Made by Micron' WHERE name = 'Crucial Pro 64GB (2x32GB) DDR5-6400 Kit' AND features IS NULL;
UPDATE products SET features = '16GB single module
DDR5-5600 speed
Standard desktop UDIMM
Low 1.1V operating voltage
Plug and play on DDR5 motherboards' WHERE name = 'Lexar 16GB DDR5-5600 Desktop Memory' AND features IS NULL;
UPDATE products SET features = '850W, 80 PLUS Gold efficiency
Fully modular cables
Side-mounted connectors for easier cable routing
ATX 3.0 with a 12VHPWR cable for the latest graphics cards
Quiet fan with Zero RPM mode
10-year warranty' WHERE name = 'Corsair RM850x Shift 850W Power Supply' AND features IS NULL;
UPDATE products SET features = '360mm radiator with three 120mm fans\nBuilt-in VRM fan cools the motherboard power stage\nSupports AMD AM5/AM4 and Intel LGA1700/LGA1851\nLow-noise pump and fan curve\n6-year warranty' WHERE name = 'ARCTIC Liquid Freezer III Pro 360 CPU Cooler' AND features IS NULL;
UPDATE products SET features = 'ATX mid-tower with tempered glass side panel\nPerforated front panel for high airflow\nTwo 120mm fans included\nRoom for a 360mm radiator\nClean cable management channels' WHERE name = 'NZXT H5 Flow Mid-Tower Case' AND features IS NULL;
UPDATE products SET features = '128GB capacity\nRead speeds up to 200MB/s\nUHS-I, U3 and V30 rated for 4K video\nBuilt for burst photography\nTemperature, water and shock resistant' WHERE name = 'SanDisk Extreme PRO 128GB SDXC UHS-I Card' AND features IS NULL;
UPDATE products SET features = 'Thunderbolt 5 data speeds up to 80Gbps\nUp to 240W charging\nDrives high-resolution external displays\nWorks with Thunderbolt 4, USB4 and USB-C devices\nDurable braided design' WHERE name = 'Belkin Thunderbolt 5 Cable' AND features IS NULL;
UPDATE products SET features = 'Tenkeyless (TKL) layout saves desk space\n2.4GHz wireless, Bluetooth or USB-C wired\nHot-swappable switches\nGasket mount for a softer typing feel\nFully programmable with QMK/VIA' WHERE name = 'Keychron V3 Max TKL Wireless Mechanical Keyboard' AND features IS NULL;
UPDATE products SET features = 'Full-size layout with number pad\nSmart backlighting that lights up as your hands approach\nSwitch between up to 3 devices\nBluetooth or USB receiver\nRechargeable over USB-C' WHERE name = 'Logitech MX Keys Wireless Keyboard' AND features IS NULL;
UPDATE products SET features = '8,000 DPI sensor that tracks even on glass\nQuiet clicks\nMagSpeed electromagnetic scroll wheel\nSwitch between up to 3 devices\nRechargeable over USB-C' WHERE name = 'Logitech MX Master 3S Wireless Mouse' AND features IS NULL;
UPDATE products SET features = 'Ultra-lightweight design for fast flicks\nHERO 2 gaming sensor\nLIGHTSPEED wireless connection\nLong battery life\nRechargeable over USB-C' WHERE name = 'Logitech G PRO X SUPERLIGHT 2c Wireless Gaming Mouse' AND features IS NULL;
UPDATE products SET features = '1080p at 30fps or 720p at 60fps\nAutofocus and automatic light correction\nBuilt-in stereo microphones\nTripod included\nPlug-and-play USB connection' WHERE name = 'Logitech C922n Full HD Webcam' AND features IS NULL;
UPDATE products SET features = 'Noise-cancelling boom microphone\nBluetooth wireless\nWorks with Teams, Zoom and Google Meet\nLightweight, comfortable fit for all-day wear\nRechargeable battery' WHERE name = 'Logitech Zone 300 Wireless Headset' AND features IS NULL;
UPDATE products SET features = '4K HDR with Dolby Vision and Dolby Atmos\nGoogle TV with recommendations across your apps\nVoice remote with Google Assistant\nEthernet port for a stable connection\nWorks as a smart home hub' WHERE name = 'Google TV Streamer 4K' AND features IS NULL;
UPDATE products SET features = 'Adaptive noise cancellation\nUp to 60 hours of battery life\n42mm drivers tuned by Sennheiser\nCustomisable sound in the Smart Control app\nComfortable, foldable design' WHERE name = 'Sennheiser MOMENTUM 4 Wireless Special Edition' AND features IS NULL;
UPDATE products SET features = 'Pack of 3 adapters\nType G plug, used in Singapore and the UK\nAccepts plugs from many other countries\nCompact for travel\nAdapter only: does not convert voltage' WHERE name = 'Ceptics UK Travel Plug Adapter (Type G) 3-Pack' AND features IS NULL;
UPDATE products SET features = '20 AAA alkaline batteries\nLong-lasting power for everyday devices\nUp to 10 years in storage\nIdeal for remotes, mice and controllers\nRecyclable packaging' WHERE name = 'Duracell AAA Alkaline Batteries (20-Pack)' AND features IS NULL;

-- Make your own account an admin: register on login.html first, then run this in phpMyAdmin
-- with your username. No page on the website can do this, so customers can never become admins.
-- UPDATE users SET role = 'admin' WHERE username = 'your_username';
