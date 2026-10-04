<?php
// Handles the "Create Account" form on index.html.
// Expects POST fields: username, email, password
// Success: back to index.html?register=success
// Failure: back to index.html?register_error=<code>

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

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Check again on the server: browser/JS validation can be bypassed
if ($username === '' || $email === '' || $password === '') {
    backToHome("register_error=missing");
}
if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    backToHome("register_error=username");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    backToHome("register_error=email");
}
if (strlen($password) < 8) {
    backToHome("register_error=password");
}

// Is the username or email already registered? 
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
mysqli_stmt_bind_param($stmt, "ss", $username, $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$alreadyTaken = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($alreadyTaken) {
    backToHome("register_error=taken");
}

// Never store the real password, only a one-way hash of it 
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "sss", $username, $email, $passwordHash);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

backToHome("register=success");