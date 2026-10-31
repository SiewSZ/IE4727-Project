<?php
// Handles the Logout button that JS/session.js puts in the header of every page.
// Ends the whole session (login and cart), then goes back to index.html.

// Page control: only accept the button being pressed, not someone typing the URL directly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../index.html");
    exit;
}

session_start();

// Empty the session, delete its cookie from the browser, then remove it from the server
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), "", time() - 3600, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
session_destroy();

header("Location: ../../index.html");
exit;
