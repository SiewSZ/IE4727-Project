<?php
// Permanently deletes a user account, from the Users tab on the admin page.
// If that user is signed in somewhere, they are signed out on their next page (refreshSessionUser).
// Expects POST field: user_id
// Replies with JSON: {"ok": true} or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requirePost(), requireAdmin()
require_once __DIR__ . "/admin_checks.php";          // findUser(), adminCount()
requirePost();
requireAdmin();

$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId === (int) $_SESSION['user_id']) {
    respond(400, ["error" => "You can't delete your own account while signed in to it."]);
}

$user = findUser($conn, $userId);
if (!$user) {
    respond(404, ["error" => "That user no longer exists."]);
}
if ($user['role'] === 'admin' && adminCount($conn) <= 1) {
    respond(400, ["error" => "The shop needs at least one admin."]);
}

$stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ["ok" => true]);
?>
