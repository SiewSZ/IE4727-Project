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

// Page control: stop the request unless the signed-in user is an admin.
// 401 = not signed in at all, 403 = signed in but not an admin.
function requireAdmin()
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(isset($_SESSION['user_id']) ? 403 : 401);
        header("Content-Type: application/json");
        echo json_encode(["error" => "Only admins can do this. Please sign in with an admin account."]);
        exit;
    }
}
