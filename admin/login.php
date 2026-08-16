<?php

session_start();

require_once "../config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("
        SELECT id, email, password, first_name, last_name
        FROM admins
        WHERE email = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $admin = $result->fetch_assoc();

        if (password_verify($password, $admin['password'])) {

            $_SESSION['admin_id'] = $admin['id'];

            $_SESSION['admin_name'] =
                $admin['first_name'] . " " .
                $admin['last_name'];

            $_SESSION['admin_email'] =
                $admin['email'];

            header("Location: dashboard.php");
            exit();

        } else {

            $message = "Invalid email or password.";

        }

    } else {

        $message = "Invalid email or password.";

    }

    $stmt->close();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Login | TicketFlix</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    font-family: Arial, sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(126,87,194,.35),
            transparent 35%
        ),
        #100b18;

    color: white;

}

.login-box {

    width: 90%;

    max-width: 430px;

    padding: 40px;

    border-radius: 25px;

    background: rgba(255,255,255,.06);

    border:
        1px solid rgba(212,175,55,.3);

    box-shadow:
        0 20px 60px rgba(0,0,0,.5);

}

.logo {

    text-align: center;

    font-size: 30px;

    font-weight: bold;

    margin-bottom: 10px;

}

.logo span {

    color: #d4af37;

}

.subtitle {

    text-align: center;

    color: #aaa;

    margin-bottom: 30px;

}

label {

    display: block;

    margin-bottom: 8px;

    color: #ddd;

}

input {

    width: 100%;

    padding: 13px;

    margin-bottom: 20px;

    border-radius: 10px;

    border: 1px solid rgba(255,255,255,.15);

    background: rgba(255,255,255,.08);

    color: white;

    outline: none;

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

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

}

button:hover {

    transform: translateY(-2px);

}

.error {

    background: rgba(255,0,0,.12);

    border: 1px solid rgba(255,0,0,.25);

    color: #ff8c8c;

    padding: 12px;

    border-radius: 10px;

    margin-bottom: 20px;

    text-align: center;

}

.back {

    display: block;

    text-align: center;

    margin-top: 20px;

    color: #aaa;

    text-decoration: none;

}

.back:hover {

    color: #d4af37;

}

</style>

</head>

<body>

<div class="login-box">

    <div class="logo">

        🎬 Ticket<span>Flix</span>

    </div>

    <p class="subtitle">

        Admin Panel Login

    </p>

    <?php if ($message): ?>

        <div class="error">

            <?= htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>

    <form method="POST">

        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="Enter admin email"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter admin password"
            required
        >

        <button type="submit">

            🔐 Admin Login

        </button>

    </form>

    <a href="../index.php" class="back">

        ← Back to TicketFlix

    </a>

</div>

</body>

</html>