<?php
// Counts how many times this browser has visited the page, using cookies.
// Open: http://localhost/IE4727-Project/PHP/cookies/cookiecounter.php
// Reset: add ?reset=1 to the URL

date_default_timezone_set("Asia/Singapore");   // XAMPP's default timezone is usually Europe/Berlin

$cookieDays = 30;
$expires = time() + $cookieDays * 24 * 60 * 60;

if (isset($_GET['reset'])) {
    // Deleting a cookie = setting it again with an expiry time in the past
    setcookie("visit_count", "", time() - 3600, "/");
    setcookie("last_visit", "", time() - 3600, "/");
    header("Location: cookiecounter.php");
    exit;
}

// Read the old values sent by the browser (they don't exist on the first visit)
$visitCount = isset($_COOKIE['visit_count']) ? (int) $_COOKIE['visit_count'] : 0;
$lastVisit  = $_COOKIE['last_visit'] ?? null;

$visitCount++;

// setcookie() must run before any HTML is output
setcookie("visit_count", $visitCount, $expires, "/");
setcookie("last_visit", date("d M Y, h:i A"), $expires, "/");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cookie Counter</title>
</head>
<body>
    <h1>Cookie Counter</h1>

    <?php if ($visitCount === 1): ?>
        <p>Welcome! This is your first visit.</p>
    <?php else: ?>
        <p>You have visited this page <strong><?php echo $visitCount; ?></strong> times.</p>
        <p>Your last visit was on <?php echo htmlspecialchars($lastVisit); ?>.</p>
    <?php endif; ?>

    <p><a href="cookiecounter.php?reset=1">Reset counter</a></p>
</body>
</html>
