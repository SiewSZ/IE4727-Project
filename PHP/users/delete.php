<?php
// Permanently deletes a user account, from the Users tab on the admin page.
// If that user is signed in somewhere, they are signed out on their next page (refreshSessionUser).
// Expects POST field: user_id
// Then back to admin.php (Users tab) with a message

require_once __DIR__ . "/../products/common.php";   // $conn, goBack(), requirePost(), requireAdmin()
require_once __DIR__ . "/admin_checks.php";          // findUser(), adminCount()
requirePost("admin.php?tab=users");
requireAdmin();

$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId === (int) $_SESSION['user_id']) {
    goBack("admin.php?tab=users", "You can't delete your own account while signed in to it.");
}

$user = findUser($conn, $userId);
if (!$user) {
    goBack("admin.php?tab=users", "That user no longer exists.");
}
if ($user['role'] === 'admin' && adminCount($conn) <= 1) {
    goBack("admin.php?tab=users", "The shop needs at least one admin.");
}

$stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
goBack("admin.php?tab=users", "Deleted the account {$user['username']}.", "success");
?>
