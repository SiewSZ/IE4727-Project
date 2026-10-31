<?php
// Places an order from the session cart (the "Place Order" button on order.html).
// Expects POST fields: address_label, address
// Everything happens in one transaction: check stock, save the order and its items, take the
// items out of stock. If anything fails, nothing is saved and the cart is left as it was.
// Replies with JSON: {"ok": true, "orderId": 12, "total": 996.00, "itemCount": 4} or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requirePost() and the session
requirePost();

if (empty($_SESSION['user_id'])) {
    respond(401, ["error" => "Please sign in to place your order."]);
}

$label = trim($_POST['address_label'] ?? '');
$address = trim($_POST['address'] ?? '');
if ($label === '' || $address === '') {
    respond(400, ["error" => "Please choose a delivery address."]);
}
if (strlen($label) > 50 || strlen($address) > 255) {
    respond(400, ["error" => "The delivery address is too long."]);
}

$cart = $_SESSION['cart'] ?? [];   // [product_id => quantity]
if (!$cart) {
    respond(400, ["error" => "Your cart is empty."]);
}

// Name and email are copied onto the order
$stmt = mysqli_prepare($conn, "SELECT username, email FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$customer) {
    respond(401, ["error" => "Please sign in again to place your order."]);
}

mysqli_begin_transaction($conn);
try {
    // FOR UPDATE locks these product rows until COMMIT, so two people can't buy the last unit at once
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = mysqli_prepare($conn,
        "SELECT product_id, name, price, old_price, stock FROM products
         WHERE is_active = 1 AND product_id IN ($placeholders) FOR UPDATE");
    mysqli_stmt_bind_param($stmt, str_repeat("i", count($ids)), ...$ids);
    mysqli_stmt_execute($stmt);
    $products = array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), null, 'product_id');
    mysqli_stmt_close($stmt);

    $lines = [];
    $subtotal = 0;
    $total = 0;
    foreach ($cart as $id => $qty) {
        $p = $products[$id] ?? null;
        if (!$p) {
            throw new RuntimeException("One of the products in your cart is no longer sold. Please review your cart.");
        }
        if ($qty > $p['stock']) {
            throw new RuntimeException($p['stock'] > 0
                ? "Sorry, only {$p['stock']} of {$p['name']} left in stock. Please update your cart."
                : "Sorry, {$p['name']} has just sold out. Please remove it from your cart.");
        }
        $regular = $p['old_price'] ?? $p['price'];
        $subtotal += $regular * $qty;
        $total += $p['price'] * $qty;
        $lines[] = [(int) $id, $p['name'], $p['price'], $regular, (int) $qty];
    }
    $discount = $subtotal - $total;

    $stmt = mysqli_prepare($conn,
        "INSERT INTO orders (user_id, customer_name, email, address_label, address, subtotal, discount, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issssddd", $_SESSION['user_id'], $customer['username'], $customer['email'],
        $label, $address, $subtotal, $discount, $total);
    mysqli_stmt_execute($stmt);
    $orderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $itemStmt = mysqli_prepare($conn,
        "INSERT INTO order_items (order_id, product_id, name, unit_price, regular_price, qty) VALUES (?, ?, ?, ?, ?, ?)");
    $stockStmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE product_id = ?");
    foreach ($lines as [$productId, $name, $unitPrice, $regular, $qty]) {
        mysqli_stmt_bind_param($itemStmt, "iisddi", $orderId, $productId, $name, $unitPrice, $regular, $qty);
        mysqli_stmt_execute($itemStmt);
        mysqli_stmt_bind_param($stockStmt, "ii", $qty, $productId);
        mysqli_stmt_execute($stockStmt);
    }
    mysqli_stmt_close($itemStmt);
    mysqli_stmt_close($stockStmt);

    mysqli_commit($conn);
} catch (RuntimeException $e) {
    mysqli_rollback($conn);
    respond(409, ["error" => $e->getMessage()]);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    respond(500, ["error" => "Your order could not be placed. Please try again."]);
}

// Only now that the order is saved is the cart emptied
$_SESSION['cart'] = [];
mysqli_close($conn);

respond(200, ["ok" => true, "orderId" => $orderId, "total" => round($total, 2), "itemCount" => array_sum($cart)]);
?>
