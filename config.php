<?php

/* ================================
   START SESSION
================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ================================
   DATABASE CONNECTION
================================ */

$server = '193.203.184.233';
$user = 'u217687379_ticket_flix';
$pass = 'TicketFlix@2026';
$db = 'u217687379_ticket_flix';


$conn = new mysqli(
    $server,
    $user,
    $pass,
    $db
);


/* ================================
   CHECK CONNECTION
================================ */

if ($conn->connect_error) {

    die(
        "Database Connection Failed: "
        . $conn->connect_error
    );

}


/* ================================
   CHARACTER SET
================================ */

$conn->set_charset("utf8mb4");

?>



 <!-- // Gmail App Password
    $mail->Password   = 'iork apqv inxs jiwt'; -->