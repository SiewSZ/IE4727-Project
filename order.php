<?php
// Shopping cart and checkout, built on the server from the session cart (PHP/shopping_cart/cart_items.php).
// The + / - / remove buttons are small forms sent to PHP/shopping_cart/cart.php, and the checkout
// form is sent to PHP/orders/place.php. Both come back here; place.php adds ?placed=<order id>.

require_once __DIR__ . "/PHP/page.php";                       // header, flash messages, csrfField()
require_once __DIR__ . "/PHP/products/render.php";            // h(), money(), productImage()
require_once __DIR__ . "/PHP/shopping_cart/cart_items.php";   // cartItems(), ADDRESSES

$cart = cartItems($conn);

// SubTotal uses the regular price, Discount is the amount saved
$subtotal = 0;
$total = 0;
foreach ($cart as $item) {
    $subtotal += ($item['old_price'] ?? $item['price']) * $item['qty'];
    $total += $item['price'] * $item['qty'];
}

// Just placed an order: show its confirmation (only the customer's own orders)
$placed = null;
$placedId = (int) ($_GET['placed'] ?? 0);
if ($placedId > 0 && !empty($_SESSION['user_id'])) {
    $stmt = mysqli_prepare($conn,
        "SELECT o.order_id, o.total, o.address_label, o.address, SUM(i.qty) AS item_count
         FROM orders o JOIN order_items i USING (order_id)
         WHERE o.order_id = ? AND o.user_id = ?
         GROUP BY o.order_id");
    mysqli_stmt_bind_param($stmt, "ii", $placedId, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $placed = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/order.css">
    <link rel="stylesheet" href="CSS/order-button.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/order-button.js" defer></script>
    <script src="JS/order.js" defer></script>
    <title>Shopping Cart | Quantum Gear</title>
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
    <main class="cart-page">
        <h2 class="cart-title"><i class="fa-solid fa-bag-shopping"></i> Shopping Cart</h2>
        <div class="cart-layout">
            <section class="cart-items" id="cartItems">
<?php if ($placed): ?>
                <div class="order-confirm">
                    <i class="fa-solid fa-circle-check"></i>
                    <h3>Thank you! Order #<?= (int) $placed['order_id'] ?> is confirmed.</h3>
                    <p><?= (int) $placed['item_count'] ?> item<?= $placed['item_count'] == 1 ? "" : "s" ?>, <?= money($placed['total']) ?> in total, will be delivered to <strong><?= h($placed['address_label']) ?></strong> (<?= h($placed['address']) ?>).</p>
                    <div class="order-confirm-actions">
                        <a href="my-orders.php#order-<?= (int) $placed['order_id'] ?>" class="order-confirm-btn">Track this order</a>
                        <a href="shop.php" class="order-confirm-link">Continue shopping</a>
                    </div>
                    <p class="order-confirm-note">A confirmation email is on its way.</p>
                </div>
<?php elseif (!$cart): ?>
                <p class="cart-empty">Your cart is empty. <a href="shop.php">Continue shopping</a></p>
<?php else: ?>
                <div class="cart-head">
                    <span>Product</span>
                    <span>Price</span>
                    <span>Quantity</span>
                    <span>Total</span>
                    <span></span>
                </div>
<?php foreach ($cart as $item): $id = (int) $item['product_id']; ?>
                <article class="cart-item" id="item-<?= $id ?>">
                    <div class="cart-item-image">
                        <?= productImage($item) ?>

                    </div>
                    <div class="cart-item-details">
                        <h3 class="cart-item-name"><a href="product.php?id=<?= $id ?>"><?= h($item['name']) ?></a></h3>
                        <p class="cart-item-meta">Category: <strong><?= h($item['category']) ?></strong></p>
<?php if (isOnSale($item)): ?>
                        <span class="status-badge sale">Sale</span>
<?php endif; ?>
                    </div>
                    <div class="cart-item-unit">
                        <?= money($item['price']) ?><?= isOnSale($item) ? " <s>" . money($item['old_price']) . "</s>" : "" ?>

                    </div>
                    <!-- Each button sends the new quantity to PHP/shopping_cart/cart.php -->
                    <form class="qty-control" method="post" action="PHP/shopping_cart/cart.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $id ?>">
                        <?= returnField("order.php#item-$id") ?>

                        <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" aria-label="Decrease quantity"<?= $item['qty'] <= 1 ? " disabled" : "" ?>><i class="fa-solid fa-minus"></i></button>
                        <span><?= (int) $item['qty'] ?></span>
                        <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" aria-label="Increase quantity"<?= $item['qty'] >= $item['stock'] ? " disabled" : "" ?>><i class="fa-solid fa-plus"></i></button>
                    </form>
                    <p class="cart-item-price"><?= money($item['price'] * $item['qty']) ?></p>
                    <form class="cart-item-actions" method="post" action="PHP/shopping_cart/cart.php">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $id ?>">
                        <?= returnField("order.php") ?>

                        <button type="submit" class="icon-btn remove-btn" aria-label="Remove <?= h($item['name']) ?>"><i class="fa-regular fa-trash-can"></i></button>
                    </form>
                </article>
<?php endforeach; ?>
<?php endif; ?>
            </section>
            <aside class="cart-side">
                <!-- Checkout: the summary and the delivery address are one form, sent to PHP/orders/place.php -->
                <form class="checkout-form" id="checkoutForm" method="post" action="PHP/orders/place.php" novalidate>
                    <?= csrfField() ?>

                    <div class="cart-panel">
                        <h3>Order Summary</h3>
                        <div class="summary-row">
                            <span>SubTotal</span>
                            <span><?= money($subtotal) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Discount</span>
                            <span><?= money($subtotal - $total) ?></span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total</span>
                            <span><?= money($total) ?></span>
                        </div>
                        <button type="submit" class="order-btn" id="checkoutBtn"<?= $cart ? "" : " disabled" ?>>
                            <span class="order-bar"></span>
                            <span class="order-label default">Place Order</span>
                            <span class="order-label working"><i class="fa-solid fa-spinner fa-spin"></i> Placing your order...</span>
                            <span class="order-label success"><i class="fa-solid fa-circle-check"></i> Order Placed</span>
                        </button>
<?php if ($cart && empty($_SESSION['user_id'])): ?>
                        <p class="order-error info">Please <a href="login.html">sign in</a> to place your order. Your cart is kept.</p>
<?php endif; ?>
                        <p class="order-error" id="orderError" role="alert" hidden></p>
                    </div>
                    <div class="cart-panel">
                        <h3>Delivery Address</h3>
                        <div class="address-list" id="addressList">
<?php foreach (ADDRESSES as $i => [$label, $address]): ?>
                            <label class="address-option">
                                <input type="radio" name="address" value="<?= $i ?>"<?= $i === 0 ? " checked" : "" ?>>
                                <span>
                                    <strong><?= h($label) ?></strong>
                                    <small><?= h($address) ?></small>
                                </span>
                            </label>
<?php endforeach; ?>
                            <label class="address-option">
                                <input type="radio" name="address" value="new">
                                <span>
                                    <strong>New address</strong>
                                    <small>Deliver somewhere else</small>
                                </span>
                            </label>
                        </div>
                        <!-- Shown when "New address" is picked (JS/order.js) -->
                        <div class="address-form" id="addressForm">
                            <input type="text" name="new_label" id="newLabel" placeholder="Label (e.g. Home)" maxlength="50" aria-label="Address label">
                            <input type="text" name="new_address" id="newAddress" placeholder="Full address" maxlength="255" aria-label="Full address">
                        </div>
                    </div>
                </form>
            </aside>
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