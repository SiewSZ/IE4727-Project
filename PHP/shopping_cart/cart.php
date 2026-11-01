<?php
// Changes the session cart (cart_items.php). The action of the Add to Cart forms on the product
// cards, product page and Hot Deals bundles, and of the quantity / remove buttons on order.php.
//   action=add    product_id [qty] -> add to the cart (qty defaults to 1)
//   action=update product_id qty   -> set the quantity
//   action=remove product_id       -> take the product out
//   action=bundle product_ids[]    -> add one of each product
// Then goes back to the page in the "return" field with a message, or to the cart
// when then=checkout (the Buy Now button on product.php).

require_once __DIR__ . "/../products/common.php";   // $conn, goBack(), returnPage() and the session
require_once __DIR__ . "/cart_items.php";           // loadCartProducts()

$back = returnPage("order.php");
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    goBack($back);
}

$cart = $_SESSION['cart'] ?? [];
$action = $_POST['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? 0);
$qty = $_POST['qty'] ?? '1';
$viewCart = ["order.php", "View cart"];

// Check again on the server: never let the cart hold more than is in stock.
// Returns an error message, or "" when the quantity is fine.
function checkQuantity($product, $newQty)
{
    $stock = (int) $product['stock'];
    if ($newQty < 1) {
        return "Quantity must be at least 1.";
    }
    if ($stock === 0) {
        return "Sorry, {$product['name']} is out of stock.";
    }
    if ($newQty > $stock) {
        return "Sorry, only $stock of {$product['name']} left in stock.";
    }
    return "";
}

if ($action === 'remove') {
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
    goBack($back, "Item removed from your cart.", "success");
}

if ($action === 'add' || $action === 'update') {
    // A whole number of at least 1 (the quantity box on product.php can be typed into)
    if (!preg_match('/^\d+$/', (string) $qty) || (int) $qty < 1) {
        goBack($back, "Please enter a quantity of at least 1.");
    }
    $qty = (int) $qty;

    $product = loadCartProducts($conn, [$productId])[$productId] ?? null;
    if (!$product) {
        goBack($back, "Sorry, that product is no longer available.");
    }

    $newQty = $action === 'add' ? ($cart[$productId] ?? 0) + $qty : $qty;
    $error = checkQuantity($product, $newQty);
    if ($error !== "") {
        goBack($back, $error);
    }

    $cart[$productId] = $newQty;
    $_SESSION['cart'] = $cart;

    if ($action === 'update') {
        goBack($back);   // the cart page itself shows the new quantity and totals
    }
    if (($_POST['then'] ?? '') === 'checkout') {
        goBack("order.php");
    }
    goBack($back, "Added $qty × {$product['name']} to your cart.", "success", ...$viewCart);
}

if ($action === 'bundle') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['product_ids'] ?? [])))));
    $products = loadCartProducts($conn, $ids);
    $added = 0;
    $problems = [];
    foreach ($ids as $id) {
        $product = $products[$id] ?? null;
        $error = $product ? checkQuantity($product, ($cart[$id] ?? 0) + 1) : "One of the products is no longer available.";
        if ($error !== "") {
            $problems[] = $error;
            continue;
        }
        $cart[$id] = ($cart[$id] ?? 0) + 1;
        $added++;
    }
    $_SESSION['cart'] = $cart;

    if (!$problems) {
        goBack($back, "Added all $added items to your cart.", "success", ...$viewCart);
    }
    goBack($back, ($added ? "Added $added of " . count($ids) . " items. " : "") . implode(" ", $problems),
        "error", ...$viewCart);
}

goBack($back, "Unknown cart action.");
?>
