<?php
// Permanently removes a product (and its uploaded image).
// Expects POST field: product_id
// Then back to admin.php (Inventory tab) with a message

require_once __DIR__ . "/common.php";
requirePost("admin.php?tab=inventory");
requireAdmin();

$productId = (int) ($_POST['product_id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT name, image_path FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    goBack("admin.php?tab=inventory", "That product no longer exists.");
}

$stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
deleteUploadedImage($row['image_path']);
goBack("admin.php?tab=inventory", "Deleted \"{$row['name']}\".", "success");
?>
