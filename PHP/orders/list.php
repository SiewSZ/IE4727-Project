<?php
// Every order with its items, for the Orders tab on the admin page (admins only).
// Replies with JSON: {"orders": [...], "pending": <count>, "revenueThisMonth": <amount>}

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requireAdmin()
requireAdmin();

$orders = [];
$result = mysqli_query($conn, "SELECT * FROM orders ORDER BY created_at DESC, order_id DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $orders[(int) $row['order_id']] = [
        "id" => (int) $row['order_id'],
        "customer" => $row['customer_name'],
        "email" => $row['email'],
        "addressLabel" => $row['address_label'],
        "address" => $row['address'],
        "subtotal" => (float) $row['subtotal'],
        "discount" => (float) $row['discount'],
        "total" => (float) $row['total'],
        "status" => $row['status'],
        "placed" => $row['created_at'],
        "items" => []
    ];
}

$result = mysqli_query($conn, "SELECT order_id, product_id, name, unit_price, qty FROM order_items ORDER BY item_id");
while ($row = mysqli_fetch_assoc($result)) {
    if (isset($orders[(int) $row['order_id']])) {
        $orders[(int) $row['order_id']]['items'][] = [
            "productId" => $row['product_id'] === null ? null : (int) $row['product_id'],
            "name" => $row['name'],
            "unitPrice" => (float) $row['unit_price'],
            "qty" => (int) $row['qty']
        ];
    }
}

// Header cards: orders still waiting, and money taken this calendar month (cancelled orders don't count)
$pending = (int) mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status = 'Pending'"))[0];
$revenue = (float) mysqli_fetch_row(mysqli_query($conn,
    "SELECT COALESCE(SUM(total), 0) FROM orders
     WHERE status <> 'Cancelled' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"))[0];

mysqli_close($conn);
respond(200, ["orders" => array_values($orders), "pending" => $pending, "revenueThisMonth" => $revenue]);
?>
