<?php
// Session helpers shared by every endpoint that needs to know who is signed in.
// authmain.php saves user_id, username and role in the session when someone logs in.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
