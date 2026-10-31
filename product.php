<?php
// Product details page, built on the server: product.php?id=<product_id>
// Every product card (shop, Hot Deals, related products below) links here.
// Add to Cart / Buy Now use the same session cart as the shop (JS/product.js -> PHP/shopping_cart/cart.php).

require_once __DIR__ . "/PHP/db_connect.php";
require_once __DIR__ . "/PHP/users/auth.php";        // the session, csrfToken() for the review form
require_once __DIR__ . "/PHP/products/render.php";   // h(), money(), productCard() ...

const LOW_STOCK = 5;   // same "low stock" level as the admin page

refreshSessionUser($conn);
$userId = (int) ($_SESSION['user_id'] ?? 0);

$id = (int) ($_GET['id'] ?? 0);

// Hidden products (is_active = 0) are treated as not found, like in the shop
$stmt = mysqli_prepare($conn,
    "SELECT p.*, c.name AS category, c.icon
     FROM products p
     JOIN categories c USING (category_id)
     WHERE p.product_id = ? AND p.is_active = 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$related = [];
if ($product) {
    // Up to 4 other products from the same category, in-stock ones first
    $stmt = mysqli_prepare($conn,
        "SELECT p.*, c.name AS category, c.icon
         FROM products p
         JOIN categories c USING (category_id)
         WHERE p.category_id = ? AND p.product_id <> ? AND p.is_active = 1
         ORDER BY p.stock > 0 DESC, p.product_id
         LIMIT 4");
    mysqli_stmt_bind_param($stmt, "ii", $product['category_id'], $id);
    mysqli_stmt_execute($stmt);
    $related = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // Customer reviews, newest first
    $stmt = mysqli_prepare($conn,
        "SELECT review_id, user_id, username, rating, comment, created_at, updated_at
         FROM reviews WHERE product_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $reviews = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // Only customers who have bought this product (in an order that wasn't cancelled) can review it
    $hasBought = false;
    if ($userId) {
        $stmt = mysqli_prepare($conn,
            "SELECT 1 FROM order_items i JOIN orders o USING (order_id)
             WHERE o.user_id = ? AND i.product_id = ? AND o.status <> 'Cancelled' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $userId, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $hasBought = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
    }
} else {
    http_response_code(404);
}
mysqli_close($conn);

$pageTitle = $product ? $product['name'] : "Product not found";

if ($product) {
    $stock = (int) $product['stock'];
    $onSale = isOnSale($product);
    // Key features are stored one per line (edited on the admin page)
    $features = array_filter(array_map('trim', preg_split('/\R/', (string) $product['features'])));
    // Price split into dollars and cents so the cents can be drawn smaller
    [$dollars, $cents] = explode(".", number_format((float) $product['price'], 2, ".", ","));
    $categoryUrl = "shop.html?category=" . rawurlencode($product['category']);

    // Review summary: average and how many reviews gave each number of stars
    $reviewCount = count($reviews);
    $averageRating = $reviewCount ? array_sum(array_column($reviews, 'rating')) / $reviewCount : 0;
    $starCounts = array_fill_keys([5, 4, 3, 2, 1], 0);
    foreach ($reviews as $review) {
        $starCounts[(int) $review['rating']]++;
    }
    $myReview = null;
    foreach ($reviews as $review) {
        if ($userId && (int) $review['user_id'] === $userId) {
            $myReview = $review;
        }
    }

    // PHP/products/review.php redirects back with ?review=saved or ?review_error=<code>
    $reviewErrors = [
        "signin" => "Please sign in to write a review.",
        "not_bought" => "Only customers who have bought this product can review it.",
        "rating" => "Please choose a rating from 1 to 5 stars.",
        "comment" => "Please write a review between 10 and 1000 characters.",
        "expired" => "Your session expired. Please try again.",
    ];
    $reviewMessage = isset($_GET['review_error'])
        ? ["error", $reviewErrors[$_GET['review_error']] ?? "Your review could not be saved."]
        : (($_GET['review'] ?? '') === 'saved' ? ["success", "Thank you! Your review has been saved."] : null);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/global.css">
    <link rel="stylesheet" href="CSS/shop.css">
    <link rel="stylesheet" href="CSS/product.css">
    <link rel="stylesheet" href="CSS/dark.css">
    <link rel="stylesheet" href="CSS/light.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="JS/theme.js"></script>
    <script src="JS/menu.js" defer></script>
    <script src="JS/session.js" defer></script>
    <script src="JS/products.js" defer></script>
    <script src="JS/product.js" defer></script>
    <title><?= h($pageTitle) ?> | Quantum Gear</title>
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
    <main class="product-page">
<?php if (!$product): ?>
        <section class="pd-missing">
            <i class="fa-solid fa-box-open"></i>
            <h1>Product not found</h1>
            <p>This product may have been removed or is no longer sold. Have a look at what else is in stock.</p>
            <a href="shop.html" class="btn">Browse the shop</a>
        </section>
<?php else: ?>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="index.php">Home</a> <i class="fa-solid fa-chevron-right"></i>
            <a href="shop.html">Shop</a> <i class="fa-solid fa-chevron-right"></i>
            <a href="<?= h($categoryUrl) ?>"><?= h($product['category']) ?></a> <i class="fa-solid fa-chevron-right"></i>
            <span aria-current="page"><?= h($product['name']) ?></span>
        </nav>

        <div class="pd-title">
            <p class="pd-brand"><?= h($product['brand']) ?></p>
            <h1><?= h($product['name']) ?></h1>
            <a class="pd-rating" href="#reviews">
                <?= stars(round($averageRating)) ?>
                <span><?= $reviewCount ? number_format($averageRating, 1) . " ($reviewCount review" . ($reviewCount === 1 ? "" : "s") . ")" : "No reviews yet" ?></span>
            </a>
        </div>

        <div class="product-detail" data-id="<?= (int) $product['product_id'] ?>">
            <div class="pd-gallery<?= $product['image_path'] ? "" : " no-image" ?>">
                <?= productImage($product) ?>
<?php if ($onSale): ?>
                <span class="sale-badge">-<?= percentOff($product) ?>%</span>
<?php endif; ?>
            </div>

            <section class="pd-features" aria-labelledby="featuresTitle">
                <h2 id="featuresTitle">Key Features</h2>
<?php if ($features): ?>
                <ul>
<?php foreach ($features as $feature): ?>
                    <li><?= h($feature) ?></li>
<?php endforeach; ?>
                </ul>
<?php else: ?>
                <p><?= h($product['description'] ?: "Details for this product are coming soon.") ?></p>
<?php endif; ?>
                <p class="pd-category">Category: <a href="<?= h($categoryUrl) ?>"><?= h($product['category']) ?></a></p>
            </section>

            <aside class="pd-buy" aria-label="Price and purchase">
<?php if ($onSale): ?>
                <p class="pd-was"><s><?= money($product['old_price']) ?></s> <span><i class="fa-solid fa-tag"></i> You save <?= money($product['old_price'] - $product['price']) ?> (<?= percentOff($product) ?>%)</span></p>
<?php endif; ?>
                <p class="pd-price"><span class="pd-currency">$</span><?= h($dollars) ?><span class="pd-cents">.<?= h($cents) ?></span></p>

<?php if ($stock === 0): ?>
                <p class="pd-stock out"><i class="fa-solid fa-circle-xmark"></i> Out of stock</p>
<?php elseif ($stock <= LOW_STOCK): ?>
                <p class="pd-stock low"><i class="fa-solid fa-triangle-exclamation"></i> Only <?= $stock ?> left in stock, order soon</p>
<?php else: ?>
                <p class="pd-stock"><i class="fa-solid fa-circle-check"></i> In stock (<?= $stock ?> available)</p>
<?php endif; ?>
                <p class="pd-note"><i class="fa-solid fa-truck-fast"></i> Delivered to any of your saved addresses</p>

<?php if ($stock > 0): ?>
                <form class="pd-cart-form" id="cartForm" novalidate>
                    <div class="pd-qty">
                        <label for="qtyInput">Quantity</label>
                        <input type="number" id="qtyInput" name="qty" value="1" min="1" max="<?= $stock ?>" step="1" required>
                    </div>
                    <p class="pd-message" id="cartMessage" role="status"></p>
                    <div class="pd-actions">
                        <button type="submit" class="pd-primary-btn" data-action="add"><i class="fa-solid fa-cart-shopping"></i> Add to Cart</button>
                        <button type="submit" class="pd-secondary-btn" data-action="buy">Buy Now</button>
                    </div>
                </form>
<?php else: ?>
                <a href="contact.html" class="pd-secondary-btn">Ask when it's back in stock</a>
<?php endif; ?>

                <div class="pd-help">
                    <i class="fa-solid fa-headset"></i>
                    <p>Questions about this product? <a href="contact.html">Ask our team</a></p>
                </div>
            </aside>
        </div>

        <section class="pd-section" aria-labelledby="aboutTitle">
            <h2 id="aboutTitle">About this product</h2>
            <p><?= h($product['description'] ?: "A full description is coming soon.") ?></p>
            <table class="pd-specs">
                <caption>Product details</caption>
                <tbody>
                    <tr><th scope="row">Brand</th><td><?= h($product['brand']) ?></td></tr>
                    <tr><th scope="row">Category</th><td><?= h($product['category']) ?></td></tr>
                    <tr><th scope="row">Price</th><td><?= money($product['price']) ?><?= $onSale ? " (was " . money($product['old_price']) . ", " . percentOff($product) . "% off)" : "" ?></td></tr>
                    <tr><th scope="row">Availability</th><td><?= $stock > 0 ? "$stock in stock" : "Out of stock" ?></td></tr>
                    <tr><th scope="row">Item code</th><td>QG-<?= str_pad((string) $product['product_id'], 5, "0", STR_PAD_LEFT) ?></td></tr>
                </tbody>
            </table>
        </section>

        <section class="pd-section pd-reviews" id="reviews" aria-labelledby="reviewsTitle">
            <h2 id="reviewsTitle">Customer Reviews</h2>
            <div class="reviews-layout">
                <div class="review-summary">
<?php if ($reviewCount): ?>
                    <p class="review-average"><?= number_format($averageRating, 1) ?> <small>out of 5</small></p>
                    <div class="pd-rating"><?= stars(round($averageRating)) ?></div>
                    <p class="review-total"><?= $reviewCount ?> review<?= $reviewCount === 1 ? "" : "s" ?></p>
                    <!-- How many reviews gave each number of stars -->
                    <ul class="star-bars">
<?php foreach ($starCounts as $starsGiven => $count): ?>
                        <li title="<?= $count ?> review<?= $count === 1 ? "" : "s" ?> with <?= $starsGiven ?> star<?= $starsGiven === 1 ? "" : "s" ?>">
                            <span><?= $starsGiven ?> <i class="fa-solid fa-star"></i></span>
                            <span class="star-track"><span class="star-fill" style="width: <?= round($count / $reviewCount * 100) ?>%"></span></span>
                            <span><?= $count ?></span>
                        </li>
<?php endforeach; ?>
                    </ul>
<?php else: ?>
                    <p class="review-average">No reviews yet</p>
                    <p class="review-total">Bought this product? Be the first to tell other shoppers what you think.</p>
<?php endif; ?>
                </div>

                <div class="review-main">
<?php if ($reviewMessage): ?>
                    <p class="review-message <?= $reviewMessage[0] ?>" role="status"><?= h($reviewMessage[1]) ?></p>
<?php endif; ?>
<?php if ($hasBought): ?>
                    <!-- HTML5 checks a rating is chosen and the length; JS/product.js and PHP/products/review.php check again -->
                    <form class="review-form" id="reviewForm" method="post" action="PHP/products/review.php">
                        <h3><?= $myReview ? "Update your review" : "Write a review" ?></h3>
                        <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <fieldset class="star-input">
                            <legend>Your rating</legend>
<?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required<?= $myReview && (int) $myReview['rating'] === $i ? " checked" : "" ?>>
                            <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? "" : "s" ?>"><i class="fa-solid fa-star"></i><span class="visually-hidden"><?= $i ?> star<?= $i === 1 ? "" : "s" ?></span></label>
<?php endfor; ?>
                        </fieldset>
                        <label for="reviewComment">Your review</label>
                        <textarea id="reviewComment" name="comment" rows="4" minlength="10" maxlength="1000" required placeholder="What did you like or dislike? How are you using it?"><?= $myReview ? h($myReview['comment']) : "" ?></textarea>
                        <p class="field-error" id="reviewError"></p>
                        <button type="submit" class="pd-primary-btn"><?= $myReview ? "Update review" : "Post review" ?></button>
                    </form>
<?php elseif ($userId): ?>
                    <p class="review-note"><i class="fa-solid fa-circle-info"></i> Reviews are written by customers who bought this product, so you can review it after your order.</p>
<?php else: ?>
                    <p class="review-note"><i class="fa-solid fa-circle-info"></i> <a href="login.html">Sign in</a> to review products you have bought.</p>
<?php endif; ?>

<?php if ($reviews): ?>
                    <ul class="review-list">
<?php foreach ($reviews as $review): ?>
                        <li class="review">
                            <div class="review-head">
                                <span class="pd-rating"><?= stars((int) $review['rating']) ?></span>
                                <strong><?= h($review['username']) ?></strong>
                                <span class="verified"><i class="fa-solid fa-circle-check"></i> Verified purchase</span>
                            </div>
                            <p class="review-date"><?= h(date("d M Y", strtotime($review['created_at']))) ?><?= $review['updated_at'] ? " (edited)" : "" ?></p>
                            <p><?= nl2br(h($review['comment'])) ?></p>
                        </li>
<?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </div>
            </div>
        </section>

<?php if ($related): ?>
        <section class="pd-related" aria-labelledby="relatedTitle">
            <h2 id="relatedTitle">More in <?= h($product['category']) ?></h2>
            <div class="product-grid">
<?php foreach ($related as $item) { productCard($item); } ?>
            </div>
        </section>
<?php endif; ?>
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