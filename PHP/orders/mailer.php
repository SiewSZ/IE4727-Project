<?php
// Order emails: a confirmation when an order is placed (place.php) and an update whenever
// an admin changes its status (status.php).
//
// Project rule: emails may only go to our own account on the local mail server, never to a
// customer's real address. So every email is delivered to LOCAL_MAILBOX (Mercury, which comes
// with XAMPP), and says at the top which customer it was meant for.
//
// Every email is also saved in the email_log table, with whether the mail server accepted it.
// If Mercury isn't running, ordering still works and the log shows what would have been sent.

const LOCAL_MAILBOX = "newuser@localhost";             // a Mercury mailbox (XAMPP's "Test User")
const SHOP_FROM = "Quantum Gear <orders@localhost>";

function sendOrderEmail($conn, $customerEmail, $subject, $body)
{
    $message = "To: $customerEmail (delivered to " . LOCAL_MAILBOX . " for this project)\r\n\r\n" . $body
             . "\r\n\r\nQuantum Gear\r\n50 Nanyang Ave, Singapore | +65 6123 4567";
    $headers = "From: " . SHOP_FROM . "\r\nContent-Type: text/plain; charset=UTF-8";

    // @ hides PHP's warning when no mail server is running, so the page's JSON reply stays valid
    $sent = @mail(LOCAL_MAILBOX, $subject, $message, $headers) ? 1 : 0;

    $stmt = mysqli_prepare($conn,
        "INSERT INTO email_log (to_address, intended_for, subject, body, sent) VALUES (?, ?, ?, ?, ?)");
    $to = LOCAL_MAILBOX;
    mysqli_stmt_bind_param($stmt, "ssssi", $to, $customerEmail, $subject, $message, $sent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool) $sent;
}

// One line per item, for the email body
function orderLinesText($items)
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = sprintf("  %d x %s  $%s", $item['qty'], $item['name'], number_format($item['unit_price'] * $item['qty'], 2));
    }
    return implode("\r\n", $lines);
}
?>
