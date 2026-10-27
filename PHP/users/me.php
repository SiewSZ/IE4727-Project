<?php
// Tells a page who is signed in, e.g. so admin.html can send non-admins to the login page.
// Replies with JSON: {"loggedIn": true, "username": "...", "role": "admin"} or {"loggedIn": false}

require_once __DIR__ . "/auth.php";

header("Content-Type: application/json");

if (empty($_SESSION['user_id'])) {
    echo json_encode(["loggedIn" => false]);
    exit;
}

echo json_encode([
    "loggedIn" => true,
    "username" => $_SESSION['username'],
    "role" => $_SESSION['role'] ?? "customer"
]);
