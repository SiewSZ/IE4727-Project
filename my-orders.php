<?php
// My Orders: the signed-in customer's own orders, newest first, built on the server.
// Each order shows a status tracker, its items in a table, the totals and the delivery address.
// Statuses are changed by admins on the Orders tab (PHP/orders/status.php), which also emails the customer.

require_once __DIR__ . "/PHP/db_connect.php";
require_once __DIR__ . "/PHP/users/auth.php";        // the session and refreshSessionUser()
require_once __DIR__ . "/PHP/products/render.php";   // h(), money()

refreshSessionUser($conn);   // signs out an account that has been deleted
$signedIn = !empty($_SESSION['user_id']);

$orders = [];
if ($signedIn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, order_id DESC");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $order) {
        $order['items'] = [];
        $orders[$order['order_id']] = $order;
    }
    mysqli_stmt_close($stmt);

    // Items with their current product picture (the product may have been removed since)
    $stmt = mysqli_prepare($conn,
        "SELECT i.*, p.image_path, p.is_active
         FROM order_items i
         JOIN orders o USING (order_id)
         LEFT JOIN products p ON p.product_id = i.product_id
         WHERE o.user_id = ?
         ORDER BY i.item_id");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $item) {
        $orders[$item['order_id']]['items'][] = $item;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);

// The tracker's steps, in order (a cancelled order shows a notice instead)
const ORDER_STEPS = [
    "Pending" => ["fa-receipt", "Order placed"],
    "Processing" => ["fa-box", "Packing"],
    "Shipped" => ["fa-truck-fast", "On the way"],
    "Delivered" => ["fa-house-circle-check", "Delivered"],
];

$active = array_filter($orders, fn($o) => $o['status'] !== 'Cancelled');
$totalSpent = array_sum(array_column($active, 'total'));
$inProgress = count(array_filter($active, fn($o) => $o['status'] !== 'Delivered'));

function formatDate($timestamp)
{
    return date("d M Y, g:i A", strtotime($timestamp));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/my-orders.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/session.js" defer></script>
    <title>My Orders | Quantum Gear</title>
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
                                <li><a href="pc-builder.php">Desktop PC (PC Builder)</a></li>
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
                <li><a href="hotdeals.php">Hot Deals</a></li>
                <li><a href="pc-builder.php">PC Builder</a></li>
                <li><a href="contact.html">Contact Us</a></li>
            </ul>
        </nav>
    </header>
    <main class="orders-page">
        <div class="orders-title">
            <h1><i class="fa-solid fa-box-open"></i> My Orders</h1>
            <p>Follow your orders from checkout to your door. We also email you each time an order's status changes.</p>
        </div>
<?php if (!$signedIn): ?>
        <section class="orders-empty">
            <i class="fa-solid fa-user-lock"></i>
            <h2>Sign in to see your orders</h2>
            <p>Your orders are saved to your account, so sign in with the account you ordered with.</p>
            <a href="login.html" class="btn">Login / Register</a>
        </section>
<?php elseif (!$orders): ?>
        <section class="orders-empty">
            <i class="fa-solid fa-cart-shopping"></i>
            <h2>No orders yet</h2>
            <p>When you place an order it will appear here, with its status and delivery details.</p>
            <a href="shop.html" class="btn">Start shopping</a>
        </section>
<?php else: ?>
        <div class="orders-summary">
            <p><strong><?= count($orders) ?></strong> order<?= count($orders) === 1 ? "" : "s" ?></p>
            <p><strong><?= $inProgress ?></strong> on the way</p>
            <p><strong><?= money($totalSpent) ?></strong> spent<?= count($active) < count($orders) ? " (not counting cancelled orders)" : "" ?></p>
        </div>

<?php foreach ($orders as $order):
    $stepIndex = array_search($order['status'], array_keys(ORDER_STEPS), true);
    $itemCount = array_sum(array_column($order['items'], 'qty'));
?>
        <article class="my-order" id="order-<?= (int) $order['order_id'] ?>">
            <div class="my-order-head">
                <div>
                    <h2>Order #<?= (int) $order['order_id'] ?></h2>
                    <p>Placed <?= h(formatDate($order['created_at'])) ?> &middot; <?= $itemCount ?> item<?= $itemCount === 1 ? "" : "s" ?></p>
                </div>
                <span class="order-status <?= strtolower(h($order['status'])) ?>"><?= h($order['status']) ?></span>
            </div>

<?php if ($order['status'] === 'Cancelled'): ?>
            <p class="order-cancelled"><i class="fa-solid fa-circle-xmark"></i> This order was cancelled and you were not charged. Questions? <a href="contact.html?subject=order">Contact us</a></p>
<?php else: ?>
            <ol class="order-steps" aria-label="Order progress">
<?php foreach (array_values(ORDER_STEPS) as $i => [$icon, $label]): ?>
                <li class="<?= $i < $stepIndex ? "done" : ($i === $stepIndex ? "current" : "") ?>"<?= $i === $stepIndex ? ' aria-current="step"' : "" ?>>
                    <span class="step-dot"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="step-label"><?= $label ?></span>
                </li>
<?php endforeach; ?>
            </ol>
<?php endif; ?>

            <table class="order-lines">
                <caption class="visually-hidden">Items in order #<?= (int) $order['order_id'] ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Price</th>
                        <th scope="col">Qty</th>
                        <th scope="col">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <th scope="row">
                            <span class="line-thumb"><?= $item['image_path'] ? '<img src="' . h($item['image_path']) . '" alt="">' : '<i class="fa-solid fa-box"></i>' ?></span>
<?php if ($item['product_id'] && $item['is_active']): ?>
                            <a href="product.php?id=<?= (int) $item['product_id'] ?>"><?= h($item['name']) ?></a>
<?php else: ?>
                            <?= h($item['name']) ?>
<?php endif; ?>
                        </th>
                        <td><?= money($item['unit_price']) ?><?= $item['regular_price'] > $item['unit_price'] ? "<s>" . money($item['regular_price']) . "</s>" : "" ?></td>
                        <td><?= (int) $item['qty'] ?></td>
                        <td><?= money($item['unit_price'] * $item['qty']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>

            <div class="my-order-foot">
                <p class="order-ship"><i class="fa-solid fa-location-dot"></i> <strong><?= h($order['address_label']) ?>:</strong> <?= h($order['address']) ?></p>
                <dl class="order-totals">
                    <dt>Subtotal</dt><dd><?= money($order['subtotal']) ?></dd>
<?php if ($order['discount'] > 0): ?>
                    <dt>Discount</dt><dd class="saving">-<?= money($order['discount']) ?></dd>
<?php endif; ?>
                    <dt class="total">Total</dt><dd class="total"><?= money($order['total']) ?></dd>
                </dl>
            </div>
        </article>
<?php endforeach; ?>
<?php endif; ?>
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
                    <li><a href="pc-builder.php">PC Builder</a></li>
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