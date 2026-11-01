<?php
// Admin dashboard (admins only), built on the server: inventory, orders, users, insights and the
// sales report. Every change is a form sent to a handler in PHP/products, PHP/orders or PHP/users,
// which comes back here (?tab=...) with a message. JS/admin.js only switches tabs, filters the
// tables already on the page, asks "are you sure?" and helps with the sale price.
//   admin.php?tab=inventory|orders|users|insights|report
//   &edit=<product_id> or &add=1     opens the product form
//   &from=YYYY-MM-DD&to=YYYY-MM-DD   (report tab) the sales report for those dates

require_once __DIR__ . "/PHP/page.php";                     // the session, header and messages
require_once __DIR__ . "/PHP/products/render.php";          // h(), money()
require_once __DIR__ . "/PHP/reports/sales_report.php";     // salesReport()
requireAdmin("");

const LOW_STOCK = 5;   // at or below this count the stock pill turns orange
const UNSOLD_SHOWN = 3;   // sales report: unsold products named in the Highlights before "Show all"
const ORDER_STATUSES = ["Pending", "Processing", "Shipped", "Delivered", "Cancelled"];
const TABS = [
    "inventory" => ["fa-warehouse", "Inventory"],
    "orders" => ["fa-truck-fast", "Orders"],
    "users" => ["fa-users", "Users"],
    "insights" => ["fa-chart-column", "Insights"],
    "report" => ["fa-chart-line", "Sales Report"],
];
$tab = array_key_exists($_GET['tab'] ?? '', TABS) ? $_GET['tab'] : "inventory";

$fetchAll = fn($sql) => mysqli_fetch_all(mysqli_query($conn, $sql), MYSQLI_ASSOC);

// ---------- Inventory: every product, hidden ones too ----------
$categories = $fetchAll("SELECT category_id, name, icon FROM categories ORDER BY name");
$products = $fetchAll(
    "SELECT p.*, c.name AS category, c.icon,
            (SELECT COUNT(*) FROM stock_alerts a WHERE a.product_id = p.product_id AND a.notified_at IS NULL) AS waiting
     FROM products p
     JOIN categories c USING (category_id)
     ORDER BY p.product_id");

// ---------- Orders, with their items ----------
$orders = [];
foreach ($fetchAll("SELECT * FROM orders ORDER BY created_at DESC, order_id DESC") as $order) {
    $order['items'] = [];
    $orders[$order['order_id']] = $order;
}
foreach ($fetchAll("SELECT order_id, name, unit_price, qty FROM order_items ORDER BY item_id") as $item) {
    if (isset($orders[$item['order_id']])) {
        $orders[$item['order_id']]['items'][] = $item;
    }
}

// Header cards: orders still waiting, and money taken this calendar month (cancelled orders don't count)
$pending = (int) mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status = 'Pending'"))[0];
$revenueThisMonth = (float) mysqli_fetch_row(mysqli_query($conn,
    "SELECT COALESCE(SUM(total), 0) FROM orders
     WHERE status <> 'Cancelled' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"))[0];

// ---------- Users (password hashes are never read) ----------
$users = $fetchAll("SELECT id, username, email, role, created_at, last_login FROM users ORDER BY created_at DESC, id DESC");
$currentUserId = (int) $_SESSION['user_id'];

// ---------- Sales report, once dates have been chosen ----------
$reportFrom = is_string($_GET['from'] ?? null) ? $_GET['from'] : date("Y-m-d", strtotime("-29 days"));
$reportTo = is_string($_GET['to'] ?? null) ? $_GET['to'] : date("Y-m-d");
$report = isset($_GET['from'], $_GET['to']) ? salesReport($conn, $reportFrom, $reportTo) : null;

mysqli_close($conn);

// ---------- The product form: Add Product, Edit, or the values sent back after an error (save.php) ----------
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($products as $p) {
    if ((int) $p['product_id'] === $editId) {
        $editing = $p;
    }
}
$showForm = $editing || isset($_GET['add']);
$form = [
    "product_id" => "", "name" => "", "brand" => "", "category_id" => "", "regular_price" => "", "sale_price" => "",
    "stock" => "", "description" => "", "features" => "", "part_type" => "", "socket" => "", "memory_type" => "",
    "wattage" => "", "is_active" => "on",
];
if ($editing) {
    // On sale: the stored old price is the regular price
    $form = [
        "product_id" => $editing['product_id'],
        "name" => $editing['name'],
        "brand" => $editing['brand'],
        "category_id" => $editing['category_id'],
        "regular_price" => $editing['old_price'] ?? $editing['price'],
        "sale_price" => $editing['old_price'] === null ? "" : $editing['price'],
        "stock" => $editing['stock'],
        "description" => $editing['description'] ?? "",
        "features" => $editing['features'] ?? "",
        "part_type" => $editing['part_type'] ?? "",
        "socket" => $editing['socket'] ?? "",
        "memory_type" => $editing['memory_type'] ?? "",
        "wattage" => $editing['wattage'] ?? "",
        "is_active" => $editing['is_active'] ? "on" : "",
    ];
}
if ($showForm && isset($_SESSION['product_form'])) {
    // A checkbox that wasn't ticked isn't sent at all
    $form = array_merge($form, ["is_active" => ""], array_map(fn($v) => is_string($v) ? $v : "", $_SESSION['product_form']));
}
unset($_SESSION['product_form']);

// Selected / checked attributes for the form's lists and checkbox
$selected = fn($field, $value) => (string) $form[$field] === (string) $value ? " selected" : "";

// ---------- Insights: worked out from the product list ----------
$stockValue = fn($p) => $p['price'] * $p['stock'];
$percentOff = fn($p) => (1 - $p['price'] / $p['old_price']) * 100;
$productCount = count($products);
$lowStock = array_filter($products, fn($p) => $p['stock'] <= LOW_STOCK);
$totalValue = array_sum(array_map($stockValue, $products));
$totalUnits = array_sum(array_column($products, 'stock'));
$averagePrice = $productCount ? array_sum(array_column($products, 'price')) / $productCount : 0;

$deals = array_values(array_filter($products, 'isOnSale'));
usort($deals, fn($a, $b) => $percentOff($b) <=> $percentOff($a));
$avgDiscount = $deals ? array_sum(array_map($percentOff, $deals)) / count($deals) : 0;
$biggest = null;
foreach ($deals as $p) {
    if (!$biggest || $p['old_price'] - $p['price'] > $biggest['old_price'] - $biggest['price']) {
        $biggest = $p;
    }
}
$unitsOnSale = array_sum(array_column($deals, 'stock'));
$discountGiven = array_sum(array_map(fn($p) => ($p['old_price'] - $p['price']) * $p['stock'], $deals));

$needRestock = array_values($lowStock);
usort($needRestock, fn($a, $b) => $a['stock'] <=> $b['stock']);

// Stock value per category (every category, so an empty one shows as $0), and the average
// discount per category (only categories with at least one deal)
$valueRows = [];
$discountRows = [];
foreach ($categories as $c) {
    $inCategory = array_filter($products, fn($p) => $p['category_id'] == $c['category_id']);
    $valueRows[] = ["name" => $c['name'], "count" => count($inCategory), "value" => array_sum(array_map($stockValue, $inCategory))];
    $dealsHere = array_filter($deals, fn($p) => $p['category_id'] == $c['category_id']);
    if ($dealsHere) {
        $discountRows[] = ["name" => $c['name'], "count" => count($dealsHere), "avg" => array_sum(array_map($percentOff, $dealsHere)) / count($dealsHere)];
    }
}
usort($valueRows, fn($a, $b) => $b['value'] <=> $a['value']);
usort($discountRows, fn($a, $b) => $b['avg'] <=> $a['avg']);
$maxValue = max(array_column($valueRows, 'value') ?: [0]) ?: 1;
$maxAvg = max(array_column($discountRows, 'avg') ?: [0]) ?: 1;

// ---------- Users tab cards ----------
$weekAgo = strtotime("-7 days");
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$newThisWeek = count(array_filter($users, fn($u) => strtotime($u['created_at']) >= $weekAgo));
$activeThisWeek = count(array_filter($users, fn($u) => $u['last_login'] && strtotime($u['last_login']) >= $weekAgo));

// ---------- Small display helpers ----------
function plural($n, $word)
{
    return "$n $word" . ($n == 1 ? "" : "s");
}

// "2026-10-31" -> "31 Oct 2026"
function niceDate($value)
{
    return date("d M Y", strtotime($value));
}

// "3 h ago", "2 days ago", or the date once it is a month old
function timeAgo($value)
{
    $minutes = (int) round((time() - strtotime($value)) / 60);
    if ($minutes < 1) {
        return "just now";
    }
    if ($minutes < 60) {
        return "$minutes min ago";
    }
    if ($minutes < 24 * 60) {
        return round($minutes / 60) . " h ago";
    }
    $days = (int) round($minutes / (24 * 60));
    return $days < 30 ? plural($days, "day") . " ago" : niceDate($value);
}

function insightTile($label, $value, $note)
{
    ?>
                <div class="insight-tile">
                    <p class="insight-label"><?= $label ?></p>
                    <p class="insight-value"><?= $value ?></p>
                    <p class="insight-note"><?= $note ?></p>
                </div>
<?php
}

// One horizontal bar of a bar chart; $share is the bar's length in % of the longest
function chartBar($title, $label, $share, $value)
{
    ?>
                    <li title="<?= h($title) ?>">
                        <span class="bar-label"><?= $label ?></span>
                        <span class="bar-track"><span class="bar" style="width: <?= round($share, 2) ?>%"></span></span>
                        <span class="bar-value"><?= h($value) ?></span>
                    </li>
<?php
}

// Sales report: change from the previous period, e.g. "▲ 12.5%". Arrow and text as well as colour.
function reportChange($pct)
{
    if ($pct === null) {
        return '<span class="report-change">No sales in the previous period</span>';
    }
    [$dir, $arrow] = $pct > 0 ? ["up", "fa-arrow-up"] : ($pct < 0 ? ["down", "fa-arrow-down"] : ["", "fa-minus"]);
    return "<span class=\"report-change $dir\"><i class=\"fa-solid $arrow\"></i> " . abs($pct) . "%</span>";
}

function reportCard($icon, $label, $value, $extra = "")
{
    ?>
                <div class="stat-card">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <div>
                        <p class="stat-label"><?= $label ?></p>
                        <p class="stat-value"><?= $value ?></p>
                        <?= $extra ?>

                    </div>
                </div>
<?php
}

function shareCell($share)
{
    return "<div class=\"share-cell\"><span class=\"share-track\"><span style=\"width: $share%\"></span></span> $share%</div>";
}

// Stock left, and how long it lasts at the rate the product sold in the report's period
function reportStock($p)
{
    if ($p['stock'] === null) {
        return '<span class="status-pill hidden-pill">Deleted</span>';
    }
    if ($p['stock'] === 0) {
        return '<span class="stock-pill out">Sold out</span>';
    }
    $lasts = $p['daysOfStock'] < 1 ? "less than a day" : "about " . plural($p['daysOfStock'], "day");
    return '<span class="stock-pill' . ($p['restockSoon'] ? " low" : "") . "\">{$p['stock']} left</span>"
        . "<small>" . ($p['restockSoon'] ? "Restock soon (under " . RESTOCK_DAYS . " days): " : "") . "$lasts</small>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/admin.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/admin.js" defer></script>
    <title>Admin Dashboard | Quantum Gear</title>
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
    <main class="admin-page">
        <div class="admin-title">
            <h1><i class="fa-solid fa-gauge-high"></i> Admin Dashboard</h1>
            <p>Manage inventory and customer orders, and see how stock is spread across the store</p>
        </div>

        <section class="admin-stats" aria-label="Store overview">
            <div class="stat-card">
                <i class="fa-solid fa-boxes-stacked"></i>
                <div>
                    <p class="stat-label">Products</p>
                    <p class="stat-value"><?= $productCount ?></p>
                </div>
            </div>
            <div class="stat-card warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <p class="stat-label">Low / Out of Stock</p>
                    <p class="stat-value"><?= count($lowStock) ?></p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-receipt"></i>
                <div>
                    <p class="stat-label">Pending Orders</p>
                    <p class="stat-value"><?= $pending ?></p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-sack-dollar"></i>
                <div>
                    <p class="stat-label">Revenue (This Month)</p>
                    <p class="stat-value"><?= money($revenueThisMonth) ?></p>
                </div>
            </div>
        </section>

        <!-- Each tab is also a link (admin.php?tab=...), so it works without JavaScript -->
        <div class="admin-tabs" role="tablist">
<?php foreach (TABS as $key => [$icon, $label]): ?>
            <a href="admin.php?tab=<?= $key ?>" class="admin-tab<?= $key === $tab ? " active" : "" ?>" role="tab" aria-selected="<?= $key === $tab ? "true" : "false" ?>" aria-controls="<?= $key ?>Panel" data-tab="<?= $key ?>">
                <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>

            </a>
<?php endforeach; ?>
        </div>

        <!-- Inventory -->
        <section class="admin-panel" id="inventoryPanel" role="tabpanel"<?= $tab === "inventory" ? "" : " hidden" ?>>
            <div class="admin-toolbar">
                <div class="admin-search">
                    <i class="fa fa-search"></i>
                    <input type="text" id="inventorySearch" placeholder="Search products..." aria-label="Search products" data-filter-table="inventoryBody">
                </div>
                <select id="inventoryCategory" aria-label="Filter by category" data-filter-table="inventoryBody">
                    <option value="">All categories</option>
<?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>"><?= h($c['name']) ?></option>
<?php endforeach; ?>
                </select>
                <button type="button" class="admin-secondary-btn" id="saleBtn">
                    <i class="fa-solid fa-tags"></i> Run a Sale
                </button>
                <a href="admin.php?tab=inventory&amp;add=1#productForm" class="admin-primary-btn">
                    <i class="fa-solid fa-plus"></i> Add Product
                </a>
            </div>

            <!-- Bulk sale: same % off for a whole category (PHP/products/discount.php) -->
            <form class="admin-form sale-form" id="saleForm" method="post" action="PHP/products/discount.php" hidden>
                <?= csrfField() ?>

                <h3><i class="fa-solid fa-tags"></i> Run a Sale</h3>
                <p class="sale-help">Takes the same percentage off every product in a category. A new sale replaces any discount already running, and 0% ends the sale and restores regular prices.</p>
                <div class="sale-fields">
                    <label>Products
                        <select name="category_id" id="saleCategory">
                            <option value="0">All products</option>
<?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['category_id'] ?>"><?= h($c['name']) ?></option>
<?php endforeach; ?>
                        </select>
                    </label>
                    <label>Discount (%)
                        <input type="number" name="percent" id="salePercent" min="0" max="90" step="1" value="10" required>
                    </label>
                    <button type="submit" class="admin-primary-btn">Apply</button>
                    <button type="button" class="admin-secondary-btn" id="cancelSaleBtn">Cancel</button>
                </div>
                <p class="admin-form-error" id="saleFormError" role="alert" hidden></p>
            </form>

<?php if ($showForm): ?>
            <form class="admin-form" id="productForm" method="post" action="PHP/products/save.php" enctype="multipart/form-data">
                <?= csrfField() ?>

                <h3><?= $form['product_id'] ? "Edit Product" : "Add Product" ?></h3>
                <input type="hidden" name="product_id" value="<?= h($form['product_id']) ?>">
                <div class="admin-form-grid">
                    <label class="span-2">Product Name
                        <input type="text" name="name" required maxlength="150" value="<?= h($form['name']) ?>">
                    </label>
                    <label>Brand
                        <input type="text" name="brand" required maxlength="50" value="<?= h($form['brand']) ?>">
                    </label>
                    <label>Category
                        <select name="category_id" required>
                            <option value="">Select a category</option>
<?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['category_id'] ?>"<?= $selected("category_id", $c['category_id']) ?>><?= h($c['name']) ?></option>
<?php endforeach; ?>
                        </select>
                    </label>
                    <label>Regular Price ($)
                        <input type="number" name="regular_price" required min="0" step="0.01" value="<?= h($form['regular_price']) ?>">
                    </label>
                    <label>Discount (%) <small>0 = not on sale</small>
                        <input type="number" id="discountInput" min="0" max="90" step="1" value="0">
                    </label>
                    <label>Sale Price ($) <small>filled in from the discount, or type your own</small>
                        <input type="number" name="sale_price" min="0" step="0.01" value="<?= h($form['sale_price']) ?>">
                    </label>
                    <p class="price-preview" id="pricePreview" aria-live="polite"></p>
                    <label>Stock
                        <input type="number" name="stock" required min="0" step="1" value="<?= h($form['stock']) ?>">
                    </label>
                    <label>Image <small>JPG, PNG, WEBP or GIF, max 2 MB<?= $editing && $editing['image_path'] ? "; leave empty to keep the current image" : "" ?></small>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </label>
                    <label class="span-2">Description
                        <textarea name="description" rows="3"><?= h($form['description']) ?></textarea>
                    </label>
                    <label class="span-2">Key Features <small>one per line, shown as bullet points on the product page</small>
                        <textarea name="features" rows="5"><?= h($form['features']) ?></textarea>
                    </label>
                    <!-- PC Builder (pc-builder.php): leave "Not a PC part" for everything else -->
                    <fieldset class="span-2 builder-fields">
                        <legend>PC Builder <small>for parts that can go in a custom PC</small></legend>
                        <label>Part type
                            <select name="part_type">
<?php foreach (["" => "Not a PC part", "cpu" => "Processor (CPU)", "motherboard" => "Motherboard", "memory" => "Memory (RAM)",
                "gpu" => "Graphics card", "storage" => "Storage", "psu" => "Power supply", "cooler" => "CPU cooler", "case" => "Case"] as $value => $label): ?>
                                <option value="<?= $value ?>"<?= $selected("part_type", $value) ?>><?= $label ?></option>
<?php endforeach; ?>
                            </select>
                        </label>
                        <label>Socket <small>CPUs and motherboards</small>
                            <select name="socket">
                                <option value="">None</option>
<?php foreach (["AM4", "AM5", "LGA1700", "LGA1851"] as $value): ?>
                                <option<?= $selected("socket", $value) ?>><?= $value ?></option>
<?php endforeach; ?>
                            </select>
                        </label>
                        <label>Memory type <small>motherboards and RAM</small>
                            <select name="memory_type">
                                <option value="">None</option>
<?php foreach (["DDR4", "DDR5"] as $value): ?>
                                <option<?= $selected("memory_type", $value) ?>><?= $value ?></option>
<?php endforeach; ?>
                            </select>
                        </label>
                        <label>Watts <small>CPU/graphics draw, or PSU output</small>
                            <input type="number" name="wattage" min="1" max="3000" step="1" value="<?= h($form['wattage']) ?>">
                        </label>
                    </fieldset>
                    <label class="admin-check span-2">
                        <input type="checkbox" name="is_active"<?= $form['is_active'] !== "" ? " checked" : "" ?>>
                        Show this product in the shop
                    </label>
                </div>
                <div class="admin-form-actions">
                    <a href="admin.php?tab=inventory" class="admin-secondary-btn">Cancel</a>
                    <button type="submit" class="admin-primary-btn">Save Product</button>
                </div>
            </form>
<?php endif; ?>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="inventoryBody">
<?php foreach ($products as $p):
    $id = (int) $p['product_id'];
    $stock = (int) $p['stock'];
    $stockClass = $stock === 0 ? "out" : ($stock <= LOW_STOCK ? "low" : ""); ?>
                        <tr id="product-<?= $id ?>" data-search="<?= h(mb_strtolower("{$p['name']} {$p['brand']}")) ?>" data-filter="<?= (int) $p['category_id'] ?>">
                            <td>
                                <div class="admin-product">
<?php if ($p['image_path']): ?>
                                    <img src="<?= h($p['image_path']) ?>" alt="">
<?php else: ?>
                                    <span class="admin-product-icon"><i class="fa-solid <?= h($p['icon'] ?: "fa-box") ?>"></i></span>
<?php endif; ?>
                                    <div><strong><?= h($p['name']) ?></strong><small><?= h($p['brand']) ?></small></div>
                                </div>
                            </td>
                            <td><?= h($p['category']) ?></td>
                            <td><?= money($p['price']) ?><?= isOnSale($p) ? "<s>" . money($p['old_price']) . "</s>" : "" ?></td>
                            <td><span class="stock-pill <?= $stockClass ?>"><?= $stock ?></span></td>
                            <td><?= $p['is_active'] ? '<span class="status-pill active">Active</span>' : '<span class="status-pill hidden-pill">Hidden</span>' ?></td>
                            <td class="admin-actions">
                                <a href="admin.php?tab=inventory&amp;edit=<?= $id ?>#productForm" class="icon-btn" aria-label="Edit <?= h($p['name']) ?>"><i class="fa-solid fa-pen"></i></a>
                                <form method="post" action="PHP/products/toggle.php">
                                    <?= csrfField() ?>

                                    <input type="hidden" name="product_id" value="<?= $id ?>">
                                    <input type="hidden" name="is_active" value="<?= $p['is_active'] ? "0" : "1" ?>">
                                    <button type="submit" class="icon-btn" aria-label="<?= $p['is_active'] ? "Hide from shop" : "Show in shop" ?>">
                                        <i class="fa-solid <?= $p['is_active'] ? "fa-eye-slash" : "fa-eye" ?>"></i>
                                    </button>
                                </form>
                                <form method="post" action="PHP/products/delete.php" data-confirm="Delete &quot;<?= h($p['name']) ?>&quot;? This cannot be undone.">
                                    <?= csrfField() ?>

                                    <input type="hidden" name="product_id" value="<?= $id ?>">
                                    <button type="submit" class="icon-btn delete-btn" aria-label="Delete <?= h($p['name']) ?>"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
<?php endforeach; ?>
                        <tr class="no-match" hidden><td colspan="6">No products found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Insights: worked out in PHP from the same product list as the Inventory tab -->
        <section class="admin-panel" id="insightsPanel" role="tabpanel"<?= $tab === "insights" ? "" : " hidden" ?>>
            <p class="insights-intro">A snapshot of the inventory, including products hidden from the shop. It updates whenever a product is added, edited or removed.</p>
            <div class="insight-tiles">
<?php
insightTile("Stock value", money($totalValue), "price × units, every product");
insightTile("Units in stock", number_format($totalUnits), "across $productCount products");
insightTile("Average price", money($averagePrice), "per product listed");
insightTile("On sale", count($deals) . " <small>of $productCount</small>", $deals ? round($avgDiscount) . "% off on average" : "no discounts running");
?>
            </div>
            <div class="insight-panels">
                <figure class="insight-chart">
                    <figcaption>
                        <h3>Stock value by category</h3>
                        <p>Price &times; units in stock: where the money sits on the shelves</p>
                    </figcaption>
                    <ul class="bar-chart">
<?php foreach ($valueRows as $r) {
    chartBar("{$r['name']}: " . money($r['value']) . " in stock across " . plural($r['count'], "product"),
        h($r['name']) . " <small>(" . plural($r['count'], "product") . ")</small>", $r['value'] / $maxValue * 100, money($r['value']));
} ?>
                    </ul>
                </figure>
                <div class="insight-table-wrap">
                    <table class="insight-table">
                        <caption>Restock priority (<?= LOW_STOCK ?> or fewer left)</caption>
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Price</th>
                                <th scope="col">In stock</th>
                                <th scope="col">Stock value</th>
                                <th scope="col">Waiting</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($needRestock as $p): ?>
                            <tr>
                                <th scope="row"><?= h($p['name']) ?><small><?= h($p['category']) ?><?= $p['is_active'] ? "" : " · hidden" ?></small></th>
                                <td><?= money($p['price']) ?></td>
                                <td><?= $p['stock'] == 0 ? '<span class="out">Out of stock</span>' : (int) $p['stock'] ?></td>
                                <td><?= money($stockValue($p)) ?></td>
                                <td title="Customers who asked to be emailed when it's back"><?= $p['waiting'] ? "<strong>" . (int) $p['waiting'] . '</strong> <i class="fa-regular fa-bell"></i>' : "–" ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$needRestock): ?>
                            <tr><td colspan="5">Every product has more than <?= LOW_STOCK ?> in stock.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <h2 class="insights-heading"><i class="fa-solid fa-tags"></i> Discounts</h2>
            <div class="insight-tiles">
<?php
insightTile("Products on sale", count($deals) . " <small>of $productCount</small>", ($productCount ? round(count($deals) / $productCount * 100) : 0) . "% of the range is discounted");
insightTile("Average discount", round($avgDiscount) . "%", "across every product on sale");
insightTile("Biggest saving", $biggest ? money($biggest['old_price'] - $biggest['price']) : "–", $biggest ? "on the " . h($biggest['name']) : "no discounts running");
insightTile("Discount on stock", money($discountGiven), "if all $unitsOnSale units on sale sell");
?>
            </div>
            <div class="insight-panels">
                <figure class="insight-chart">
                    <figcaption>
                        <h3>Average discount by category</h3>
                        <p>Which kinds of products have the deepest price cuts</p>
                    </figcaption>
                    <ul class="bar-chart">
<?php foreach ($discountRows as $r) {
    chartBar("{$r['name']}: " . number_format($r['avg'], 1) . "% off on average across " . plural($r['count'], "deal"),
        h($r['name']) . " <small>(" . plural($r['count'], "deal") . ")</small>", $r['avg'] / $maxAvg * 100, round($r['avg']) . "%");
} ?>
<?php if (!$discountRows): ?>
                        <li>No products are on sale.</li>
<?php endif; ?>
                    </ul>
                </figure>
                <div class="insight-table-wrap">
                    <table class="insight-table">
                        <caption>Products on sale, biggest discount first</caption>
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Was</th>
                                <th scope="col">Now</th>
                                <th scope="col">Saving</th>
                                <th scope="col">% off</th>
                                <th scope="col">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($deals as $p): ?>
                            <tr>
                                <th scope="row"><?= h($p['name']) ?><small><?= h($p['category']) ?><?= $p['is_active'] ? "" : " · hidden" ?></small></th>
                                <td><s><?= money($p['old_price']) ?></s></td>
                                <td><?= money($p['price']) ?></td>
                                <td><?= money($p['old_price'] - $p['price']) ?></td>
                                <td><?= round($percentOff($p)) ?>%</td>
                                <td><?= $p['stock'] == 0 ? '<span class="out">Sold out</span>' : (int) $p['stock'] ?></td>
                            </tr>
<?php endforeach; ?>
<?php if (!$deals): ?>
                            <tr><td colspan="6">No products are on sale right now.</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Users: every account -->
        <section class="admin-panel" id="usersPanel" role="tabpanel"<?= $tab === "users" ? "" : " hidden" ?>>
            <div class="insight-tiles">
<?php
insightTile("Total users", count($users), "registered accounts");
insightTile("Admins", $adminCount, plural(count($users) - $adminCount, "customer"));
insightTile("New this week", $newThisWeek, "joined in the last 7 days");
insightTile("Active this week", $activeThisWeek, "signed in during the last 7 days");
?>
            </div>
            <div class="admin-toolbar">
                <div class="admin-search">
                    <i class="fa fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Search by username or email..." aria-label="Search users" data-filter-table="usersBody">
                </div>
                <select id="userRoleFilter" aria-label="Filter by role" data-filter-table="usersBody">
                    <option value="">All roles</option>
                    <option value="customer">Customers</option>
                    <option value="admin">Admins</option>
                </select>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Last Sign-in</th>
                            <th><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="usersBody">
<?php foreach ($users as $u):
    $isMe = (int) $u['id'] === $currentUserId;
    $otherRole = $u['role'] === "admin" ? "customer" : "admin"; ?>
                        <tr data-search="<?= h(mb_strtolower("{$u['username']} {$u['email']}")) ?>" data-filter="<?= h($u['role']) ?>">
                            <td><strong><?= h($u['username']) ?></strong><?= $isMe ? '<span class="you-tag">You</span>' : "" ?><small><?= h($u['email']) ?></small></td>
                            <td><span class="role-pill <?= h($u['role']) ?>"><?= $u['role'] === "admin" ? "Admin" : "Customer" ?></span></td>
                            <td><?= niceDate($u['created_at']) ?></td>
                            <td><?= $u['last_login']
                                ? '<span title="' . h(date("d M Y, g:i A", strtotime($u['last_login']))) . '">' . timeAgo($u['last_login']) . "</span>"
                                : '<span class="last-login-never">Never</span>' ?></td>
                            <td>
<?php if (!$isMe): ?>
                                <div class="user-actions">
                                    <form method="post" action="PHP/users/role.php" data-confirm="<?= h($otherRole === "admin"
                                        ? "Make {$u['username']} an admin? They will be able to change products, prices and users."
                                        : "Make {$u['username']} a customer? They will lose access to this page.") ?>">
                                        <?= csrfField() ?>

                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <input type="hidden" name="role" value="<?= $otherRole ?>">
                                        <button type="submit" class="admin-secondary-btn"><?= $otherRole === "admin" ? "Make admin" : "Make customer" ?></button>
                                    </form>
                                    <form method="post" action="PHP/users/delete.php" data-confirm="<?= h("Delete the account \"{$u['username']}\" ({$u['email']})? This cannot be undone.") ?>">
                                        <?= csrfField() ?>

                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="icon-btn" aria-label="Delete <?= h($u['username']) ?>"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
<?php endif; ?>
                            </td>
                        </tr>
<?php endforeach; ?>
                        <tr class="no-match" hidden><td colspan="5">No users found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Orders -->
        <section class="admin-panel" id="ordersPanel" role="tabpanel"<?= $tab === "orders" ? "" : " hidden" ?>>
            <div class="admin-toolbar">
                <div class="admin-search">
                    <i class="fa fa-search"></i>
                    <input type="text" id="orderSearch" placeholder="Search by order # or customer..." aria-label="Search orders" data-filter-table="ordersBody">
                </div>
                <select id="orderStatusFilter" aria-label="Filter by status" data-filter-table="ordersBody">
                    <option value="">All statuses</option>
<?php foreach (ORDER_STATUSES as $status): ?>
                    <option><?= $status ?></option>
<?php endforeach; ?>
                </select>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody id="ordersBody">
<?php foreach ($orders as $o):
    $id = (int) $o['order_id'];
    $itemCount = array_sum(array_column($o['items'], 'qty')); ?>
                        <tr id="order-<?= $id ?>" data-search="<?= h(mb_strtolower("$id {$o['customer_name']} {$o['email']}")) ?>" data-filter="<?= h($o['status']) ?>">
                            <td><strong>#<?= $id ?></strong></td>
                            <td><?= h($o['customer_name']) ?><small><?= h($o['email']) ?></small></td>
                            <td><?= date("d M Y", strtotime($o['created_at'])) ?><small><?= date("g:i A", strtotime($o['created_at'])) ?></small></td>
                            <td>
                                <details class="order-items">
                                    <summary><?= plural($itemCount, "item") ?></summary>
                                    <ul>
<?php foreach ($o['items'] as $item): ?>
                                        <li><?= (int) $item['qty'] ?> × <?= h($item['name']) ?> <span><?= money($item['unit_price'] * $item['qty']) ?></span></li>
<?php endforeach; ?>
                                    </ul>
                                    <p class="order-address"><i class="fa-solid fa-location-dot"></i> <?= h($o['address_label']) ?>: <?= h($o['address']) ?></p>
                                </details>
                            </td>
                            <td><?= money($o['total']) ?><?= $o['discount'] > 0 ? "<small>saved " . money($o['discount']) . "</small>" : "" ?></td>
                            <td><span class="order-status <?= strtolower(h($o['status'])) ?>"><?= h($o['status']) ?></span></td>
                            <td>
<?php if ($o['status'] === "Cancelled"): ?>
                                <!-- A cancelled order can't be reopened, so it has no status form -->
                                <span class="order-final">Stock returned</span>
<?php else: ?>
                                <form class="status-form" method="post" action="PHP/orders/status.php" data-order="<?= $id ?>">
                                    <?= csrfField() ?>

                                    <input type="hidden" name="order_id" value="<?= $id ?>">
                                    <select name="status" aria-label="New status for order <?= $id ?>">
<?php foreach (ORDER_STATUSES as $status): ?>
                                        <option<?= $status === $o['status'] ? " selected" : "" ?>><?= $status ?></option>
<?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="admin-secondary-btn">Update</button>
                                </form>
<?php endif; ?>
                            </td>
                        </tr>
<?php endforeach; ?>
                        <tr class="no-match"<?= $orders ? " hidden" : "" ?>><td colspan="7"><?= $orders ? "No orders match." : "No orders yet. Orders placed from the cart will appear here." ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Sales Report: worked out by salesReport() in PHP/reports/sales_report.php -->
        <section class="admin-panel" id="reportPanel" role="tabpanel"<?= $tab === "report" ? "" : " hidden" ?>>
            <form class="admin-toolbar report-toolbar" id="reportForm" method="get" action="admin.php">
                <input type="hidden" name="tab" value="report">
                <label>From
                    <input type="date" name="from" value="<?= h($reportFrom) ?>" required>
                </label>
                <label>To
                    <input type="date" name="to" value="<?= h($reportTo) ?>" required>
                </label>
                <button type="submit" class="admin-primary-btn">
                    <i class="fa-solid fa-chart-column"></i> Generate Report
                </button>
            </form>
            <p class="admin-form-error" id="reportError" role="alert"<?= isset($report['error']) ? "" : " hidden" ?>><?= h($report['error'] ?? "") ?></p>
            <div id="reportOutput">
<?php if (!$report): ?>
                <p class="report-empty">Choose the dates, then click Generate Report.</p>
<?php elseif (!isset($report['error'])):
    $s = $report['summary']; ?>
                <h2 class="report-heading"><?= niceDate($report['from']) ?> – <?= niceDate($report['to']) ?>
                    <small><?= plural($report['days'], "day") ?>. Arrows compare with the previous <?= plural($report['days'], "day") ?>
                    (<?= niceDate($report['previousFrom']) ?> – <?= niceDate($report['previousTo']) ?>).</small>
                </h2>
                <section class="admin-stats report-kpis" aria-label="Report summary">
<?php
reportCard("fa-sack-dollar", "Revenue", money($s['revenue']), reportChange($s['revenueChange']));
reportCard("fa-receipt", "Orders", $s['orders'], reportChange($s['ordersChange']));
reportCard("fa-cubes", "Units Sold", $s['units'], reportChange($s['unitsChange']));
reportCard("fa-cart-shopping", "Average Order", money($s['avgOrderValue']),
    '<span class="report-change">' . plural($s['itemsPerOrder'], "item") . " per order</span>");
reportCard("fa-calendar-day", "Average per Day", money($s['avgDailyRevenue']));
?>
                </section>
<?php if ($s['orders'] === 0): ?>
                <p class="report-empty">No orders were placed in this period.</p>
<?php else:
    $best = $report['products'][0];
    $restock = array_filter($report['products'], fn($p) => $p['restockSoon']);
    $maxDay = max(array_column($report['daily'], 'revenue'));
    // Products with no sales: the count and a few names; the full list opens below (can be dozens)
    $unsold = array_map('h', $report['unsold']);
    $unsoldText = !$unsold ? "Every product sold at least once"
        : plural(count($unsold), "product") . (count($unsold) > UNSOLD_SHOWN
            ? ", e.g. " . implode(", ", array_slice($unsold, 0, UNSOLD_SHOWN))
            : ": " . implode(", ", $unsold));
    $highlights = [
        ["fa-trophy", "Best seller (revenue)", h($best['name']) . ": " . money($best['revenue']) . ", {$best['share']}% of all revenue"],
        ["fa-boxes-stacked", "Most units sold", h($report['mostUnits']['name']) . ": " . plural($report['mostUnits']['units'], "unit")],
        ["fa-calendar-check", "Best day", $report['bestDay']   // null only if every item sold was free
            ? niceDate($report['bestDay']['date']) . ": " . money($report['bestDay']['revenue']) . " from " . plural($report['bestDay']['orders'], "order")
            : "No revenue in this period"],
        ["fa-triangle-exclamation", "Restock soon",
            $restock ? implode(", ", array_map(fn($p) => h($p['name']), $restock)) : "Nothing is about to run out"],
        ["fa-circle-minus", "No sales in this period", $unsoldText,
            count($unsold) > UNSOLD_SHOWN ? $unsold : []],
    ]; ?>
                <div class="report-grid">
                    <section class="report-section">
                        <h3>Highlights</h3>
                        <ul class="report-highlights">
<?php foreach ($highlights as $highlight): [$icon, $label, $text] = $highlight; $more = $highlight[3] ?? []; ?>
                            <li><i class="fa-solid <?= $icon ?>"></i><div><strong><?= $label ?></strong><span><?= $text ?></span>
<?php if ($more): ?>
                                <details class="highlight-more">
                                    <summary>Show all <?= count($more) ?></summary>
                                    <ul>
<?php foreach ($more as $name): ?>
                                        <li><?= $name ?></li>
<?php endforeach; ?>
                                    </ul>
                                </details>
<?php endif; ?>
                            </div></li>
<?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="report-section">
                        <h3>Revenue per Day</h3>
                        <!-- Revenue per day as bars. Pointing at a bar shows that day's numbers in the line under the chart (JS/admin.js). -->
                        <div class="report-chart" role="img" aria-label="Revenue per day. Highest day: <?= money($maxDay) ?>.">
                            <span class="report-chart-max"><?= money($maxDay) ?></span>
                            <div class="report-bars<?= count($report['daily']) > 90 ? " dense" : "" ?>">
<?php foreach ($report['daily'] as $d): ?>
                                <div class="report-bar" data-tip="<?= h(niceDate($d['date']) . ": " . money($d['revenue']) . " from " . plural($d['orders'], "order")) ?>"><span style="height: <?= $d['revenue'] > 0 ? "max(2px, " . round($d['revenue'] / $maxDay * 100, 2) . "%)" : "0" ?>"></span></div>
<?php endforeach; ?>
                            </div>
                            <div class="report-axis">
                                <span><?= niceDate($report['daily'][0]['date']) ?></span>
                                <span><?= niceDate(end($report['daily'])['date']) ?></span>
                            </div>
                        </div>
                        <p class="report-readout">Point at a bar to see that day's sales.</p>
                    </section>
                </div>

                <section class="report-section">
                    <h3>Sales by Product</h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>#</th><th>Product</th><th>Category</th><th>Units</th><th>Orders</th>
                                    <th>Revenue</th><th>Share of Revenue</th><th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($report['products'] as $p): ?>
                                <tr>
                                    <td><?= $p['rank'] ?></td>
                                    <td><strong><?= h($p['name']) ?></strong></td>
                                    <td><?= h($p['category']) ?></td>
                                    <td><?= $p['units'] ?></td>
                                    <td><?= $p['orders'] ?></td>
                                    <td><?= money($p['revenue']) ?></td>
                                    <td><?= shareCell($p['share']) ?></td>
                                    <td><?= reportStock($p) ?></td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="report-section">
                    <h3>Sales by Category</h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Category</th><th>Units</th><th>Orders</th><th>Revenue</th><th>Share of Revenue</th></tr>
                            </thead>
                            <tbody>
<?php foreach ($report['categories'] as $c): ?>
                                <tr>
                                    <td><strong><?= h($c['name']) ?></strong></td>
                                    <td><?= $c['units'] ?></td>
                                    <td><?= $c['orders'] ?></td>
                                    <td><?= money($c['revenue']) ?></td>
                                    <td><?= shareCell($c['share']) ?></td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
<?php endif; ?>
<?php endif; ?>
            </div>
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