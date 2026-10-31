<?php
// Session helpers shared by every endpoint that needs the session.
// authmain.php saves user_id, username, role and last_activity in the session at login,
// and the shopping cart (PHP/shopping_cart/cart.php) is kept in the same session.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto sign-out: a signed-in user who makes no request for 30 minutes is signed out.
// Only the login is forgotten; the cart stays in the session.
const SESSION_TIMEOUT = 30 * 60;   // seconds

if (isset($_SESSION['user_id'])) {
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['last_activity']);
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// The role can change while someone is signed in (admins manage it on the Users tab), so read
// it from the database instead of trusting the copy saved at login. An account that has been
// deleted is signed out. Needs $conn from db_connect.php.
function refreshSessionUser($conn)
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    $stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row) {
        $_SESSION['role'] = $row['role'];
    } else {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['last_activity']);
    }
}

// Page control: stop the request unless the signed-in user is an admin.
// 401 = not signed in at all, 403 = signed in but not an admin.
function requireAdmin()
{
    global $conn;
    if (isset($conn)) {
        refreshSessionUser($conn);
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(isset($_SESSION['user_id']) ? 403 : 401);
        header("Content-Type: application/json");
        echo json_encode(["error" => "Only admins can do this. Please sign in with an admin account."]);
        exit;
    }
}
