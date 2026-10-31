<?php
// Tells a page what is in the session: who is signed in and how many items are in the cart.
// JS/session.js uses it to update the header; admin.html uses it to send non-admins away.
// Replies with JSON: {"loggedIn": true, "username": "...", "role": "admin", "cartCount": 3}
//                 or {"loggedIn": false, "cartCount": 0}

require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/auth.php";   // also signs out a user who has been inactive too long

// Pick up role changes (or a deleted account) made on the admin Users tab since they signed in
refreshSessionUser($conn);
mysqli_close($conn);

header("Content-Type: application/json");

$reply = ["loggedIn" => !empty($_SESSION['user_id'])];

if ($reply['loggedIn']) {
    $reply['username'] = $_SESSION['username'];
    $reply['role'] = $_SESSION['role'] ?? "customer";
}

// The cart is kept as [product_id => quantity]
$reply['cartCount'] = array_sum($_SESSION['cart'] ?? []);

echo json_encode($reply);
