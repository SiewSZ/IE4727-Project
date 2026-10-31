<?php
// Hot Deals: built on the server from the products table, so every deal is already in the HTML
// the browser receives. A deal is any product shown in the shop whose old price is higher than its price.
//   Shell Shocker   = one of the 3 biggest in-stock discounts, a different one each day
//   Almost Gone     = deals with 5 or fewer left
//   Clearance Deals = every other deal, filtered and sorted by ?category= and ?sort= in the URL
//   Bundles         = ready-made sets of products that work together
// The Email Deals form posts to PHP/contact/subscribe.php, which redirects back with ?subscribed=1.

require_once __DIR__ . "/PHP/db_connect.php";
require_once __DIR__ . "/PHP/products/render.php";   // h(), money(), productCard(), productRow() ...

const LOW_STOCK = 5;

$active = mysqli_fetch_all(mysqli_query($conn,
    "SELECT p.*, c.name AS category, c.icon
     FROM products p
     JOIN categories c USING (category_id)
     WHERE p.is_active = 1
     ORDER BY p.stock > 0 DESC, (COALESCE(p.old_price, p.price) - p.price) / COALESCE(p.old_price, p.price) DESC, p.name"),
    MYSQLI_ASSOC);
mysqli_close($conn);

// Deals are already sorted: in stock first, then biggest % off
$deals = array_values(array_filter($active, 'isOnSale'));

// ---------- Shell Shocker: rotates at midnight through the top 3 in-stock deals ----------
$inStockDeals = array_values(array_filter($deals, fn($p) => $p['stock'] > 0));
$shellShocker = null;
if ($inStockDeals) {
    $shellShocker = $inStockDeals[(int) date('z') % min(3, count($inStockDeals))];   // date('z') = day of the year
    $deals = array_values(array_filter($deals, fn($p) => $p['product_id'] !== $shellShocker['product_id']));
}

// ---------- Almost Gone ----------
$almostGone = array_values(array_filter($deals, fn($p) => $p['stock'] > 0 && $p['stock'] <= LOW_STOCK));

// ---------- Clearance filters (?category= and ?sort=) ----------
$categoryCounts = array_count_values(array_column($deals, 'category'));
ksort($categoryCounts);

$chosenCategory = $_GET['category'] ?? '';
if (!isset($categoryCounts[$chosenCategory])) {
    $chosenCategory = '';   // unknown or empty category: show every deal
}

$sortOptions = [
    "discount" => "Biggest % off",
    "saving" => "Biggest saving",
    "price-asc" => "Price: low to high",
    "price-desc" => "Price: high to low",
];
$sort = $_GET['sort'] ?? 'discount';
if (!isset($sortOptions[$sort])) {
    $sort = 'discount';
}

$clearance = $chosenCategory === ''
    ? $deals
    : array_values(array_filter($deals, fn($p) => $p['category'] === $chosenCategory));

// In-stock deals always come first, then the chosen order
usort($clearance, function ($a, $b) use ($sort) {
    $stock = ($b['stock'] > 0) <=> ($a['stock'] > 0);
    if ($stock !== 0) {
        return $stock;
    }
    return match ($sort) {
        "saving" => ($b['old_price'] - $b['price']) <=> ($a['old_price'] - $a['price']),
        "price-asc" => $a['price'] <=> $b['price'],
        "price-desc" => $b['price'] <=> $a['price'],
        default => percentOff($b) <=> percentOff($a),
    };
});

// Link to this page with a category and sort, jumping back down to the Clearance section
function dealsUrl($category, $sort)
{
    $query = array_filter(["category" => $category, "sort" => $sort === "discount" ? "" : $sort]);
    return "hotdeals.php" . ($query ? "?" . http_build_query($query) : "") . "#clearance";
}

// ---------- Bundles: products that work together, by name ----------
// A bundle is only shown while every product in it is in the shop and in stock.
$bundleIdeas = [
    ["AM5 Gaming Build", "fa-gamepad", "The core of a fast, future-ready gaming PC: add a graphics card, case and SSD.",
        ["AMD Ryzen 7 9800X3D Processor", "ASUS TUF Gaming B650-PLUS Motherboard", "Crucial Pro 64GB (2x32GB) DDR5-6400 Kit", "Corsair RM850x Shift 850W Power Supply"]],
    ["AM4 Upgrade Kit", "fa-arrow-trend-up", "Give a Ryzen 3000 or 5000 PC new life with a fresh board, more memory and fast storage.",
        ["Gigabyte B550 Gaming X V2 Motherboard", "Corsair Vengeance LPX 32GB (2x16GB) DDR4-3200", "WD Blue 1TB SATA SSD"]],
    ["Home Lab Storage", "fa-server", "One place for every backup, photo and film, with fast wired networking to reach it.",
        ["Synology DiskStation 4-Bay NAS", "WD Blue 1TB 3.5\" Hard Drive", "TRENDnet 6-Port 10G Multi-Gig Switch"]],
];

$byName = array_column($active, null, 'name');
$bundles = [];
foreach ($bundleIdeas as [$title, $icon, $blurb, $names]) {
    $items = array_map(fn($name) => $byName[$name] ?? null, $names);
    if (in_array(null, $items, true) || array_filter($items, fn($p) => $p['stock'] == 0)) {
        continue;
    }
    $bundles[] = [
        "title" => $title,
        "icon" => $icon,
        "blurb" => $blurb,
        "items" => $items,
        "total" => array_sum(array_column($items, 'price')),
        "saving" => array_sum(array_map(fn($p) => isOnSale($p) ? $p['old_price'] - $p['price'] : 0, $items)),
    ];
}

$subscribed = ($_GET['subscribed'] ?? '') === '1';
$subscribeError = isset($_GET['subscribe_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/shop.css">
    <link rel="stylesheet" href="CSS/home.css">
    <link rel="stylesheet" href="CSS/hotdeals.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/session.js" defer></script>
    <script src="JS/products.js" defer></script>
    <script src="JS/home.js" defer></script>
    <script src="JS/hotdeals.js" defer></script>
    <title>Hot Deals | Quantum Gear</title>
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
    <main class="deals-page">
        <section class="page-hero">
            <img src="Images/Logo.png" alt="Quantum Gear logo" class="page-hero-logo">
            <div>
                <h1><i class="fa-solid fa-fire"></i> Hot Deals</h1>
                <p>Every product on sale right now, biggest discount first. Prices come straight from our stock list, so what you see here is what you pay at checkout.</p>
            </div>
        </section>

        <section class="shell-shocker" id="shell-shocker">
            <div class="deals-heading">
                <h2><i class="fa-solid fa-bolt"></i> Shell Shocker</h2>
                <p>Our biggest discount today. Offer ends in <strong class="countdown" id="countdown">--:--:--</strong></p>
            </div>
<?php if ($shellShocker): ?>
            <article class="shell-shocker-card" data-id="<?= (int) $shellShocker['product_id'] ?>">
                <div class="shell-shocker-image<?= $shellShocker['image_path'] ? "" : " no-image" ?>">
                    <?= productImage($shellShocker) ?>
                    <span class="sale-badge">-<?= percentOff($shellShocker) ?>%</span>
                </div>
                <div class="shell-shocker-info">
                    <p class="product-category"><?= h($shellShocker['category']) ?> &middot; <?= h($shellShocker['brand']) ?></p>
                    <h3><a href="<?= productUrl($shellShocker) ?>"><?= h($shellShocker['name']) ?></a></h3>
                    <p class="shell-shocker-desc"><?= h($shellShocker['description']) ?></p>
                    <p class="shell-shocker-price">
                        <?= money($shellShocker['price']) ?>
                        <s><?= money($shellShocker['old_price']) ?></s>
                    </p>
                    <p class="deal-saving"><i class="fa-solid fa-tag"></i> You save <?= money($shellShocker['old_price'] - $shellShocker['price']) ?> &middot; only <?= (int) $shellShocker['stock'] ?> left</p>
                    <button class="add-cart-btn"><i class="fa-solid fa-cart-shopping"></i> Add to Cart</button>
                </div>
            </article>
<?php else: ?>
            <p class="no-products">There is no Shell Shocker deal right now. Check back soon, or <a href="shop.html">browse the shop</a>.</p>
<?php endif; ?>
        </section>

<?php productRow("almostGoneTitle", "Almost Gone: only a few left", "Shop all deals", "#clearance", $almostGone); ?>

        <section class="clearance" id="clearance">
            <div class="deals-heading">
                <h2><i class="fa-solid fa-tags"></i> Clearance Deals</h2>
                <p><?= count($clearance) ?> product<?= count($clearance) === 1 ? "" : "s" ?> on sale while stocks last</p>
            </div>
<?php if ($deals): ?>
            <!-- Filters are plain links and a GET form, so PHP does the filtering and the URL can be shared -->
            <div class="deal-filters">
                <nav class="chip-list" aria-label="Filter deals by category">
                    <a class="chip<?= $chosenCategory === '' ? " active" : "" ?>" href="<?= h(dealsUrl('', $sort)) ?>"<?= $chosenCategory === '' ? ' aria-current="true"' : '' ?>>All <span><?= count($deals) ?></span></a>
<?php foreach ($categoryCounts as $category => $count): ?>
                    <a class="chip<?= $chosenCategory === $category ? " active" : "" ?>" href="<?= h(dealsUrl($category, $sort)) ?>"<?= $chosenCategory === $category ? ' aria-current="true"' : '' ?>><?= h($category) ?> <span><?= $count ?></span></a>
<?php endforeach; ?>
                </nav>
                <form class="sort-form" method="get" action="hotdeals.php#clearance">
<?php if ($chosenCategory !== ''): ?>
                    <input type="hidden" name="category" value="<?= h($chosenCategory) ?>">
<?php endif; ?>
                    <label for="dealSort">Sort by</label>
                    <select id="dealSort" name="sort">
<?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $value === $sort ? " selected" : "" ?>><?= h($label) ?></option>
<?php endforeach; ?>
                    </select>
                    <button type="submit" class="sort-apply">Apply</button>
                </form>
            </div>
<?php endif; ?>
            <div class="product-grid">
<?php
if ($clearance) {
    foreach ($clearance as $deal) {
        productCard($deal);
    }
} else {
    echo '                <p class="no-products">No other products are on sale right now. <a href="shop.html">Browse the full shop</a>.</p>' . "\n";
}
?>
            </div>
        </section>

<?php if ($bundles): ?>
        <section class="bundles" id="bundles">
            <div class="deals-heading">
                <h2><i class="fa-solid fa-boxes-stacked"></i> Bundles</h2>
                <p>Parts that work together, picked by our team</p>
            </div>
            <div class="bundle-cards">
<?php foreach ($bundles as $bundle): ?>
                <article class="bundle-card" data-ids="<?= h(implode(",", array_column($bundle['items'], 'product_id'))) ?>">
                    <div class="bundle-head">
                        <i class="fa-solid <?= h($bundle['icon']) ?>"></i>
                        <div>
                            <h3><?= h($bundle['title']) ?></h3>
                            <p><?= h($bundle['blurb']) ?></p>
                        </div>
                    </div>
                    <ul class="bundle-items">
<?php foreach ($bundle['items'] as $item): ?>
                        <li>
                            <a href="<?= productUrl($item) ?>">
                                <span class="bundle-thumb"><?= productImage($item) ?></span>
                                <span class="bundle-name"><?= h($item['name']) ?></span>
                            </a>
                            <span class="bundle-price"><?= money($item['price']) ?><?= isOnSale($item) ? "<s>" . money($item['old_price']) . "</s>" : "" ?></span>
                        </li>
<?php endforeach; ?>
                    </ul>
                    <div class="bundle-total">
                        <p>Bundle total <strong><?= money($bundle['total']) ?></strong></p>
<?php if ($bundle['saving'] > 0): ?>
                        <p class="deal-saving">Includes <?= money($bundle['saving']) ?> of today's deals</p>
<?php endif; ?>
                    </div>
                    <button type="button" class="add-bundle-btn"><i class="fa-solid fa-cart-plus"></i> Add bundle to cart</button>
                    <p class="bundle-message" role="status"></p>
                </article>
<?php endforeach; ?>
            </div>
        </section>
<?php endif; ?>

        <section class="how-deals" aria-labelledby="howDealsTitle">
            <div class="deals-heading">
                <h2 id="howDealsTitle"><i class="fa-solid fa-circle-question"></i> How Hot Deals work</h2>
            </div>
            <div class="guide-cards">
                <article class="guide-card">
                    <i class="fa-solid fa-bolt"></i>
                    <h3>A new Shell Shocker every day</h3>
                    <p>At midnight the Shell Shocker moves to another of our biggest discounts. Catch it before the countdown runs out.</p>
                    <a href="#shell-shocker">See today's deal <i class="fa-solid fa-arrow-right"></i></a>
                </article>
                <article class="guide-card">
                    <i class="fa-solid fa-hourglass-half"></i>
                    <h3>While stocks last</h3>
                    <p>Clearance prices hold until the stock runs out. Deals marked "Only a few left" are the ones most likely to go first.</p>
                    <a href="#clearance">Browse clearance <i class="fa-solid fa-arrow-right"></i></a>
                </article>
                <article class="guide-card">
                    <i class="fa-regular fa-envelope"></i>
                    <h3>Hear about deals first</h3>
                    <p>Sign up for Email Deals below and we will let you know when new discounts and Shell Shockers go live.</p>
                    <a href="#email-deals">Sign up <i class="fa-solid fa-arrow-right"></i></a>
                </article>
            </div>
        </section>

        <section class="email-deals" id="email-deals">
            <div>
                <h2><i class="fa-regular fa-envelope"></i> Email Deals</h2>
                <p>Sign up to be the first to hear about new Shell Shocker and clearance deals. We only use your email for deal alerts.</p>
            </div>
            <form class="subscribe-form" action="PHP/contact/subscribe.php" method="post">
                <label for="subscribeEmail" class="visually-hidden">Email address</label>
                <input type="email" id="subscribeEmail" name="email" placeholder="you@example.com" maxlength="100" required>
                <button type="submit" class="btn">Subscribe</button>
<?php if ($subscribed): ?>
                <p class="subscribe-message success" role="status">Thanks! You are on the list for our deal emails.</p>
<?php elseif ($subscribeError): ?>
                <p class="subscribe-message" role="alert">Please enter a valid email address.</p>
<?php endif; ?>
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