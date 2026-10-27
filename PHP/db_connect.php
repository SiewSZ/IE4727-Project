<?php
// Default XAMPP MySQL credentials -- change these if you've set a root password
$dbHost = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "quantum_gear";

// Make mysqli throw exceptions (already the default on PHP 8.1+) so errors can be caught below
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Builds the database from database/schema.sql, which creates quantum_gear, its tables
// and the starting products. Returns a connection to the new database.
function createDatabaseFromSchema($host, $user, $password, $name)
{
    $sql = file_get_contents(__DIR__ . "/../database/schema.sql");
    if ($sql === false) {
        die("Database setup failed: could not read database/schema.sql.");
    }

    // Connect without choosing a database, because it doesn't exist yet
    $conn = mysqli_connect($host, $user, $password);

    // schema.sql holds many statements, so run them all with multi_query and step through
    // every result: an error in any statement then throws instead of being skipped silently
    mysqli_multi_query($conn, $sql);
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));

    mysqli_select_db($conn, $name);
    return $conn;
}

try {
    $conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
} catch (mysqli_sql_exception $e) {
    // 1049 = "Unknown database": the first time the site runs on this PC, so create it
    if ($e->getCode() !== 1049) {
        die("Connection failed: " . $e->getMessage() . " (Is MySQL running in XAMPP?)");
    }

    try {
        $conn = createDatabaseFromSchema($dbHost, $dbUser, $dbPassword, $dbName);
    } catch (mysqli_sql_exception $e) {
        die("Database setup failed while running database/schema.sql: " . $e->getMessage());
    }
}
?>
