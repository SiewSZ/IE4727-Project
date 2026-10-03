<?php
require_once "db_connect.php";

// Trim and collect the submitted fields (matches the fields validated in jobvalidation.js)
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$startDate = trim($_POST['start-date'] ?? '');
$experience = trim($_POST['experience'] ?? '');

// The JS validation on Jobs.html can be bypassed (JS disabled, or a raw request),
// so the required fields are checked again here before touching the database.
if ($name === '' || $email === '' || $experience === '') {
    die("Please fill in all required fields.");
}

$startDateValue = $startDate === '' ? null : $startDate;

$sql = "INSERT INTO job_applications (name, email, start_date, experience) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $startDateValue, $experience);

if (mysqli_stmt_execute($stmt)) {
    $applicationId = mysqli_insert_id($conn);
} else {
    die("Could not save your application: " . mysqli_stmt_error($stmt));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="source.css">
    <title>Application Received - JavaJam Coffee House</title>
</head>
<body>
    <header>
        <img src="Header.png" alt="JavaJam Coffee House">
    </header>

    <div id="content">
        <nav>
            <a href="Home.html">Home</a>
            <a href="Menu.html">Menu</a>
            <a href="Music.html">Music</a>
            <a href="Jobs.html">Jobs</a>
        </nav>
        <main>
            <h2>Thank you, <?php echo htmlspecialchars($name); ?>!</h2>
            <p>Your application (#<?php echo $applicationId; ?>) has been received.</p>
            <table>
                <tr><td class="item">Name</td><td class="desc"><?php echo htmlspecialchars($name); ?></td></tr>
                <tr><td class="item">E-mail</td><td class="desc"><?php echo htmlspecialchars($email); ?></td></tr>
                <tr><td class="item">Start Date</td><td class="desc"><?php echo htmlspecialchars($startDate !== '' ? $startDate : 'Not specified'); ?></td></tr>
                <tr><td class="item">Experience</td><td class="desc"><?php echo nl2br(htmlspecialchars($experience)); ?></td></tr>
            </table>
            <p><a href="Jobs.html">Submit another application</a></p>
        </main>
    </div>

    <footer>
        <p><i>Copyright &copy; 2014 JavaJam Coffee House</i></p>
        <a href="mailto:SzeZhe.Siew@gmail.com"><i>SzeZhe.Siew@gmail.com</i></a>
    </footer>
</body>
</html>
