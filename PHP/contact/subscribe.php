<?php
// Handles the "Email Deals" sign-up form on hotdeals.php.
// Expects POST field: email
// Success: back to hotdeals.php?subscribed=1
// Failure: back to hotdeals.php?subscribe_error=email

require_once __DIR__ . "/../db_connect.php";

function backToDeals($query)
{
    // #email-deals scrolls the page back down to the form so the message is seen
    header("Location: ../../hotdeals.php?" . $query . "#email-deals");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../hotdeals.php");
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    backToDeals("subscribe_error=email");
}

// INSERT IGNORE: signing up twice with the same email is not an error, it is just already on the list
$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO subscribers (email) VALUES (?)");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

backToDeals("subscribed=1");
