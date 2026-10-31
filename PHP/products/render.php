<?php
// HTML helpers for pages built on the server (hotdeals.php, product.php).
// The product card matches the one JS/products.js draws on shop.html, and both use CSS/shop.css.
// $p is a products row joined with its category: name, brand, price, old_price, stock,
// image_path, rating, reviews, category, icon, product_id.

// Database text goes into HTML, so escape it first
function h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function money($amount)
{
    return "$" . number_format((float) $amount, 2);
}

function isOnSale($p)
{
    return $p['old_price'] !== null && (float) $p['old_price'] > (float) $p['price'];
}

function percentOff($p)
{
    return (int) round((1 - $p['price'] / $p['old_price']) * 100);
}

function stars($rating)
{
    $html = "";
    for ($i = 0; $i < 5; $i++) {
        $html .= '<i class="fa-' . ($i < $rating ? "solid" : "regular") . ' fa-star"></i>';
    }
    return $html;
}

function productUrl($p)
{
    return "product.php?id=" . (int) $p['product_id'];
}

function productImage($p)
{
    return $p['image_path']
        ? '<img src="' . h($p['image_path']) . '" alt="' . h($p['name']) . '">'
        : '<i class="fa-solid ' . h($p['icon'] ?: "fa-box") . '"></i>';
}

function productCard($p)
{
    $inStock = $p['stock'] > 0;
    ?>
                <article class="product-card" data-id="<?= (int) $p['product_id'] ?>">
                    <a class="product-image<?= $p['image_path'] ? "" : " no-image" ?>" href="<?= productUrl($p) ?>">
                        <?= productImage($p) ?>
<?php if (isOnSale($p)): ?>
                        <span class="sale-badge">-<?= percentOff($p) ?>%</span>
<?php endif; ?>
                    </a>
                    <button class="wishlist-btn" aria-label="Add to wishlist"><i class="fa-regular fa-heart"></i></button>
                    <div class="product-info">
                        <p class="product-category"><?= h($p['category']) ?></p>
                        <h3 class="product-name" title="<?= h($p['name']) ?>"><a href="<?= productUrl($p) ?>"><?= h($p['name']) ?></a></h3>
                        <div class="product-rating"><?= stars($p['rating']) ?><span><?= (int) $p['reviews'] ?> Reviews</span></div>
                        <p class="product-stock"><?= $inStock ? "In Stock <span>" . (int) $p['stock'] . "</span>" : '<span class="out">Out of Stock</span>' ?></p>
                        <p class="product-price"><?= money($p['price']) ?><?= isOnSale($p) ? " <s>" . money($p['old_price']) . "</s>" : "" ?></p>
<?php if (isOnSale($p)): ?>
                        <p class="deal-saving"><i class="fa-solid fa-tag"></i> You save <?= money($p['old_price'] - $p['price']) ?></p>
<?php endif; ?>
                        <button class="add-cart-btn" <?= $inStock ? "" : "disabled" ?>>
                            <i class="fa-solid fa-cart-shopping"></i> Add to Cart
                        </button>
                    </div>
                </article>
<?php
}

// Small Amazon-style card for the scrolling product rows on the home page: picture, price, name.
// The whole card is one link to the product page.
function miniCard($p)
{
    ?>
                    <li>
                        <a class="mini-card" href="<?= productUrl($p) ?>">
                            <span class="mini-image<?= $p['image_path'] ? "" : " no-image" ?>">
                                <?= productImage($p) ?>
<?php if (isOnSale($p)): ?>
                                <span class="sale-badge">-<?= percentOff($p) ?>%</span>
<?php endif; ?>
                            </span>
                            <span class="mini-price"><?= money($p['price']) ?><?= isOnSale($p) ? " <s>" . money($p['old_price']) . "</s>" : "" ?></span>
                            <span class="mini-name"><?= h($p['name']) ?></span>
<?php if ($p['stock'] == 0): ?>
                            <span class="mini-stock out">Out of stock</span>
<?php elseif ($p['stock'] <= 5): ?>
                            <span class="mini-stock low">Only <?= (int) $p['stock'] ?> left</span>
<?php endif; ?>
                        </a>
                    </li>
<?php
}

// A titled, horizontally scrolling row of mini cards (JS/home.js adds the arrow buttons' scrolling)
function productRow($id, $title, $linkText, $linkUrl, $products)
{
    if (!$products) {
        return;
    }
    ?>
        <section class="product-row" aria-labelledby="<?= h($id) ?>">
            <div class="row-head">
                <h2 id="<?= h($id) ?>"><?= h($title) ?></h2>
                <a href="<?= h($linkUrl) ?>"><?= h($linkText) ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="row-scroller">
                <button type="button" class="row-btn prev" aria-label="Scroll <?= h($title) ?> left"><i class="fa-solid fa-chevron-left"></i></button>
                <ul class="row-track">
<?php foreach ($products as $p) { miniCard($p); } ?>
                </ul>
                <button type="button" class="row-btn next" aria-label="Scroll <?= h($title) ?> right"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </section>
<?php
}
?>
