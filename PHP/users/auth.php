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

// CSRF protection: a random secret kept in the session. PHP writes it into each form that changes
// something (csrfField() in PHP/page.php) and the form sends it back. Another website can't read it, so it can't
// trick a signed-in admin's browser into deleting products or placing orders.
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// True when the request carries the session's token (form field csrf_token)
function csrfValid()
{
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($sent) && hash_equals($_SESSION['csrf_token'], $sent);
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

// Page control: unless the signed-in user is an admin, send them to the sign-in page instead.
// $toRoot is the way from the running script up to the project folder: "" for admin.php,
// "../../" (the default) for the form handlers in PHP/<folder>/.
function requireAdmin($toRoot = "../../")
{
    global $conn;
    if (isset($conn)) {
        refreshSessionUser($conn);
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: {$toRoot}login.html?login_error=admin");
        exit;
    }
}
