<?php
// Handles the "Sign In" form on index.html.
// Expects POST fields: login (username or email), password
// Success: starts a session, back to index.html?login=success
// Failure: back to index.html?login_error=<code>

require_once __DIR__ . "/../db_connect.php";

function backToHome($query)
{
    header("Location: ../../index.html?" . $query);
    exit;
}

// Page control: only accept the form being submitted, not someone typing the URL directly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../index.html");
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    backToHome("login_error=missing");
}

// Is there an account with this username or email?
// (usernames can't contain "@", so this never matches two different users)
$stmt = mysqli_prepare($conn, "SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
mysqli_stmt_bind_param($stmt, "ss", $login, $login);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Same error for "no such user" and "wrong password",
// so the form can't be used to find out which usernames are registered
if (!$user || !password_verify($password, $user['password_hash'])) {
    backToHome("login_error=invalid");
}

// Logged in: give them a fresh session id, then remember who they are
session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

backToHome("login=success");
