<?php
// Shared helpers for the form handlers in PHP/products, PHP/orders, PHP/users and PHP/shopping_cart.
// Every handler is the action of a normal HTML form: it does its work, saves a message for the
// page (setFlash in PHP/page.php) and redirects back, so the page shows the result after reloading.

require_once __DIR__ . "/../db_connect.php";
require_once __DIR__ . "/../users/auth.php";   // the session, csrfValid() and requireAdmin()
require_once __DIR__ . "/../page.php";         // setFlash()

// Where to go back to: the page named in the form's "return" field, if it is one of this site's
// pages (e.g. "shop.php?q=ssd#product-4"), otherwise $default. Never an address on another site.
function returnPage($default)
{
    $page = $_POST['return'] ?? '';
    return is_string($page) && preg_match('/^[a-z0-9-]+\.php([?#][^\s<>"]*)?$/i', $page) ? $page : $default;
}

// Show $message on the page (an error unless $type is "success") and stop.
// Handlers live two folders down (PHP/<folder>/), so the pages are at ../../
function goBack($page, $message = "", $type = "error", $linkUrl = "", $linkText = "")
{
    global $conn;
    if ($message !== "") {
        setFlash($message, $type, $linkUrl, $linkText);
    }
    if ($conn) {
        mysqli_close($conn);
        $conn = null;
    }
    header("Location: ../../" . $page);
    exit;
}

// Only accept POST for anything that changes the database, and only with the session's CSRF token
function requirePost($page)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        goBack($page);
    }
    if (!csrfValid()) {
        goBack($page, "Your session has expired. Please try again.");
    }
}

// Images uploaded from the admin page are stored here (relative to the project root).
// Kept separate from Images/products/ so deleting a product never removes the bundled images.
const UPLOAD_DIR = "Images/uploads/";

// Remove an uploaded product image, but never touch files outside Images/uploads/
function deleteUploadedImage($imagePath)
{
    if ($imagePath && str_starts_with($imagePath, UPLOAD_DIR)) {
        $file = __DIR__ . "/../../" . $imagePath;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
?>
