<?php
// Small lookups shared by role.php and delete.php

function findUser($conn, $userId)
{
    $stmt = mysqli_prepare($conn, "SELECT id, username, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $user;
}

function adminCount($conn)
{
    return (int) mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin'"))[0];
}
?>
