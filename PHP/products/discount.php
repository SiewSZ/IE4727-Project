<?php
// Bulk sale from the admin page: puts every product in a category (or the whole shop) on sale
// at the same percentage, or ends the sale when the percentage is 0.
// Expects POST fields: category_id (0 = all products), percent (0 to 90)
// Replies with JSON: {"ok": true, "updated": <number of products changed>} or {"error": "..."}

require_once __DIR__ . "/common.php";
requirePost();
requireAdmin();

$categoryId = (int) ($_POST['category_id'] ?? 0);
$percent = $_POST['percent'] ?? '';

if (!preg_match('/^\d{1,2}$/', (string) $percent) || $percent > 90) {
    respond(400, ["error" => "Discount must be a whole number from 0 to 90."]);
}
$percent = (int) $percent;

if ($percent === 0) {
    // End the sale: the regular price becomes the price again
    $sql = "UPDATE products SET price = COALESCE(old_price, price), old_price = NULL
            WHERE (? = 0 OR category_id = ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $categoryId, $categoryId);
} else {
    // MySQL applies these left to right: old_price is first set to the regular price
    // (kept if the product was already on sale), then price is worked out from it.
    // So running a new sale replaces the old discount instead of stacking on top of it.
    $sql = "UPDATE products
            SET old_price = COALESCE(old_price, price),
                price = ROUND(old_price * (100 - ?) / 100, 2)
            WHERE (? = 0 OR category_id = ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $percent, $categoryId, $categoryId);
}

mysqli_stmt_execute($stmt);
$updated = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ["ok" => true, "updated" => $updated]);
?>
