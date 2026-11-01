<?php
// Shop page, built on the server: the products in the shop, searched, filtered and sorted by MySQL.
// The search box, sort menu and filters belong to one GET form, so every choice is in the URL:
//   shop.php?q=<words>&category[]=<name>&brand[]=<name>&min=<price>&max=<price>&sort=<order>
// The header search on every page and the category links in the menu arrive the same way
// (shop.php?q=... and shop.php?category=...). JS/shop.js only applies a change straight away.
// Add to Cart is a form sent to PHP/shopping_cart/cart.php (addToCartForm() in PHP/products/render.php).

require_once __DIR__ . "/PHP/db_connect.php";
require_once __DIR__ . "/PHP/page.php";              // the session, header (cart, sign-in, search) and messages
require_once __DIR__ . "/PHP/products/render.php";   // h(), productCard(), bestSellers()

// Sort menu: value in the URL => [label, ORDER BY]. Only these strings ever reach the SQL.
const SORTS = [
    "featured" => ["Featured", "p.product_id"],
    "name-asc" => ["Alphabetically, A-Z", "p.name ASC"],
    "name-desc" => ["Alphabetically, Z-A", "p.name DESC"],
    "price-asc" => ["Price, low to high", "p.price ASC, p.name"],
    "price-desc" => ["Price, high to low", "p.price DESC, p.name"],
];

// Every category is listed (not only ones with products), so menu links like
// shop.php?category=Networking always have a box to tick. Brands come from the products for sale.
$allCategories = array_column(mysqli_fetch_all(mysqli_query($conn, "SELECT name FROM categories ORDER BY name")), 0);
$allBrands = array_column(mysqli_fetch_all(mysqli_query($conn,
    "SELECT DISTINCT brand FROM products WHERE is_active = 1 ORDER BY brand")), 0);

// Price slider: up to the most expensive product, rounded up to the next 100
$highest = (float) mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(MAX(price), 0) FROM products WHERE is_active = 1"))[0];
$priceMax = max(100, (int) (ceil($highest / 100) * 100));

// ---------- The visitor's choices, read from the URL ($_GET) ----------
// Anything that isn't one of the expected values is ignored, so a hand-edited URL can't break the page
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : "";
$words = preg_split('/\s+/', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY);

// Ticked boxes arrive as category[]=...&category[]=..., a menu link as category=...
$fromUrl = fn($key) => array_filter((array) ($_GET[$key] ?? []), 'is_string');
$chosenCategories = array_values(array_intersect($allCategories, $fromUrl('category')));
$chosenBrands = array_values(array_intersect($allBrands, $fromUrl('brand')));

$min = is_numeric($_GET['min'] ?? null) ? max(0, (float) $_GET['min']) : 0;
$max = is_numeric($_GET['max'] ?? null) ? min($priceMax, max(0, (float) $_GET['max'])) : $priceMax;
if ($min > $max) {
    [$min, $max] = [$max, $min];
}

$sort = $_GET['sort'] ?? "featured";
if (!is_string($sort) || !array_key_exists($sort, SORTS)) {
    $sort = "featured";
}

// ---------- The matching products, filtered and sorted by MySQL ----------
// Each filter adds a condition with ? placeholders, and its values go to bind_param in the same order
$where = ["p.is_active = 1", "p.price BETWEEN ? AND ?"];
$types = "dd";
$values = [$min, $max];

if ($chosenCategories) {
    $where[] = "c.name IN (" . implode(",", array_fill(0, count($chosenCategories), "?")) . ")";
    $types .= str_repeat("s", count($chosenCategories));
    array_push($values, ...$chosenCategories);
}
if ($chosenBrands) {
    $where[] = "p.brand IN (" . implode(",", array_fill(0, count($chosenBrands), "?")) . ")";
    $types .= str_repeat("s", count($chosenBrands));
    array_push($values, ...$chosenBrands);
}
// Every word typed must appear somewhere in the name, brand, category or description.
// addcslashes stops % and _ in the search acting as LIKE wildcards.
foreach ($words as $word) {
    $where[] = "CONCAT_WS(' ', p.name, p.brand, c.name, p.description) LIKE ?";
    $types .= "s";
    $values[] = "%" . addcslashes($word, "%_\\") . "%";
}

$stmt = mysqli_prepare($conn,
    "SELECT p.*, c.name AS category, c.icon
     FROM products p
     JOIN categories c USING (category_id)
     WHERE " . implode(" AND ", $where) . "
     ORDER BY " . SORTS[$sort][1]);
mysqli_stmt_bind_param($stmt, $types, ...$values);
mysqli_stmt_execute($stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Tag the top sellers (units sold in orders that weren't cancelled)
$topIds = array_map('intval', array_column(bestSellers($conn), 'product_id'));
foreach ($products as &$p) {
    $p['bestseller'] = in_array((int) $p['product_id'], $topIds, true);
}
unset($p);
mysqli_close($conn);

// The current choices that differ from the defaults, for building the "Reset selection" links
$choices = array_filter([
    "q" => $q,
    "category" => $chosenCategories,
    "brand" => $chosenBrands,
    "min" => $min > 0 ? $min : null,
    "max" => $max < $priceMax ? $max : null,
    "sort" => $sort === "featured" ? null : $sort,
]);

// This page's URL with the current choices, minus the ones named in $drop
function shopUrl($choices, $drop)
{
    $query = http_build_query(array_diff_key($choices, array_flip($drop)));
    return "shop.php" . ($query === "" ? "" : "?$query");
}

$count = count($products);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/shop.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/shop.js" defer></script>
    <title>Shop | Quantum Gear</title>
</head>
<body>
    <header>
        <nav>
            <div class="nav-top">
                <img src="Images/logo.png" alt="Logo" class="logo">
<?php searchBox(); ?>
                <!-- Light / dark slider: the knob sits under the active mode's icon (JS/theme.js) -->
                <button type="button" class="theme-toggle" id="themeToggle" role="switch" aria-checked="false" aria-label="Light mode">
                    <span class="theme-knob"></span>
                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                    <i class="fa-solid fa-moon" aria-hidden="true"></i>
                </button>
                <a href="order.php" class="cart-icon" aria-label="Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="cart-count"><?= cartCount() ?></span>
                </a>
                <?php userMenu(); ?>
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
                                <li><a href="contact.php?subject=trade-in"><i class="fa-solid fa-rotate"></i> Trade In</a></li>
                            </ul>
                            <p class="mega-menu-heading">Help me build&hellip;</p>
                            <ul class="mega-menu-plain">
                                <li><a href="pc-builder.php">Desktop PC (PC Builder)</a></li>
                                <li><a href="shop.php?category=Computer%20Systems">Workstation</a></li>
                                <li><a href="shop.php?category=Networking">Home Network</a></li>
                                <li><a href="shop.php?category=Gaming%20%26%20VR">Game Room</a></li>
                                <li><a href="contact.php?subject=build">Server / NAS (ask us)</a></li>
                            </ul>
                        </div>
                        <div class="mega-menu-col mega-menu-products">
                            <p class="mega-menu-heading">All Products</p>
                            <!-- Same categories as the categories table in database/schema.sql -->
                            <ul>
                                <li><a href="shop.php?category=Components%20%26%20Storage"><span><i class="fa-solid fa-memory"></i> Components &amp; Storage</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.php?category=Computer%20Systems"><span><i class="fa-solid fa-desktop"></i> Computer Systems</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.php?category=Computer%20Peripherals"><span><i class="fa-solid fa-computer-mouse"></i> Computer Peripherals</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.php?category=Gaming%20%26%20VR"><span><i class="fa-solid fa-gamepad"></i> Gaming &amp; VR</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.php?category=Networking"><span><i class="fa-solid fa-wifi"></i> Networking</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.php?category=Electronics"><span><i class="fa-solid fa-headphones"></i> Electronics</span><i class="fa-solid fa-chevron-right"></i></a></li>
                                <li><a href="shop.php"><span><i class="fa-solid fa-store"></i> Shop All</span><i class="fa-solid fa-chevron-right"></i></a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li><a href="index.php">Home</a></li>
                <li><a href="shop.php">Shop</a></li>
                <li><a href="hotdeals.php">Hot Deals</a></li>
                <li><a href="pc-builder.php">PC Builder</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </nav>
    </header>
<?php flashMessage(); ?>
    <main class="shop">
        <!-- The search box, sort menu and filters are spread over the page, so they join this
             form with form="shopForm" instead of sitting inside it -->
        <form id="shopForm" action="shop.php" method="get"></form>
        <div class="shop-header">
            <h2>Get the products as your needs</h2>
            <a class="shop-reset" href="shop.php">Reset Filters</a>
        </div>
        <div class="shop-toolbar">
            <button type="button" class="toolbar-filter" id="filterToggle" aria-expanded="true" aria-controls="shopFilters">
                Filter <i class="fa-solid fa-sliders"></i>
            </button>
            <label class="toolbar-sort">
                <span>Sort by</span>
                <select name="sort" id="sortSelect" form="shopForm">
<?php foreach (SORTS as $value => [$label]): ?>
                    <option value="<?= $value ?>"<?= $value === $sort ? " selected" : "" ?>><?= $label ?></option>
<?php endforeach; ?>
                </select>
            </label>
            <div class="toolbar-search">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="search" name="q" id="shopSearch" form="shopForm" value="<?= h($q) ?>" placeholder="Search products..." aria-label="Search products">
            </div>
            <span class="toolbar-count" id="productCount"><?= $count ?> product<?= $count === 1 ? "" : "s" ?></span>
        </div>
        <div class="shop-body">
            <aside class="shop-filters" id="shopFilters">
                <div class="filter-group">
                    <button type="button" class="filter-heading" aria-expanded="true">
                        Price <i class="fa-solid fa-chevron-up"></i>
                    </button>
                    <div class="filter-content">
                        <div class="price-inputs">
                            <label>$ <input type="number" name="min" id="priceMinInput" form="shopForm" min="0" max="<?= $priceMax ?>" step="any" value="<?= $min ?>" aria-label="Minimum price"></label>
                            <span>-</span>
                            <label>$ <input type="number" name="max" id="priceMaxInput" form="shopForm" min="0" max="<?= $priceMax ?>" step="any" value="<?= $max ?>" aria-label="Maximum price"></label>
                        </div>
                        <!-- The sliders mirror the two boxes above; only the boxes are sent -->
                        <div class="price-slider">
                            <div class="price-slider-fill" id="priceFill"></div>
                            <input type="range" id="priceMinRange" min="0" max="<?= $priceMax ?>" step="10" value="<?= $min ?>" aria-label="Minimum price">
                            <input type="range" id="priceMaxRange" min="0" max="<?= $priceMax ?>" step="10" value="<?= $max ?>" aria-label="Maximum price">
                        </div>
                        <a class="filter-reset" href="<?= h(shopUrl($choices, ["min", "max"])) ?>">Reset selection</a>
                    </div>
                </div>
                <div class="filter-group">
                    <button type="button" class="filter-heading" aria-expanded="true">
                        Product Categories <i class="fa-solid fa-chevron-up"></i>
                    </button>
                    <div class="filter-content">
                        <div class="filter-options" id="categoryFilters">
<?php foreach ($allCategories as $category): ?>
                            <label class="filter-option">
                                <input type="checkbox" name="category[]" value="<?= h($category) ?>" form="shopForm"<?= in_array($category, $chosenCategories, true) ? " checked" : "" ?>>
                                <span><?= h($category) ?></span>
                            </label>
<?php endforeach; ?>
                        </div>
                        <a class="filter-reset" href="<?= h(shopUrl($choices, ["category"])) ?>">Reset selection</a>
                    </div>
                </div>
                <div class="filter-group">
                    <button type="button" class="filter-heading" aria-expanded="true">
                        Brands <i class="fa-solid fa-chevron-up"></i>
                    </button>
                    <div class="filter-content">
                        <div class="filter-options" id="brandFilters">
<?php foreach ($allBrands as $brand): ?>
                            <label class="filter-option">
                                <input type="checkbox" name="brand[]" value="<?= h($brand) ?>" form="shopForm"<?= in_array($brand, $chosenBrands, true) ? " checked" : "" ?>>
                                <span><?= h($brand) ?></span>
                            </label>
<?php endforeach; ?>
                        </div>
                        <a class="filter-reset" href="<?= h(shopUrl($choices, ["brand"])) ?>">Reset selection</a>
                    </div>
                </div>
                <!-- Changes apply on their own with JavaScript; this button is for typed prices and no-JS visitors -->
                <button type="submit" form="shopForm" class="btn filter-apply">Apply filters</button>
            </aside>
            <section class="product-grid" id="productGrid">
<?php if ($products): ?>
<?php foreach ($products as $p) { productCard($p); } ?>
<?php else: ?>
                <p class="no-products">No products match <?= $words ? "your search and " : "" ?>the selected filters. <a href="shop.php">Show all products</a></p>
<?php endif; ?>
            </section>
        </div>
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
                    <li><a href="shop.php">Shop</a></li>
                    <li><a href="hotdeals.php">Hot Deals</a></li>
                    <li><a href="pc-builder.php">PC Builder</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Categories</h3>
                <ul>
                    <li><a href="shop.php?category=Components%20%26%20Storage">Components &amp; Storage</a></li>
                    <li><a href="shop.php?category=Computer%20Systems">Computer Systems</a></li>
                    <li><a href="shop.php?category=Computer%20Peripherals">Computer Peripherals</a></li>
                    <li><a href="shop.php?category=Gaming%20%26%20VR">Gaming &amp; VR</a></li>
                    <li><a href="shop.php?category=Networking">Networking</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Quantum Gear. All rights reserved.</p>
            <ul class="footer-legal">
                <li><a href="contact.php#terms">Terms &amp; Conditions</a></li>
                <li><a href="contact.php#privacy">Privacy Policy</a></li>
            </ul>
        </div>
    </footer>
</body>
</html>
