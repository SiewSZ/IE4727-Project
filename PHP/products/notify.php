<?php
// "Email me when it's back" form on a sold-out product page (product.php).
// Expects POST fields: product_id, email
// Success: back to product.php?id=<id>&alert=saved#buy
// Failure: back to product.php?id=<id>&alert_error=email#buy
// The email is sent by notifyBackInStock() (PHP/orders/mailer.php) when the product is restocked.

require_once __DIR__ . "/../db_connect.php";

$productId = (int) ($_POST['product_id'] ?? 0);

function backToProduct($productId, $query)
{
    header("Location: ../../product.php?id=$productId&$query#buy");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $productId <= 0) {
    header("Location: ../../shop.html");
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    backToProduct($productId, "alert_error=email");
}

// Only for products that exist; asking twice for the same product is fine (INSERT IGNORE)
$stmt = mysqli_prepare($conn,
    "INSERT IGNORE INTO stock_alerts (product_id, email)
     SELECT product_id, ? FROM products WHERE product_id = ? AND is_active = 1");
mysqli_stmt_bind_param($stmt, "si", $email, $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

backToProduct($productId, "alert=saved");
