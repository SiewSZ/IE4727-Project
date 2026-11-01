<?php
// Shows or hides a product in the shop (the eye button on the admin page).
// Expects POST fields: product_id, is_active (1 or 0)
// Then back to admin.php (Inventory tab), at the product's row

require_once __DIR__ . "/common.php";
requirePost("admin.php?tab=inventory");
requireAdmin();

$productId = (int) ($_POST['product_id'] ?? 0);
$isActive = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;

$stmt = mysqli_prepare($conn, "UPDATE products SET is_active = ? WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $isActive, $productId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

goBack("admin.php?tab=inventory#product-$productId", $isActive ? "The product is back in the shop." : "The product is hidden from the shop.", "success");
?>
