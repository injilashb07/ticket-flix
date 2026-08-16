<?php

session_start();

require_once "config.php";


/* =========================================================
   IF ALREADY LOGGED IN AS ADMIN
========================================================= */

if (isset($_SESSION['admin_id'])) {

    header("Location: admin/dashboard.php");
    exit();

}


$error = "";


/* =========================================================
   ADMIN LOGIN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    if ($email === "" || $password === "") {

        $error = "Please enter email and password.";

    } else {


        $stmt = $conn->prepare("
            SELECT
                id,
                email,
                password,
                first_name,
                last_name
            FROM admins
            WHERE email = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "s",
                $email
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $admin = $result->fetch_assoc();

            $stmt->close();


            /* =================================================
               CHECK PASSWORD
            ================================================= */

            if (
                $admin &&
                password_verify(
                    $password,
                    $admin['password']
                )
            ) {

                /* Create admin session */

                $_SESSION['admin_id'] =
                    (int) $admin['id'];

                $_SESSION['admin_email'] =
                    $admin['email'];

                $_SESSION['admin_name'] =
                    $admin['first_name'] .
                    " " .
                    $admin['last_name'];


                /* Go to dashboard */

                header(
                    "Location: admin/dashboard.php"
                );

                exit();

            } else {

                $error =
                    "Invalid admin email or password.";

            }

        }

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Login | TicketFlix</title>


<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    font-family: 'Poppins', sans-serif;

    background:

        radial-gradient(
            circle at top right,
            rgba(126,87,194,.25),
            transparent 35%
        ),

        radial-gradient(
            circle at bottom left,
            rgba(212,175,55,.12),
            transparent 35%
        ),

        #100b18;

    color: white;

    padding: 20px;

}


.login-card {

    width: 100%;

    max-width: 430px;

    padding: 40px;

    border-radius: 25px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(212,175,55,.20);

    box-shadow:
        0 25px 70px rgba(0,0,0,.45);

}


.logo {

    text-align: center;

    font-size: 30px;

    font-weight: 800;

    margin-bottom: 8px;

}


.logo span {

    color: #d4af37;

}


.admin-text {

    text-align: center;

    color: #999;

    font-size: 13px;

    margin-bottom: 30px;

}


.icon {

    width: 75px;

    height: 75px;

    margin: 0 auto 20px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    font-size: 32px;

}


label {

    display: block;

    margin-bottom: 7px;

    color: #ccc;

    font-size: 13px;

}


input {

    width: 100%;

    padding: 13px 15px;

    margin-bottom: 18px;

    border-radius: 12px;

    border:
        1px solid rgba(255,255,255,.12);

    background:
        rgba(255,255,255,.06);

    color: white;

    outline: none;

    font-family: inherit;

}


input:focus {

    border-color: #d4af37;

}


button {

    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 25px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f1d46a
        );

    color: #171020;

    font-size: 15px;

    font-weight: 800;

    cursor: pointer;

}


button:hover {

    transform: translateY(-2px);

}


.error {

    padding: 12px;

    margin-bottom: 20px;

    border-radius: 10px;

    background:
        rgba(231,76,60,.12);

    color: #ff8175;

    text-align: center;

    font-size: 13px;

}


.back {

    display: block;

    text-align: center;

    margin-top: 20px;

    color: #999;

    text-decoration: none;

    font-size: 13px;

}


.back:hover {

    color: #d4af37;

}

</style>

</head>


<body>


<div class="login-card">


    <div class="icon">

        👑

    </div>


    <div class="logo">

        Ticket<span>Flix</span>

    </div>


    <div class="admin-text">

        Administrator Login

    </div>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <form method="POST">


        <label>

            Admin Email

        </label>


        <input
            type="email"
            name="email"
            placeholder="Enter admin email"
            required
        >


        <label>

            Password

        </label>


        <input
            type="password"
            name="password"
            placeholder="Enter admin password"
            required
        >


        <button type="submit">

            👑 Login as Admin

        </button>


    </form>


    <a
        href="login.php"
        class="back"
    >

        ← Back to User Login

    </a>


</div>


</body>

</html>