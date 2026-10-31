<?php
// Handles the "Sign In" form on index.php.
// Expects POST fields: login (username or email), password
// Success: starts a session, back to index.php?login=success
// Failure: back to index.php?login_error=<code>

require_once __DIR__ . "/../db_connect.php";

// 5 wrong passwords in a row lock the account for 5 minutes, which stops password guessing
const MAX_FAILED_LOGINS = 5;
const LOCK_MINUTES = 5;

function backToHome($query)
{
    header("Location: ../../index.php?" . $query);
    exit;
}

// Page control: only accept the form being submitted, not someone typing the URL directly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../index.php");
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    backToHome("login_error=missing");
}

// Is there an account with this username or email?
// (usernames can't contain "@", so this never matches two different users)
$stmt = mysqli_prepare($conn,
    "SELECT id, username, password_hash, role, failed_logins, locked_until > NOW() AS locked
     FROM users WHERE username = ? OR email = ?");
mysqli_stmt_bind_param($stmt, "ss", $login, $login);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($user && $user['locked']) {
    backToHome("login_error=locked");
}

// Same error for "no such user" and "wrong password",
// so the form can't be used to find out which usernames are registered
if (!$user || !password_verify($password, $user['password_hash'])) {
    if ($user) {
        // Count the miss; on the 5th in a row, lock the account and start counting again
        $failed = $user['failed_logins'] + 1;
        $lock = $failed >= MAX_FAILED_LOGINS;
        $stmt = mysqli_prepare($conn,
            "UPDATE users SET failed_logins = ?, locked_until = IF(?, NOW() + INTERVAL " . LOCK_MINUTES . " MINUTE, locked_until)
             WHERE id = ?");
        $failedToSave = $lock ? 0 : $failed;
        $lockFlag = $lock ? 1 : 0;
        mysqli_stmt_bind_param($stmt, "iii", $failedToSave, $lockFlag, $user['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($lock) {
            backToHome("login_error=locked");
        }
    }
    backToHome("login_error=invalid");
}

// Remember when they signed in, for the admin Users tab
$stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW(), failed_logins = 0, locked_until = NULL WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Logged in: give them a fresh session id, then remember who they are
session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];   // 'customer' or 'admin', checked by requireAdmin() in auth.php
$_SESSION['last_activity'] = time(); // for the 30-minute auto sign-out in auth.php

backToHome("login=success");
