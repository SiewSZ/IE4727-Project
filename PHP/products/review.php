<?php
// Saves a customer's review from the form on product.php (a normal form post, then a redirect back).
// Expects POST fields: product_id, rating (1-5), comment, csrf_token
// Success: back to product.php?id=<id>&review=saved#reviews
// Failure: back to product.php?id=<id>&review_error=<code>#reviews
// A customer can review a product once, after buying it; sending the form again updates their review.

require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/../users/auth.php";   // the session, csrfValid(), refreshSessionUser()

$productId = (int) ($_POST['product_id'] ?? 0);

function backToProduct($productId, $query)
{
    header("Location: ../../product.php?id=$productId&$query#reviews");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $productId <= 0) {
    header("Location: ../../shop.html");
    exit;
}
if (!csrfValid()) {
    backToProduct($productId, "review_error=expired");
}

refreshSessionUser($conn);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$userId) {
    backToProduct($productId, "review_error=signin");
}

// Same checks as the browser, repeated because they can be bypassed
$rating = $_POST['rating'] ?? '';
$comment = trim($_POST['comment'] ?? '');
if (!in_array($rating, ['1', '2', '3', '4', '5'], true)) {
    backToProduct($productId, "review_error=rating");
}
if (mb_strlen($comment) < 10 || mb_strlen($comment) > 1000) {
    backToProduct($productId, "review_error=comment");
}

// Only customers who bought it (in an order that wasn't cancelled)
$stmt = mysqli_prepare($conn,
    "SELECT 1 FROM order_items i JOIN orders o USING (order_id)
     WHERE o.user_id = ? AND i.product_id = ? AND o.status <> 'Cancelled' LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $userId, $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$hasBought = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);
if (!$hasBought) {
    backToProduct($productId, "review_error=not_bought");
}

$rating = (int) $rating;
$username = $_SESSION['username'];

// New review, or update the one this customer already wrote (UNIQUE product_id + user_id)
$stmt = mysqli_prepare($conn,
    "INSERT INTO reviews (product_id, user_id, username, rating, comment) VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = NOW()");
mysqli_stmt_bind_param($stmt, "iisis", $productId, $userId, $username, $rating, $comment);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Keep the stars and review count on the product cards in step with the reviews
$stmt = mysqli_prepare($conn,
    "UPDATE products p
     SET p.rating = (SELECT ROUND(AVG(r.rating)) FROM reviews r WHERE r.product_id = p.product_id),
         p.reviews = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.product_id)
     WHERE p.product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

backToProduct($productId, "review=saved");
