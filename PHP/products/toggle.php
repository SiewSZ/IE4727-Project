<?php
// Shows or hides a product in the shop (the eye button on the admin page).
// Expects POST fields: product_id, is_active (1 or 0)
// Replies with JSON: {"ok": true} or {"error": "..."}

require_once __DIR__ . "/common.php";
requirePost();

$productId = (int) ($_POST['product_id'] ?? 0);
$isActive = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;

$stmt = mysqli_prepare($conn, "UPDATE products SET is_active = ? WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $isActive, $productId);
mysqli_stmt_execute($stmt);
$found = mysqli_stmt_affected_rows($stmt) > 0;
mysqli_stmt_close($stmt);
mysqli_close($conn);

// affected_rows is 0 both when the id doesn't exist and when nothing changed
respond(200, ["ok" => true, "changed" => $found]);
?>
