<?php
// Every user account, for the Users tab on the admin page (admins only).
// Replies with JSON: {"users": [...], "currentUserId": <the signed-in admin's id>}
// Password hashes are never sent.

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requireAdmin()
requireAdmin();

$result = mysqli_query($conn,
    "SELECT id, username, email, role, created_at, last_login FROM users ORDER BY created_at DESC, id DESC");

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = [
        "id" => (int) $row['id'],
        "username" => $row['username'],
        "email" => $row['email'],
        "role" => $row['role'],
        "joined" => $row['created_at'],
        "lastLogin" => $row['last_login']   // null = never signed in
    ];
}

mysqli_close($conn);
respond(200, ["users" => $users, "currentUserId" => (int) $_SESSION['user_id']]);
?>
