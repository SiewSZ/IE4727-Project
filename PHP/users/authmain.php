<?php
// Handles the "Sign In" form on index.php.
// Expects POST fields: login (username or email), password
// Success: starts a session, back to index.php?login=success
// Failure: back to index.php?login_error=<code>

require_once __DIR__ . "/../db_connect.php";

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
$stmt = mysqli_prepare($conn, "SELECT id, username, password_hash, role FROM users WHERE username = ? OR email = ?");
mysqli_stmt_bind_param($stmt, "ss", $login, $login);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Same error for "no such user" and "wrong password",
// so the form can't be used to find out which usernames are registered
if (!$user || !password_verify($password, $user['password_hash'])) {
    backToHome("login_error=invalid");
}

// Remember when they signed in, for the admin Users tab
$stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
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
