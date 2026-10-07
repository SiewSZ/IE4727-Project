<?php
// Permanently removes a product (and its uploaded image).
// Expects POST field: product_id
// Replies with JSON: {"ok": true} or {"error": "..."}

require_once __DIR__ . "/common.php";
requirePost();

$productId = (int) ($_POST['product_id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT image_path FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    respond(404, ["error" => "That product no longer exists."]);
}

$stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

deleteUploadedImage($row['image_path']);
respond(200, ["ok" => true]);
?>
