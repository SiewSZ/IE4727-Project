<?php
// The shopping cart, kept in the PHP session so it follows the visitor from page to page
// (guests too). Stored as $_SESSION['cart'] = [product_id => quantity].
//   GET  cart.php                                -> the cart, with product details from the database
//   POST cart.php action=add    product_id [qty] -> add to the cart (qty defaults to 1)
//   POST cart.php action=update product_id qty   -> set the quantity
//   POST cart.php action=remove product_id       -> take the product out
// Every reply is JSON: {"items": [...], "count": <total quantity>} or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond() and the session

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

$cart = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty = (int) ($_POST['qty'] ?? 1);

    if ($action === 'remove') {
        unset($cart[$productId]);
    } elseif ($action === 'add' || $action === 'update') {
        $product = loadCartProducts($conn, [$productId])[$productId] ?? null;
        if (!$product) {
            respond(404, ["error" => "Sorry, that product is no longer available."]);
        }

        // Check again on the server: never let the cart hold more than is in stock
        $newQty = $action === 'add' ? ($cart[$productId] ?? 0) + max(1, $qty) : $qty;
        $stock = (int) $product['stock'];
        if ($newQty < 1) {
            respond(400, ["error" => "Quantity must be at least 1."]);
        }
        if ($stock === 0) {
            respond(409, ["error" => "Sorry, {$product['name']} is out of stock."]);
        }
        if ($newQty > $stock) {
            respond(409, ["error" => "Sorry, only $stock of {$product['name']} left in stock."]);
        }
        $cart[$productId] = $newQty;
    } else {
        respond(400, ["error" => "Unknown cart action."]);
    }
}

// Build the reply from the database so names and prices are always current. Products deleted,
// hidden or sold out since they were added are dropped, and quantities are capped at the stock left.
$products = loadCartProducts($conn, array_keys($cart));
$items = [];
foreach ($cart as $id => $qty) {
    $p = $products[$id] ?? null;
    if (!$p || (int) $p['stock'] === 0) {
        unset($cart[$id]);
        continue;
    }

    $cart[$id] = min($qty, (int) $p['stock']);
    $items[] = [
        "id" => $id,
        "name" => $p['name'],
        "category" => $p['category'],
        "icon" => $p['icon'],
        "price" => (float) $p['price'],
        "oldPrice" => $p['old_price'] === null ? 0 : (float) $p['old_price'],
        "stock" => (int) $p['stock'],
        "image" => $p['image_path'] ?? "",
        "qty" => $cart[$id]
    ];
}

$_SESSION['cart'] = $cart;
mysqli_close($conn);
respond(200, ["items" => $items, "count" => array_sum($cart)]);
?>
