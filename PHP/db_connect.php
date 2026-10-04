<?php
// Default XAMPP MySQL credentials -- change these if you've set a root password
$dbHost = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "quantum_gear";

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
