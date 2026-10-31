<?php
// Home page, built on the server so the product rows show live data from the database.
// Each row is a horizontally scrolling list of small product cards (helpers in PHP/products/render.php).

require_once __DIR__ . "/PHP/db_connect.php";
require_once __DIR__ . "/PHP/products/render.php";   // productRow(), miniCard() ...

$select = "SELECT p.*, c.name AS category, c.icon FROM products p JOIN categories c USING (category_id) WHERE p.is_active = 1";
$fetch = fn($sql) => mysqli_fetch_all(mysqli_query($conn, $sql), MYSQLI_ASSOC);

// Today's Deals: in-stock products on sale, biggest % off first
$deals = $fetch("$select AND p.stock > 0 AND p.old_price > p.price
                 ORDER BY (p.old_price - p.price) / p.old_price DESC LIMIT 10");

// New Arrivals: most recently added products
$newArrivals = $fetch("$select ORDER BY p.created_at DESC, p.product_id DESC LIMIT 10");

// Top picks: one row for every category with at least 3 products, in-stock and best-rated first
$picksByCategory = [];
foreach ($fetch("$select ORDER BY c.name, p.stock > 0 DESC, p.rating DESC, p.reviews DESC, p.product_id") as $p) {
    $picksByCategory[$p['category']][] = $p;
}
$picksByCategory = array_filter($picksByCategory, fn($items) => count($items) >= 3);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/home.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/authRedirect.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/session.js" defer></script>
    <script src="JS/carousel.js" defer></script>
    <script src="JS/home.js" defer></script>
    <script src="JS/backend.js" defer></script>
    <title>Quantum Gear | PC Parts &amp; Electronics</title>
</head>
<body>
    <header>
        <nav>
            <div class="nav-top">
                <img src="Images/logo.png" alt="Logo" class="logo">
                <div class="search-box">
                    <input type="text" placeholder="Search...">
                    <i class="fa fa-search"></i>
                </div>
                <!-- Light / dark slider: the knob sits under the active mode's icon (JS/theme.js) -->
                <button type="button" class="theme-toggle" id="themeToggle" role="switch" aria-checked="false" aria-label="Light mode">
                    <span class="theme-knob"></span>
                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                    <i class="fa-solid fa-moon" aria-hidden="true"></i>
                </button>
                <a href="order.html" class="cart-icon" aria-label="Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="cart-count">0</span>
                </a>
                <a href="login.html" class="btn" id="openLoginBtn">Login/Register</a>
            </div>
            <div class="break"></div>
            <ul class="nav-links">
                <li class="menu-item">
                    <button class="menu-toggle" id="menuToggle" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-bars"></i> Menu <i class="fa-solid fa-chevron-down chevron"></i>
                    </button>
                    <div class="mega-menu" id="megaMenu">
                        <div class="mega-menu-col mega-menu-quick">
                            <ul>
                                <li><a href="hotdeals.php#shell-shocker"><i class="fa-solid fa-bolt"></i> Shell Shocker</a></li>
                                <li><a href="hotdeals.php#clearance"><i class="fa-solid fa-tags"></i> Clearance Deals</a></li>
                                <li><a href="hotdeals.php#email-deals"><i class="fa-regular fa-envelope"></i> Email Deals</a></li>
                                <li><a href="contact.html?subject=trade-in"><i class="fa-solid fa-rotate"></i> Trade In</a></li>
                            </ul>
                            <p class="mega-menu-heading">Help me build&hellip;</p>
                            <ul class="mega-menu-plain">
                                <li><a href="shop.html?category=Components%20%26%20Storage">Desktop PC</a></li>
                                <li><a href="shop.html?category=Computer%20Systems">Workstation</a></li>
                                <li><a href="shop.html?category=Networking">Home Network</a></li>
                                <li><a href="shop.html?category=Gaming%20%26%20VR">Game Room</a></li>
                                <li><a href="contact.html?subject=build">Server / NAS (ask us)</a></li>
                            </ul>
                        </div>
                        <div class="mega-menu-col mega-menu-products">
                            <p class="mega-menu-heading">All Products</p>
                            <!-- Same categories as the categories table in database/schema.sql -->
                            <ul>
                                <li><a href="shop.html?category=Components%20%26%20Storage"><span><i class="fa-solid fa-memory"></i> Components &amp; Storage</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Computer%20Systems"><span><i class="fa-solid fa-desktop"></i> Computer Systems</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Computer%20Peripherals"><span><i class="fa-solid fa-computer-mouse"></i> Computer Peripherals</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Gaming%20%26%20VR"><span><i class="fa-solid fa-gamepad"></i> Gaming &amp; VR</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Networking"><span><i class="fa-solid fa-wifi"></i> Networking</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html?category=Electronics"><span><i class="fa-solid fa-headphones"></i> Electronics</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.html"><span><i class="fa-solid fa-store"></i> Shop All</span><i class="fa-solid fa-chevron-right"></i></a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li><a href="index.php">Home</a></li>
                <li><a href="shop.html">Shop</a></li>
                <li><a href="contact.html">Contact Us</a></li>
                <li><a href="hotdeals.php">Hot Deals</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <section class="carousel" id="heroCarousel" aria-roledescription="carousel">
            <!-- Slides are filled in by JS/carousel.js from the images in Images/carousel/ -->
            <div class="carousel-track">
            </div>
            <div class="carousel-controls">
                <button class="carousel-btn" id="carouselPrev" aria-label="Previous slide"><i class="fa-solid fa-chevron-left"></i></button>
                <span class="carousel-counter" id="carouselCounter"></span>
                <button class="carousel-btn" id="carouselNext" aria-label="Next slide"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </section>

        <section class="perks" aria-label="Why shop with us">
            <div class="perk">
                <i class="fa-solid fa-truck-fast"></i>
                <div>
                    <h3>Island-wide delivery</h3>
                    <p>Delivered to any of your saved addresses</p>
                </div>
            </div>
            <div class="perk">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <h3>Genuine products</h3>
                    <p>Official stock from the brands you trust</p>
                </div>
            </div>
            <a class="perk" href="contact.html?subject=trade-in">
                <i class="fa-solid fa-rotate"></i>
                <div>
                    <h3>Trade in old tech</h3>
                    <p>Get a quote towards your next upgrade</p>
                </div>
            </a>
            <a class="perk" href="contact.html">
                <i class="fa-solid fa-headset"></i>
                <div>
                    <h3>Real people, real help</h3>
                    <p>Mon - Sat, 10:00 AM - 7:00 PM</p>
                </div>
            </a>
        </section>

<?php productRow("dealsRowTitle", "Today's Deals", "See all deals", "hotdeals.php", $deals); ?>

        <section class="category-band" aria-labelledby="categoryBandTitle">
            <h2 id="categoryBandTitle" class="home-section-title">Shop by Category</h2>
            <div class="category-panels">
                <div class="category-panel">
                    <h3>Build Your Rig</h3>
                    <ul class="tile-grid">
                        <li><a class="tile" href="shop.html?q=graphics%20card">
                            <span class="tile-image"><img src="Images/products/Nvidia_RTX5080.svg" alt=""></span>
                            Graphics Cards
                        </a></li>
                        <li><a class="tile" href="shop.html?q=processor">
                            <span class="tile-image"><img src="Images/products/AMD_Ryzen-9-9950X3D.svg" alt=""></span>
                            Processors
                        </a></li>
                        <li><a class="tile" href="shop.html?q=monitor">
                            <span class="tile-image"><img src="Images/products/Lenovo_24''-100hz-Monitor.svg" alt=""></span>
                            Monitors
                        </a></li>
                        <li><a class="tile" href="shop.html?category=Components%20%26%20Storage">
                            <span class="tile-image tile-icon"><i class="fa-solid fa-memory"></i></span>
                            All Components
                        </a></li>
                    </ul>
                </div>

                <div class="category-panel">
                    <h3>Limited-Time Deals</h3>
                    <a class="tile tile-feature" href="hotdeals.php">
                        <span class="tile-image"><img src="Images/carousel/RTX5090.svg" alt=""></span>
                        <span>Shop Hot Deals <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                </div>

                <div class="category-panel">
                    <h3>Mobile &amp; Entertainment</h3>
                    <ul class="tile-grid">
                        <li><a class="tile" href="shop.html?q=iphone">
                            <span class="tile-image"><img src="Images/products/Apple_iPhone-18-Pro.svg" alt=""></span>
                            Phones
                        </a></li>
                        <li><a class="tile" href="shop.html?q=ipad">
                            <span class="tile-image"><img src="Images/products/Apple_iPad-11''.svg" alt=""></span>
                            Tablets
                        </a></li>
                        <li><a class="tile" href="shop.html?q=tv">
                            <span class="tile-image"><img src="Images/products/Sharp_55''-4K-TV.svg" alt=""></span>
                            TVs
                        </a></li>
                        <li><a class="tile" href="shop.html?category=Electronics">
                            <span class="tile-image tile-icon"><i class="fa-solid fa-headphones"></i></span>
                            All Electronics
                        </a></li>
                    </ul>
                </div>

                <div class="category-panel">
                    <h3>More to Explore</h3>
                    <ul class="tile-grid">
                        <li><a class="tile" href="shop.html?category=Gaming%20%26%20VR">
                            <span class="tile-image tile-icon"><i class="fa-solid fa-gamepad"></i></span>
                            Gaming &amp; VR
                        </a></li>
                        <li><a class="tile" href="shop.html?category=Networking">
                            <span class="tile-image tile-icon"><i class="fa-solid fa-wifi"></i></span>
                            Networking
                        </a></li>
                        <li><a class="tile" href="shop.html?category=Computer%20Systems">
                            <span class="tile-image tile-icon"><i class="fa-solid fa-desktop"></i></span>
                            Computer Systems
                        </a></li>
                        <li><a class="tile" href="shop.html?category=Computer%20Peripherals">
                            <span class="tile-image tile-icon"><i class="fa-solid fa-keyboard"></i></span>
                            Peripherals
                        </a></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="brand-marquee" id="brandMarquee" aria-label="Brands we carry">
            <div class="brand-track">
                <ul class="brand-list">
                    <li><img src="Images/brands/nvidia.svg" alt="NVIDIA"></li>
                    <li><img src="Images/brands/intel.svg" alt="Intel"></li>
                    <li><img src="Images/brands/amd.svg" alt="AMD"></li>
                    <li><img src="Images/brands/asus.svg" alt="ASUS"></li>
                    <li><img src="Images/brands/msi.svg" alt="MSI"></li>
                    <li><img src="Images/brands/lg.svg" alt="LG"></li>
                    <li><img src="Images/brands/hp.svg" alt="HP"></li>
                    <li><img src="Images/brands/samsung.svg" alt="Samsung"></li>
                    <li><img src="Images/brands/corsair.svg" alt="Corsair"></li>
                    <li><img src="Images/brands/seagate.svg" alt="Seagate"></li>
                    <li><img src="Images/brands/razer.svg" alt="Razer"></li>
                    <li><img src="Images/brands/acer.svg" alt="Acer"></li>
                    <li><img src="Images/brands/lenovo.svg" alt="Lenovo"></li>
                    <li><img src="Images/brands/dell.svg" alt="Dell"></li>
                </ul>
            </div>
        </section>

<?php productRow("newRowTitle", "New Arrivals", "Shop all", "shop.html", $newArrivals); ?>

<?php foreach ($picksByCategory as $category => $items): ?>
<?php productRow("picks-" . preg_replace('/[^a-z]+/', '-', strtolower($category)), "Top picks in $category", "See more", "shop.html?category=" . rawurlencode($category), array_slice($items, 0, 10)); ?>
<?php endforeach; ?>

        <section class="guides" aria-labelledby="guidesTitle">
            <h2 id="guidesTitle" class="home-section-title">Buying Guides</h2>
            <div class="guide-cards">
                <article class="guide-card">
                    <i class="fa-solid fa-microchip"></i>
                    <h3>Which graphics card do I need?</h3>
                    <p>For smooth 1080p gaming, a mainstream card like the RX 9060 XT is plenty. For high-refresh 1440p or 4K, step up to an RX 9070 XT or RTX 5080, and check that your power supply can handle it.</p>
                    <a href="shop.html?q=graphics%20card">Compare graphics cards <i class="fa-solid fa-arrow-right"></i></a>
                </article>
                <article class="guide-card">
                    <i class="fa-solid fa-memory"></i>
                    <h3>DDR4 or DDR5 memory?</h3>
                    <p>Your motherboard decides: AM4 boards (B550) take DDR4, while AM5 boards (B650) need DDR5. 32GB is the sweet spot for gaming today; go for 64GB if you edit video or run many apps at once.</p>
                    <a href="shop.html?q=ddr">Shop memory <i class="fa-solid fa-arrow-right"></i></a>
                </article>
                <article class="guide-card">
                    <i class="fa-solid fa-hard-drive"></i>
                    <h3>Do I need a NAS?</h3>
                    <p>A NAS keeps photos, files and backups in one place that every device at home can reach. A 2-bay model suits most homes; 4 bays give room to grow and protect against a drive failing.</p>
                    <a href="shop.html?category=Computer%20Systems">See NAS options <i class="fa-solid fa-arrow-right"></i></a>
                </article>
            </div>
        </section>

        <section class="services" aria-labelledby="servicesTitle">
            <h2 id="servicesTitle" class="home-section-title">Not sure where to start? We'll help you build it.</h2>
            <div class="service-cards">
                <a class="service-card" href="shop.html?category=Components%20%26%20Storage">
                    <i class="fa-solid fa-computer"></i>
                    <h3>Build a Gaming PC</h3>
                    <p>Pick a graphics card and processor that suit your games and budget, then add the parts around them.</p>
                    <span>Shop components <i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <a class="service-card" href="contact.html?subject=trade-in">
                    <i class="fa-solid fa-rotate"></i>
                    <h3>Trade In Your Old Tech</h3>
                    <p>Tell us the model and condition of your old device and we'll quote a value towards your upgrade.</p>
                    <span>Get a quote <i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <a class="service-card" href="contact.html?subject=build">
                    <i class="fa-solid fa-server"></i>
                    <h3>Servers &amp; NAS</h3>
                    <p>Need storage for a home lab or small office? Share what you need and we'll suggest a setup.</p>
                    <span>Ask our team <i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </div>
        </section>

        <section class="email-deals home-newsletter" aria-labelledby="newsletterTitle">
            <div>
                <h2 id="newsletterTitle"><i class="fa-regular fa-envelope"></i> Never miss a deal</h2>
                <p>Sign up for Email Deals and hear about new Shell Shocker and clearance offers first.</p>
            </div>
            <form class="subscribe-form" action="PHP/contact/subscribe.php" method="post">
                <label for="homeSubscribeEmail" class="visually-hidden">Email address</label>
                <input type="email" id="homeSubscribeEmail" name="email" placeholder="you@example.com" maxlength="100" required>
                <button type="submit" class="btn">Subscribe</button>
            </form>
        </section>
    </main>
    <footer>
        <div class="footer-contact">
            <div class="footer-contact-item">
                <i class="fa-solid fa-location-dot"></i>
                <div>
                    <h4>Visit Us</h4>
                    <p>50 Nanyang Ave, Singapore</p>
                </div>
            </div>
            <div class="footer-contact-item">
                <i class="fa-solid fa-phone"></i>
                <div>
                    <h4>Call Us</h4>
                    <p>+65 6123 4567</p>
                </div>
            </div>
            <div class="footer-contact-item">
                <i class="fa-regular fa-clock"></i>
                <div>
                    <h4>Working Hours</h4>
                    <p>Mon - Sat: 10:00 AM - 7:00 PM</p>
                </div>
            </div>
            <div class="footer-contact-item">
                <i class="fa-regular fa-envelope"></i>
                <div>
                    <h4>Email Us</h4>
                    <p>support@quantumgear.com</p>
                </div>
            </div>
        </div>
        <div class="footer-top">
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="shop.html">Shop</a></li>
                    <li><a href="hotdeals.php">Hot Deals</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Categories</h3>
                <ul>
                    <li><a href="shop.html?category=Components%20%26%20Storage">Components &amp; Storage</a></li>
                    <li><a href="shop.html?category=Computer%20Systems">Computer Systems</a></li>
                    <li><a href="shop.html?category=Computer%20Peripherals">Computer Peripherals</a></li>
                    <li><a href="shop.html?category=Gaming%20%26%20VR">Gaming &amp; VR</a></li>
                    <li><a href="shop.html?category=Networking">Networking</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Quantum Gear. All rights reserved.</p>
            <ul class="footer-legal">
                <li><a href="contact.html#terms">Terms &amp; Conditions</a></li>
                <li><a href="contact.html#privacy">Privacy Policy</a></li>
            </ul>
        </div>
    </footer>
</body>
</html>