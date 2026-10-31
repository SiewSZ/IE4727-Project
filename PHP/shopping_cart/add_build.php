<?php
// "Add build to cart" on pc-builder.php: adds one of each chosen part to the session cart,
// then opens the cart. A normal form post, so it works without JavaScript.
// Expects POST field: ids[] (product ids)

require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/../users/auth.php";   // starts the session the cart lives in

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../pc-builder.php");
    exit;
}

$ids = array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
$cart = $_SESSION['cart'] ?? [];
$added = 0;

if ($ids) {
    // Only parts that are still for sale and in stock; never more in the cart than in stock
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = mysqli_prepare($conn,
        "SELECT product_id, stock FROM products WHERE is_active = 1 AND stock > 0 AND product_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, str_repeat("i", count($ids)), ...$ids);
    mysqli_stmt_execute($stmt);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) {
        $id = (int) $row['product_id'];
        if (($cart[$id] ?? 0) < $row['stock']) {
            $cart[$id] = ($cart[$id] ?? 0) + 1;
            $added++;
        }
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);

$_SESSION['cart'] = $cart;
header("Location: ../../order.html?build_added=$added");
exit;
