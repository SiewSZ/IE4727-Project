<?php
// Changes an order's status from the Orders tab on the admin page (admins only).
// Cancelling puts the items back in stock; a cancelled order can't be reopened
// (the stock may already be sold again), so the customer would place a new order instead.
// Expects POST fields: order_id, status
// Replies with JSON: {"ok": true} or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requirePost(), requireAdmin()
require_once __DIR__ . "/mailer.php";               // sendOrderEmail()
requirePost();
requireAdmin();

const STATUSES = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

$orderId = (int) ($_POST['order_id'] ?? 0);
$status = $_POST['status'] ?? '';

if (!in_array($status, STATUSES, true)) {
    respond(400, ["error" => "Please choose a valid status."]);
}

mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "SELECT status, customer_name, email FROM orders WHERE order_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$order) {
        throw new RuntimeException("That order no longer exists.");
    }
    if ($order['status'] === 'Cancelled' && $status !== 'Cancelled') {
        throw new RuntimeException("A cancelled order can't be reopened.");
    }

    if ($status === 'Cancelled' && $order['status'] !== 'Cancelled') {
        // Put the items back on the shelf (products deleted since then are skipped)
        $stmt = mysqli_prepare($conn,
            "UPDATE products p JOIN order_items i ON i.product_id = p.product_id
             SET p.stock = p.stock + i.qty
             WHERE i.order_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $orderId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $orderId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (RuntimeException $e) {
    mysqli_rollback($conn);
    respond(400, ["error" => $e->getMessage()]);
}

// Cancelling restocked these products: tell anyone waiting for them
if ($status === 'Cancelled' && $order['status'] !== 'Cancelled') {
    $stmt = mysqli_prepare($conn, "SELECT product_id FROM order_items WHERE order_id = ? AND product_id IS NOT NULL");
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt)) as [$restockedId]) {
        notifyBackInStock($conn, (int) $restockedId);
    }
    mysqli_stmt_close($stmt);
}

// Tell the customer, but only when the status really changed
if ($status !== $order['status']) {
    $messages = [
        "Pending" => "Your order is waiting to be processed.",
        "Processing" => "We are packing your order now.",
        "Shipped" => "Your order is on its way!",
        "Delivered" => "Your order has been delivered. Enjoy your new gear!",
        "Cancelled" => "Your order has been cancelled. If you did not ask for this, please contact us.",
    ];
    sendOrderEmail($conn, $order['email'], "Quantum Gear order #$orderId: $status",
        "Hi {$order['customer_name']},\r\n\r\n{$messages[$status]}\r\n\r\nOrder #$orderId status: $status"
        . "\r\n\r\nYou can follow your orders on the My Orders page.");
}

mysqli_close($conn);
respond(200, ["ok" => true]);
?>
