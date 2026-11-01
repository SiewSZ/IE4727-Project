<?php
// Makes a user an admin or a customer, from the Users tab on the admin page.
// Expects POST fields: user_id, role ('customer' or 'admin')
// Then back to admin.php (Users tab) with a message

require_once __DIR__ . "/../products/common.php";   // $conn, goBack(), requirePost(), requireAdmin()
require_once __DIR__ . "/admin_checks.php";          // findUser(), adminCount()
requirePost("admin.php?tab=users");
requireAdmin();

$userId = (int) ($_POST['user_id'] ?? 0);
$role = $_POST['role'] ?? '';

if (!in_array($role, ['customer', 'admin'], true)) {
    goBack("admin.php?tab=users", "Role must be customer or admin.");
}
if ($userId === (int) $_SESSION['user_id']) {
    goBack("admin.php?tab=users", "You can't change your own role. Ask another admin.");
}

$user = findUser($conn, $userId);
if (!$user) {
    goBack("admin.php?tab=users", "That user no longer exists.");
}
// Never leave the shop without an admin
if ($user['role'] === 'admin' && $role === 'customer' && adminCount($conn) <= 1) {
    goBack("admin.php?tab=users", "The shop needs at least one admin.");
}

$stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "si", $role, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
goBack("admin.php?tab=users", "{$user['username']} is now " . ($role === 'admin' ? "an admin" : "a customer") . ".", "success");
?>
