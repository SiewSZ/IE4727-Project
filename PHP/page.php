<?php
// Shared by every page with the site header (required at the top, before any HTML is sent).
// The header is drawn on the server from the PHP session: the cart count, "Hi, <name>" with
// My Orders / Admin / Logout once signed in, the product names for the header search, and the
// one-off message (flash) that a form handler left after redirecting back here.

require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/users/auth.php";   // the session, csrfToken(), refreshSessionUser()

// Pick up role changes (or a deleted account) made on the admin Users tab since they signed in
refreshSessionUser($conn);

// Product names offered while typing in the header search (a <datalist>, see searchBox())
$searchNames = array_column(mysqli_fetch_all(mysqli_query($conn,
    "SELECT name FROM products WHERE is_active = 1 ORDER BY name")), 0);

if (!function_exists('h')) {
    // Database text goes into HTML, so escape it first (also defined in PHP/products/render.php)
    function h($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

// Total quantity in the session cart, kept as [product_id => quantity]
function cartCount()
{
    return array_sum($_SESSION['cart'] ?? []);
}

// Header search: a GET form to shop.php, with matching product names suggested by the browser
function searchBox()
{
    global $searchNames;
    ?>
                <form class="search-box" action="shop.php" method="get" role="search">
                    <input type="search" name="q" placeholder="Search..." aria-label="Search products" list="searchNames" autocomplete="off">
                    <button type="submit" aria-label="Search"><i class="fa fa-search"></i></button>
                    <datalist id="searchNames">
<?php foreach ($searchNames as $name): ?>
                        <option value="<?= h($name) ?>"></option>
<?php endforeach; ?>
                    </datalist>
                </form>
<?php
}

// Login/Register for visitors; "Hi, <name>", My Orders, Admin (admins only) and Logout once signed in
function userMenu()
{
    if (empty($_SESSION['user_id'])) {
        echo '<a href="login.html" class="btn" id="openLoginBtn">Login/Register</a>';
        return;
    }
    ?>
<div class="user-menu">
                    <span class="user-greeting"><i class="fa-solid fa-user"></i> Hi, <strong><?= h($_SESSION['username']) ?></strong></span>
                    <a href="my-orders.php" class="user-link"><i class="fa-solid fa-box-open"></i> My Orders</a>
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="admin.php" class="user-link"><i class="fa-solid fa-gear"></i> Admin</a>
<?php endif; ?>
                    <form method="post" action="PHP/users/logout.php">
                        <button type="submit" class="btn">Logout</button>
                    </form>
                </div>
<?php
}

// ---------- Messages after a form is sent ----------
// A handler such as PHP/shopping_cart/cart.php saves a message with setFlash() and redirects back;
// the page shows it once with flashMessage() (JS/menu.js closes it after a few seconds).
function setFlash($text, $type = "success", $linkUrl = "", $linkText = "")
{
    $_SESSION['flash'] = ["text" => $text, "type" => $type, "linkUrl" => $linkUrl, "linkText" => $linkText];
}

function flashMessage()
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (!$flash) {
        return;
    }
    $icon = $flash['type'] === "error" ? "fa-circle-exclamation" : "fa-circle-check";
    ?>
    <div class="flash <?= h($flash['type']) ?>" role="<?= $flash['type'] === "error" ? "alert" : "status" ?>">
        <i class="fa-solid <?= $icon ?>"></i>
        <p><?= h($flash['text']) ?><?php if ($flash['linkUrl']): ?> <a href="<?= h($flash['linkUrl']) ?>"><?= h($flash['linkText']) ?></a><?php endif; ?></p>
        <button type="button" class="flash-close" aria-label="Close message">&times;</button>
    </div>
<?php
}

// ---------- Hidden fields for forms that change something ----------
function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

// This page's address (e.g. "shop.php?q=ssd"), so a handler can send the visitor back to it
function currentPage()
{
    $path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: "index.php");
    $query = $_SERVER['QUERY_STRING'] ?? "";
    return ($path === "" ? "index.php" : $path) . ($query === "" ? "" : "?$query");
}

function returnField($page = null)
{
    return '<input type="hidden" name="return" value="' . h($page ?? currentPage()) . '">';
}
?>
