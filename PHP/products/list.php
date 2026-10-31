<?php
// Returns products and categories as JSON.
// GET list.php        -> products shown in the shop (is_active = 1)
// GET list.php?all=1  -> every product, including hidden ones (admin page)

require_once __DIR__ . "/common.php";

$showAll = ($_GET['all'] ?? '') === '1';

// Hidden products are only for the admin page; the shop list stays public
if ($showAll) {
    requireAdmin();
}

$sql = "SELECT p.product_id, p.name, p.brand, p.description, p.features, p.part_type, p.socket, p.memory_type, p.wattage, p.price, p.old_price, p.stock,
               p.image_path, p.rating, p.reviews, p.is_active,
               c.category_id, c.name AS category, c.icon,
               (SELECT COUNT(*) FROM stock_alerts a WHERE a.product_id = p.product_id AND a.notified_at IS NULL) AS waiting,
               (SELECT COALESCE(SUM(i.qty), 0) FROM order_items i JOIN orders o USING (order_id)
                WHERE i.product_id = p.product_id AND o.status <> 'Cancelled') AS sold
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
        "features" => $row['features'] ?? "",
        "partType" => $row['part_type'] ?? "",
        "socket" => $row['socket'] ?? "",
        "memoryType" => $row['memory_type'] ?? "",
        "wattage" => $row['wattage'] === null ? "" : (int) $row['wattage'],
        "categoryId" => (int) $row['category_id'],
        "category" => $row['category'],
        "icon" => $row['icon'],
        "price" => (float) $row['price'],
        "oldPrice" => $row['old_price'] === null ? 0 : (float) $row['old_price'],
        "stock" => (int) $row['stock'],
        "image" => $row['image_path'] ?? "",
        "rating" => (int) $row['rating'],
        "reviews" => (int) $row['reviews'],
        "isActive" => $row['is_active'] === '1',
        "waiting" => (int) $row['waiting'],  // customers waiting for a back-in-stock email
        "sold" => (int) $row['sold'],        // units sold in orders that weren't cancelled
        "bestseller" => false
    ];
}

// Tag the top 5 sellers (only products that have actually sold)
$ranked = array_filter($products, fn($p) => $p['sold'] > 0);
usort($ranked, fn($a, $b) => $b['sold'] <=> $a['sold']);
$topIds = array_column(array_slice($ranked, 0, 5), 'id');
foreach ($products as &$p) {
    $p['bestseller'] = in_array($p['id'], $topIds, true);
}
unset($p);

$categories = [];
$result = mysqli_query($conn, "SELECT category_id, name, icon FROM categories ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = ["id" => (int) $row['category_id'], "name" => $row['name'], "icon" => $row['icon']];
}

mysqli_close($conn);
respond(200, ["products" => $products, "categories" => $categories]);
?>
