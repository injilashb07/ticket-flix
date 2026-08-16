<?php

require_once "config.php";

$first_name = "Admin";
$last_name = "TicketFlix";
$email = "admin@ticketflix.com";
$password = "Admin@123";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO admins
    (email, password, first_name, last_name)
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param(
    "ssss",
    $email,
    $hashed_password,
    $first_name,
    $last_name
);

if ($stmt->execute()) {

    echo "<h2>Admin account created successfully! ✅</h2>";

    echo "<p>Email: <b>admin@ticketflix.com</b></p>";
    echo "<p>Password: <b>Admin@123</b></p>";

} else {

    echo "Error: " . $stmt->error;

}

$stmt->close();
$conn->close();

?>