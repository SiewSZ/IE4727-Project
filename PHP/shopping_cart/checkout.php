<?php
// Turns the session cart into an order. Called by the Complete Order button on order.html.
//   POST checkout.php address=<delivery address>
// Saves the order in the orders and order_items tables, takes the items out of stock and
// empties the session cart. Guests can check out too (user_id is then NULL).
// Replies with JSON: {"orderId": 12, "total": 1528.00, "count": 0} or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requirePost() and the session
requirePost();

$cart = $_SESSION['cart'] ?? [];
$address = trim((string) ($_POST['address'] ?? ''));

if (!$cart) {
    respond(400, ["error" => "Your cart is empty."]);
}
if ($address === '' || strlen($address) > 255) {
    respond(400, ["error" => "Please choose a delivery address."]);
}

// Everything below is one transaction: either the whole order is saved and the stock taken,
// or nothing changes. FOR UPDATE locks the product rows so two customers can't buy the last one.
mysqli_begin_transaction($conn);
try {
    $ids = array_keys($cart);
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = mysqli_prepare($conn,
        "SELECT product_id, name, price, stock
         FROM products
         WHERE is_active = 1 AND product_id IN ($placeholders)
         FOR UPDATE");
    mysqli_stmt_bind_param($stmt, str_repeat("i", count($ids)), ...$ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[(int) $row['product_id']] = $row;
    }
    mysqli_stmt_close($stmt);

    // Check the stock again and add up the total from database prices, never from the page
    $total = 0;
    foreach ($cart as $id => $qty) {
        $p = $products[$id] ?? null;
        $problem = null;
        if (!$p) {
            $problem = "an item in your cart is no longer available";
        } elseif ((int) $p['stock'] < $qty) {
            $problem = (int) $p['stock'] === 0
                ? "{$p['name']} has sold out"
                : "only {$p['stock']} of {$p['name']} are left";
        }
        if ($problem) {
            mysqli_rollback($conn);
            respond(409, ["error" => "Sorry, $problem. Your cart has been updated, please check it and place the order again."]);
        }
        $total += $p['price'] * $qty;
    }
    $total = round($total, 2);

    $userId = $_SESSION['user_id'] ?? null;
    $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, address, total) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isd", $userId, $address, $total);
    mysqli_stmt_execute($stmt);
    $orderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $addItem = mysqli_prepare($conn,
        "INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty) VALUES (?, ?, ?, ?, ?)");
    $takeStock = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE product_id = ?");
    foreach ($cart as $id => $qty) {
        $p = $products[$id];
        mysqli_stmt_bind_param($addItem, "iisdi", $orderId, $id, $p['name'], $p['price'], $qty);
        mysqli_stmt_execute($addItem);
        mysqli_stmt_bind_param($takeStock, "ii", $qty, $id);
        mysqli_stmt_execute($takeStock);
    }
    mysqli_stmt_close($addItem);
    mysqli_stmt_close($takeStock);

    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    // 1146 = table doesn't exist: the database was made before orders were added to schema.sql
    if ($e->getCode() === 1146) {
        respond(500, ["error" => "The orders table is missing. Import database/schema.sql again in phpMyAdmin."]);
    }
    respond(500, ["error" => "Your order could not be saved. Please try again."]);
}

// The order is saved, so empty the cart
$_SESSION['cart'] = [];
mysqli_close($conn);
respond(200, ["orderId" => $orderId, "total" => $total, "count" => 0]);
?>
