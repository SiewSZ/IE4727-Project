<?php
// Handles the form on contact.html.
// Expects POST fields: name, email, subject, message
// Success: back to contact.html?sent=1
// Failure: back to contact.html?error=<code>

require_once __DIR__ . "/../db_connect.php";

function backToContact($query)
{
    header("Location: ../../contact.html?" . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../contact.html");
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = $_POST['subject'] ?? 'general';
$message = trim($_POST['message'] ?? '');

// Same checks as JS/contact.js, repeated here because browser checks can be bypassed
if ($name === '' || $email === '' || $message === '') {
    backToContact("error=missing");
}
// Letters, spaces, apostrophes, dots and hyphens only, e.g. "Tan Ah Kow" or "O'Brien"
if (!preg_match("/^[A-Za-z][A-Za-z .'-]{1,99}$/", $name)) {
    backToContact("error=name");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    backToContact("error=email");
}
if (!in_array($subject, ['general', 'order', 'trade-in', 'build', 'other'], true)) {
    $subject = 'general';
}
if (strlen($message) < 10 || strlen($message) > 2000) {
    backToContact("error=message");
}

$stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $subject, $message);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

backToContact("sent=1");
