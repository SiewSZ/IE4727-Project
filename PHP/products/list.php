<?php
// Returns products and categories as JSON.
// GET list.php        -> products shown in the shop (is_active = 1)
// GET list.php?all=1  -> every product, including hidden ones (admin page)

require_once __DIR__ . "/common.php";

$showAll = ($_GET['all'] ?? '') === '1';

$sql = "SELECT p.product_id, p.name, p.brand, p.description, p.price, p.old_price, p.stock,
               p.image_path, p.rating, p.reviews, p.is_active,
               c.category_id, c.name AS category, c.icon
        FROM products p
        JOIN categories c USING (category_id)"
     . ($showAll ? "" : " WHERE p.is_active = 1")
     . " ORDER BY p.product_id";

$products = [];
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    // MySQL returns every value as a string, so convert numbers back
    $products[] = [
        "id" => (int) $row['product_id'],
        "name" => $row['name'],
        "brand" => $row['brand'],
        "description" => $row['description'] ?? "",
        "categoryId" => (int) $row['category_id'],
        "category" => $row['category'],
        "icon" => $row['icon'],
        "price" => (float) $row['price'],
        "oldPrice" => $row['old_price'] === null ? 0 : (float) $row['old_price'],
        "stock" => (int) $row['stock'],
        "image" => $row['image_path'] ?? "",
        "rating" => (int) $row['rating'],
        "reviews" => (int) $row['reviews'],
        "isActive" => $row['is_active'] === '1'
    ];
}

$categories = [];
$result = mysqli_query($conn, "SELECT category_id, name, icon FROM categories ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = ["id" => (int) $row['category_id'], "name" => $row['name'], "icon" => $row['icon']];
}

mysqli_close($conn);
respond(200, ["products" => $products, "categories" => $categories]);
?>
