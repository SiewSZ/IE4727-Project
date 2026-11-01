<?php
// The shopping cart, kept in the PHP session so it follows the visitor from page to page
// (guests too). Stored as $_SESSION['cart'] = [product_id => quantity].
// Used by cart.php (changes to the cart) and order.php (the cart page).

// The saved delivery addresses offered at checkout (order.php, PHP/orders/place.php): [label, address]
const ADDRESSES = [
    ["Home", "50 Nanyang Ave, Singapore 639798"],
    ["Office", "1 Fusionopolis Way, Singapore 138632"],
];

// The active products with these ids, keyed by product_id
function loadCartProducts($conn, array $ids)
{
    if (!$ids) {
        return [];
    }

    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = mysqli_prepare($conn,
        "SELECT p.product_id, p.name, p.price, p.old_price, p.stock, p.image_path, c.name AS category, c.icon
         FROM products p
         JOIN categories c USING (category_id)
         WHERE p.is_active = 1 AND p.product_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, str_repeat("i", count($ids)), ...$ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[(int) $row['product_id']] = $row;
    }
    mysqli_stmt_close($stmt);
    return $products;
}

// The cart's items with product details from the database, so names and prices are always current.
// Products deleted, hidden or sold out since they were added are dropped, and quantities are
// capped at the stock left (the session cart is updated to match).
function cartItems($conn)
{
    $cart = $_SESSION['cart'] ?? [];
    $products = loadCartProducts($conn, array_keys($cart));
    $items = [];
    foreach ($cart as $id => $qty) {
        $p = $products[$id] ?? null;
        if (!$p || (int) $p['stock'] === 0) {
            unset($cart[$id]);
            continue;
        }
        $cart[$id] = min($qty, (int) $p['stock']);
        $p['qty'] = $cart[$id];
        $items[] = $p;
    }
    $_SESSION['cart'] = $cart;
    return $items;
}
?>
