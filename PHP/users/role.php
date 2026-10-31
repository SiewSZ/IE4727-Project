<?php
// Makes a user an admin or a customer, from the Users tab on the admin page.
// Expects POST fields: user_id, role ('customer' or 'admin')
// Replies with JSON: {"ok": true} or {"error": "..."}

require_once __DIR__ . "/../products/common.php";   // $conn, respond(), requirePost(), requireAdmin()
require_once __DIR__ . "/admin_checks.php";          // findUser(), adminCount()
requirePost();
requireAdmin();

$userId = (int) ($_POST['user_id'] ?? 0);
$role = $_POST['role'] ?? '';

if (!in_array($role, ['customer', 'admin'], true)) {
    respond(400, ["error" => "Role must be customer or admin."]);
}
if ($userId === (int) $_SESSION['user_id']) {
    respond(400, ["error" => "You can't change your own role. Ask another admin."]);
}

$user = findUser($conn, $userId);
if (!$user) {
    respond(404, ["error" => "That user no longer exists."]);
}
// Never leave the shop without an admin
if ($user['role'] === 'admin' && $role === 'customer' && adminCount($conn) <= 1) {
    respond(400, ["error" => "The shop needs at least one admin."]);
}

$stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "si", $role, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

respond(200, ["ok" => true]);
?>
